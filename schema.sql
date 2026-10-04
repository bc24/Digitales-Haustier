SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(60) NOT NULL DEFAULT '',
  bio TEXT NULL,
  avatar VARCHAR(16) NOT NULL DEFAULT '🙂',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS species (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL UNIQUE,
  emoji VARCHAR(16) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  temperament VARCHAR(40) NOT NULL DEFAULT 'Ausgeglichen',
  color VARCHAR(7) NOT NULL DEFAULT '#ffd6e0',
  favorite_food VARCHAR(40) NOT NULL DEFAULT 'Leckerli',
  base_affection TINYINT UNSIGNED NOT NULL DEFAULT 30,
  food_decay DECIMAL(4,1) NOT NULL DEFAULT 4.0,
  fun_decay DECIMAL(4,1) NOT NULL DEFAULT 3.0,
  clean_decay DECIMAL(4,1) NOT NULL DEFAULT 2.0,
  energy_decay DECIMAL(4,1) NOT NULL DEFAULT 3.0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS relations (
  species_a INT UNSIGNED NOT NULL,
  species_b INT UNSIGNED NOT NULL,
  affinity TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (species_a, species_b),
  FOREIGN KEY (species_a) REFERENCES species(id) ON DELETE CASCADE,
  FOREIGN KEY (species_b) REFERENCES species(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  species_id INT UNSIGNED NOT NULL,
  name VARCHAR(30) NOT NULL,
  food DECIMAL(5,1) NOT NULL DEFAULT 80,
  fun DECIMAL(5,1) NOT NULL DEFAULT 70,
  clean DECIMAL(5,1) NOT NULL DEFAULT 80,
  energy DECIMAL(5,1) NOT NULL DEFAULT 90,
  health DECIMAL(5,1) NOT NULL DEFAULT 100,
  affection DECIMAL(5,1) NOT NULL DEFAULT 30,
  xp INT UNSIGNED NOT NULL DEFAULT 0,
  last_update DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (species_id) REFERENCES species(id),
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS friendships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requester_id INT UNSIGNED NOT NULL,
  addressee_id INT UNSIGNED NOT NULL,
  status ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  UNIQUE KEY pair (requester_id, addressee_id),
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pet_bonds (
  pet_a INT UNSIGNED NOT NULL,
  pet_b INT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 0,
  meetings INT UNSIGNED NOT NULL DEFAULT 0,
  last_meeting DATETIME NULL,
  PRIMARY KEY (pet_a, pet_b),
  FOREIGN KEY (pet_a) REFERENCES pets(id) ON DELETE CASCADE,
  FOREIGN KEY (pet_b) REFERENCES pets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  pet_id INT UNSIGNED NULL,
  message VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE SET NULL,
  INDEX (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v) VALUES
 ('site_name', 'Digitales Haustier'),
 ('max_pets', '5'),
 ('registration_open', '1'),
 ('playdate_cooldown_min', '30');

INSERT IGNORE INTO species (name, emoji, description, temperament, color, favorite_food, base_affection, food_decay, fun_decay, clean_decay, energy_decay) VALUES
 ('Katze', '🐱', 'Eigenwillig und stolz. Vertrauen muss man sich verdienen.', 'Eigenwillig', '#ffe0cc', 'Thunfisch', 15, 3.5, 3.0, 1.5, 3.0),
 ('Hund', '🐶', 'Treu und verspielt. Liebt jeden, der sich kümmert.', 'Treu', '#ffeab3', 'Knochen', 55, 4.5, 4.0, 2.5, 3.5),
 ('Hase', '🐰', 'Sanft und etwas schreckhaft.', 'Scheu', '#fde2f3', 'Karotte', 25, 4.0, 3.0, 2.0, 3.0),
 ('Hamster', '🐹', 'Kleiner Vorratsjäger, nachts aktiv.', 'Neugierig', '#fff1c9', 'Sonnenblumenkerne', 35, 4.0, 3.5, 2.0, 4.0),
 ('Fuchs', '🦊', 'Clever und misstrauisch gegenüber Fremden.', 'Misstrauisch', '#ffd9b8', 'Beeren', 10, 3.5, 3.5, 2.0, 3.0),
 ('Papagei', '🦜', 'Redselig und sozial, hasst Langeweile.', 'Gesellig', '#d4f5d0', 'Nüsse', 40, 3.0, 5.0, 1.5, 2.5),
 ('Schildkröte', '🐢', 'Gelassen und genügsam. Alles braucht Zeit.', 'Gelassen', '#d2f0e0', 'Salat', 30, 2.0, 2.0, 1.5, 2.0),
 ('Einhorn', '🦄', 'Magisch und sensibel, liebt Glitzer.', 'Sensibel', '#e8dcff', 'Regenbogenzucker', 35, 3.0, 4.0, 3.0, 3.0),
 ('Drache', '🐲', 'Stolz und feurig. Wer ihn zähmt, hat einen Freund fürs Leben.', 'Stolz', '#cdeccf', 'Feuerpaprika', 5, 5.0, 3.0, 2.0, 3.5),
 ('Pinguin', '🐧', 'Tollpatschig und freundlich.', 'Freundlich', '#d6ecff', 'Fisch', 45, 4.0, 3.0, 2.0, 3.0),
 ('Maus', '🐭', 'Winzig, flink und furchtsam.', 'Furchtsam', '#e9e9f2', 'Käse', 20, 4.5, 3.0, 2.5, 3.5),
 ('Eule', '🦉', 'Weise Nachtschwärmerin, beobachtet lieber.', 'Zurückhaltend', '#e5d9c8', 'Insekten', 20, 3.0, 2.5, 1.5, 2.5);

INSERT IGNORE INTO relations (species_a, species_b, affinity)
SELECT LEAST(a.id, b.id), GREATEST(a.id, b.id), r.v FROM (
  SELECT 'Katze' x, 'Hund' y, -2 v UNION ALL
  SELECT 'Katze','Maus',-2 UNION ALL SELECT 'Katze','Papagei',-1 UNION ALL SELECT 'Katze','Hamster',-1 UNION ALL
  SELECT 'Katze','Eule',0 UNION ALL SELECT 'Katze','Einhorn',1 UNION ALL
  SELECT 'Hund','Maus',-1 UNION ALL SELECT 'Hund','Hase',1 UNION ALL SELECT 'Hund','Pinguin',1 UNION ALL SELECT 'Hund','Fuchs',-1 UNION ALL
  SELECT 'Fuchs','Hase',-2 UNION ALL SELECT 'Fuchs','Hamster',-1 UNION ALL SELECT 'Fuchs','Papagei',-1 UNION ALL SELECT 'Fuchs','Maus',-2 UNION ALL
  SELECT 'Eule','Maus',-2 UNION ALL SELECT 'Eule','Hamster',-1 UNION ALL SELECT 'Eule','Hase',-1 UNION ALL
  SELECT 'Hase','Hamster',2 UNION ALL SELECT 'Hase','Schildkröte',2 UNION ALL SELECT 'Hase','Maus',1 UNION ALL
  SELECT 'Pinguin','Schildkröte',2 UNION ALL SELECT 'Pinguin','Einhorn',1 UNION ALL
  SELECT 'Einhorn','Drache',2 UNION ALL SELECT 'Einhorn','Hase',2 UNION ALL SELECT 'Einhorn','Schildkröte',1 UNION ALL
  SELECT 'Drache','Katze',-1 UNION ALL SELECT 'Drache','Hund',-1 UNION ALL SELECT 'Drache','Schildkröte',1 UNION ALL
  SELECT 'Papagei','Pinguin',1 UNION ALL SELECT 'Papagei','Hamster',1
) r JOIN species a ON a.name = r.x JOIN species b ON b.name = r.y;
