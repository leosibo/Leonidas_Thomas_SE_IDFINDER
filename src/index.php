<?php
declare(strict_types=1);

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Africa/Kigali');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(string $name): string
{
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}

function database(): PDO
{
    $localConfig = __DIR__ . '/config.php';
    $config = require is_file($localConfig) ? $localConfig : __DIR__ . '/config.example.php';
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function uploadPhoto(): ?string
{
    $file = $_FILES['photo'] ?? [];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024
        || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Choose a JPG or PNG photo under 2 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        throw new InvalidArgumentException('The attachment must be a JPG or PNG image.');
    }
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) {
        throw new RuntimeException('Cannot read uploaded photo.');
    }
    return $bytes;
}

$view = $_GET['view'] ?? 'home';
$view = in_array($view, ['report', 'search', 'receipt'], true) ? $view : 'home';
$types = [1 => 'National ID', 2 => 'Student ID', 3 => 'Driving licence'];
$districts = [
    'Bugesera', 'Burera', 'Gakenke', 'Gasabo', 'Gatsibo', 'Gicumbi', 'Gisagara',
    'Huye', 'Kamonyi', 'Karongi', 'Kayonza', 'Kicukiro', 'Kirehe', 'Muhanga',
    'Musanze', 'Ngoma', 'Ngororero', 'Nyabihu', 'Nyagatare', 'Nyamagabe',
    'Nyamasheke', 'Nyanza', 'Nyarugenge', 'Nyaruguru', 'Rubavu', 'Ruhango',
    'Rulindo', 'Rusizi', 'Rutsiro', 'Rwamagana',
];
$_SESSION['token'] = $_SESSION['token'] ?? bin2hex(random_bytes(24));
$error = '';
$results = null;
$receipt = $_SESSION['receipt'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($view, ['search', 'report'], true)) {
    $db = null;
    try {
        if (!hash_equals($_SESSION['token'], input('token'))) {
            throw new InvalidArgumentException('The form expired. Please try again.');
        }
        if ($view === 'search') {
            $method = input('method');
            $query = input('query');
            if (!in_array($method, ['name', 'number'], true) || strlen($query) < 3 || strlen($query) > 120) {
                throw new InvalidArgumentException('Choose a search method and enter at least three characters.');
            }
            $now = time();
            $recent = array_filter($_SESSION['searches'] ?? [], function ($time) use ($now) {
                return $time > $now - 60;
            });
            if (count($recent) >= 12) {
                throw new InvalidArgumentException('Please wait a minute before searching again.');
            }
            $recent[] = $now;
            $_SESSION['searches'] = $recent;
            $column = $method === 'number' ? 'd.document_number' : 'd.holder_name';
            $db = database();
            $statement = $db->prepare(
                "SELECT t.label, r.district, r.reference
                 FROM found_reports r JOIN id_documents d ON d.id = r.document_id
                 JOIN document_types t ON t.id = d.type_id
                 WHERE {$column} = ? ORDER BY r.id DESC LIMIT 20"
            );
            $statement->execute([$method === 'number' ? strtoupper($query) : $query]);
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $values = [];
            foreach (['finder'=>120, 'phone'=>25, 'holder'=>120, 'number'=>40, 'district'=>60, 'location'=>180] as $key=>$limit) {
                $values[$key] = input($key);
                if ($values[$key] === '' || strlen($values[$key]) > $limit) {
                    throw new InvalidArgumentException('Complete every required field within its character limit.');
                }
            }
            $type = input('type');
            $date = input('found_on');
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!in_array($values['district'], $districts, true)) {
                throw new InvalidArgumentException('Select the district where you found the ID.');
            }
            if (!isset($types[$type]) || !preg_match('/^[A-Z0-9-]{3,40}$/iD', $values['number'])
                || !preg_match('/^\+?[0-9][0-9 ()-]{6,23}$/D', $values['phone'])
                || !$parsedDate || $parsedDate->format('Y-m-d') !== $date || $date > date('Y-m-d')
                || $date < '1900-01-01') {
                throw new InvalidArgumentException('Check the document type, ID number, phone number and date.');
            }
            $photo = uploadPhoto();
            $reference = 'IDF-' . strtoupper(bin2hex(random_bytes(8)));
            $db = database();
            $db->beginTransaction();
            $statement = $db->prepare(
                'INSERT INTO id_documents (type_id, holder_name, document_number) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $statement->execute([$type, $values['holder'], strtoupper($values['number'])]);
            $documentId = $db->lastInsertId();
            $statement = $db->prepare('SELECT holder_name FROM id_documents WHERE id = ?');
            $statement->execute([$documentId]);
            if (strcasecmp($statement->fetchColumn(), $values['holder']) !== 0) {
                throw new InvalidArgumentException('This document number is already recorded with a different name.');
            }
            $statement = $db->prepare('INSERT INTO finders (full_name, phone) VALUES (?, ?)');
            $statement->execute([$values['finder'], $values['phone']]);
            $finderId = $db->lastInsertId();
            $statement = $db->prepare(
                'INSERT INTO found_reports (reference, document_id, finder_id, district, location_detail, found_on, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ([$reference, $documentId, $finderId, $values['district'], $values['location'], $date] as $i=>$value) {
                $statement->bindValue($i + 1, $value);
            }
            $statement->bindValue(7, $photo, $photo === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $statement->execute();
            $db->commit();
            $_SESSION['receipt'] = $reference;
            $_SESSION['token'] = bin2hex(random_bytes(24));
            header('Location: ?view=receipt', true, 303);
            exit;
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('ID Finder demo: database or upload operation failed.');
        $error = 'Could not complete the request. Check MySQL, the SQL import and config.php.';
    } finally {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
    }
}
function field(string $name, string $label, int $max, string $type = 'text'): void
{
    echo '<label>' . escape($label) . '<input required name="' . escape($name) . '" type="' . escape($type)
        . '" maxlength="' . $max . '" value="' . escape(input($name)) . '"></label>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ID Finder Rwanda</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top"><div class="wrap bar">
  <a class="brand" href="index.php"><span>↗</span> ID Finder Rwanda</a>
  <nav aria-label="Main navigation"><a href="?view=search">Search IDs</a><a href="?view=report">Report a find</a></nav>
</div></header>
<main class="wrap">
<?php if ($view === 'home'): ?>
  <section class="hero">
    <div><h1>Lost an ID?<br>Start here.</h1>
      <p>Search for a reported identity document or let someone know you found theirs.</p>
      <a class="button" href="?view=search">Search for my ID <span aria-hidden="true">↗</span></a>
    </div>
    <aside class="finder"><h2>Found an ID?</h2>
      <p>Record where you found it and get a reference for your report.</p>
      <a class="button dark" href="?view=report">Report a found ID</a>
    </aside>
  </section>
  <section class="strip"><strong>Trying the demo?</strong> Search for <b>Demo Owner</b> or <b>DEMO-0001</b>. Use sample details when submitting a new report.</section>
<?php elseif ($view === 'report'): ?>
  <h1>Report a found ID</h1>
  <p>Use fictional information and a sample image for this local demonstration.</p>
  <?php if ($error): ?><p class="notice error" role="alert"><?= escape($error) ?></p><?php endif; ?>
  <form class="panel" method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?= escape($_SESSION['token']) ?>">
    <fieldset><legend>Document</legend><div class="grid">
      <label>Document type<select name="type" required>
      <?php foreach ($types as $key=>$label): ?>
        <option value="<?= $key ?>" <?= input('type') === (string)$key ? 'selected' : '' ?>><?= escape($label) ?></option>
      <?php endforeach; ?>
      </select></label>
      <?php field('holder', 'Full name on ID', 120); field('number', 'ID number', 40); ?>
    </div></fieldset>
    <fieldset><legend>Where you found it</legend><div class="grid">
      <label>District<select name="district" required>
        <option value="">Choose a district</option>
        <?php foreach ($districts as $district): ?>
          <option <?= input('district') === $district ? 'selected' : '' ?>><?= escape($district) ?></option>
        <?php endforeach; ?>
      </select></label>
      <?php field('location', 'Exact location or landmark', 180); field('found_on', 'Date found', 10, 'date'); ?>
      <label>Photo (optional)<input type="file" name="photo" accept="image/jpeg,image/png"><small>JPG or PNG, up to 2 MB. Stored privately.</small></label>
    </div></fieldset>
    <fieldset><legend>Finder contact</legend><div class="grid">
      <?php field('finder', 'Your name', 120); field('phone', 'Your phone number', 25, 'tel'); ?>
    </div></fieldset>
    <button class="button" type="submit">Submit report ↗</button>
  </form>
<?php elseif ($view === 'search'): ?>
  <h1>Search for an ID</h1>
  <p>Use the full name as printed on the card or the exact ID number.</p>
  <?php if ($error): ?><p class="notice error" role="alert"><?= escape($error) ?></p><?php endif; ?>
  <form class="panel search" method="post">
    <input type="hidden" name="token" value="<?= escape($_SESSION['token']) ?>">
    <label>Search by<select name="method">
      <option value="name">Full name</option>
      <option value="number" <?= input('method') === 'number' ? 'selected' : '' ?>>ID number</option>
    </select></label>
    <label>Search value<input required minlength="3" name="query" maxlength="120" value="<?= escape(input('query')) ?>" placeholder="e.g. Demo Owner"></label>
    <button class="button" type="submit">Search ↗</button>
  </form>
  <?php if ($results !== null): ?>
    <section class="results" aria-label="Search results">
      <h2><?= $results ? 'Possible matches' : 'No matching ID found' ?></h2>
      <?php if (!$results): ?><p>Check the spelling or try the exact ID number.</p><?php endif; ?>
      <?php foreach ($results as $result): ?>
        <article class="match"><div><strong><?= escape($result['label']) ?></strong><br><?= escape($result['district']) ?></div>
          <div>Report reference<br><strong><?= escape($result['reference']) ?></strong></div></article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
<?php else: ?>
  <h1><?= $receipt ? 'Report saved' : 'No recent report' ?></h1>
  <section class="panel">
    <?php if ($receipt): ?><p>Keep your report reference.</p><p class="reference"><?= escape($receipt) ?></p><?php endif; ?>
    <a class="button" href="?view=search">Go to search ↗</a>
    <a class="quiet" href="?view=report">Report another ID</a>
  </section>
<?php endif; ?>
</main>
<footer class="wrap">ID Finder Rwanda <span>Local M4 demonstration</span></footer>
</body>
</html>
