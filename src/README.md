# ID Finder Rwanda M4 Demo

PHP 7.4+ with PDO MySQL and sessions, Apache 2.4 and MySQL or MariaDB. The pages and styles load locally.

## Run in AMPPS

1. Copy the contents of the repository’s `src/` folder into a new `idfinder` folder inside AMPPS’s `www` folder.
2. Start Apache and MySQL in AMPPS. Open phpMyAdmin from AMPPS and import `idfinder/database/idfinder.sql`. The script creates and selects the `idfinder_m4` database.
3. Copy `idfinder/config.example.php` to `idfinder/config.php`, then enter your local MySQL username, password and port. Defaults are `root`, a blank password and port `3306`.
4. Visit [http://localhost/idfinder/](http://localhost/idfinder/). If Apache uses a different port, include it in the URL.

## Demonstrate the workflow

| Action | What to check |
| --- | --- |
| Search by full name using `Demo Owner` | A National ID result in Rwamagana appears with reference `IDF-DEMO-0001`. |
| Search by ID number using `DEMO-0001` | The same result appears. |
| Search by name using `Unlisted Owner` | The page shows **No matching ID found**. |
| Submit a report with a fictional name, a unique ID number such as `DEMO-0002`, district, landmark, date and sample phone | The confirmation shows a new report reference. Attach `sample/demo-id.png` to demonstrate an optional photo upload. |
| Search for the newly reported ID by its number | The new reference and district appear. The result shows no photo, phone, exact location or full ID number. |

The SQL import includes one sample record. Photos are optional and stored in the database, which keeps them out of public web folders and includes them in database backups. Use fictional details for this local classroom demo. The search limit is per browser session. The scope ends at finding a possible match and receiving a report reference.
