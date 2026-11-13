CREATE DATABASE IF NOT EXISTS idfinder_m4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE idfinder_m4;

CREATE TABLE IF NOT EXISTS document_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS finders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(25) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS id_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type_id INT UNSIGNED NOT NULL,
  holder_name VARCHAR(120) NOT NULL,
  document_number VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  UNIQUE KEY document_identity (type_id, document_number),
  KEY lookup_number (document_number),
  KEY lookup_name (holder_name),
  FOREIGN KEY (type_id) REFERENCES document_types(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS found_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(24) NOT NULL UNIQUE,
  document_id INT UNSIGNED NOT NULL,
  finder_id INT UNSIGNED NOT NULL,
  district VARCHAR(60) NOT NULL,
  location_detail VARCHAR(180) NOT NULL,
  found_on DATE NOT NULL,
  photo LONGBLOB NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES id_documents(id),
  FOREIGN KEY (finder_id) REFERENCES finders(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO document_types (id, label) VALUES
  (1, 'National ID'), (2, 'Student ID'), (3, 'Driving licence');
INSERT IGNORE INTO finders (id, full_name, phone) VALUES (1, 'Demo Finder', '0700000000');
INSERT IGNORE INTO id_documents (id, type_id, holder_name, document_number)
VALUES (1, 1, 'Demo Owner', 'DEMO-0001');
INSERT IGNORE INTO found_reports (id, reference, document_id, finder_id, district, location_detail, found_on)
VALUES (1, 'IDF-DEMO-0001', 1, 1, 'Rwamagana', 'Near the demo market', '2026-11-12');
