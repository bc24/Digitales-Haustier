ALTER TABLE users
  ADD COLUMN coins INT UNSIGNED NOT NULL DEFAULT 150,
  ADD COLUMN xp INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN best_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN last_daily DATE NULL,
  ADD COLUMN trophies INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN extra_slots TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN last_seen DATETIME NULL;

ALTER TABLE species
  ADD COLUMN rarity ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
  ADD COLUMN price INT UNSIGNED NOT NULL DEFAULT 100;

UPDATE species SET rarity='rare', price=250 WHERE name IN ('Fuchs','Papagei','Schildkröte','Pinguin','Eule');
UPDATE species SET rarity='epic', price=600 WHERE name = 'Einhorn';
UPDATE species SET rarity='legendary', price=1500 WHERE name = 'Drache';

ALTER TABLE pets
  ADD COLUMN hue SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN hat_id INT UNSIGNED NULL,
  ADD COLUMN room_id INT UNSIGNED NULL;

CREATE TABLE items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('use','hat','room','egg','boost') NOT NULL,
  name VARCHAR(40) NOT NULL,
  emoji VARCHAR(16) NOT NULL DEFAULT '',
  description VARCHAR(160) NOT NULL DEFAULT '',
  price INT UNSIGNED NOT NULL DEFAULT 50,
  rarity ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
  color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
  food_val TINYINT NOT NULL DEFAULT 0,
  fun_val TINYINT NOT NULL DEFAULT 0,
  clean_val TINYINT NOT NULL DEFAULT 0,
  energy_val TINYINT NOT NULL DEFAULT 0,
  health_val TINYINT NOT NULL DEFAULT 0,
  aff_val TINYINT NOT NULL DEFAULT 0,
  hatch_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  weights VARCHAR(30) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory (
  user_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, item_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE eggs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  hatch_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE egg_warm (
  egg_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (egg_id, user_id),
  FOREIGN KEY (egg_id) REFERENCES eggs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stats (
  user_id INT UNSIGNED NOT NULL,
  k VARCHAR(30) NOT NULL,
  v BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, k),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE daily (
  user_id INT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  k VARCHAR(30) NOT NULL,
  v INT NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, day, k),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_achievements (
  user_id INT UNSIGNED NOT NULL,
  akey VARCHAR(30) NOT NULL,
  unlocked_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, akey),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE gifts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_id INT UNSIGNED NOT NULL,
  to_id INT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  claimed TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY (from_id, to_id, day),
  FOREIGN KEY (from_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (to_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE visits (
  pet_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  PRIMARY KEY (pet_id, user_id, day),
  FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE profile_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  body VARCHAR(300) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (profile_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (profile_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE minigame_runs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  game VARCHAR(20) NOT NULL,
  token CHAR(32) NOT NULL,
  started_at DATETIME NOT NULL,
  finished TINYINT(1) NOT NULL DEFAULT 0,
  score INT NOT NULL DEFAULT 0,
  week INT NOT NULL,
  UNIQUE KEY (token),
  INDEX (game, week, score),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO items (kind, name, emoji, description, price, rarity, color, food_val, fun_val, clean_val, energy_val, health_val, aff_val, hatch_minutes, weights, sort) VALUES
 ('use','Apfel','🍎','Knackig und gesund.',20,'common','#ffffff',20,0,0,0,0,1,0,'',10),
 ('use','Gourmet-Menü','🍱','Ein Festmahl für dein Tier.',60,'common','#ffffff',45,10,0,0,0,3,0,'',11),
 ('use','Torte','🍰','Süß und glücklich machend.',80,'common','#ffffff',25,25,0,0,0,4,0,'',12),
 ('use','Energy-Drink','⚡','Gibt sofort Energie.',50,'common','#ffffff',0,0,0,35,0,0,0,'',13),
 ('use','Vitamine','💊','Stärkt die Gesundheit.',70,'common','#ffffff',0,0,0,0,40,1,0,'',14),
 ('use','Shampoo','🧼','Macht richtig sauber.',30,'common','#ffffff',0,0,50,0,0,0,0,'',15),
 ('use','Plüschball','🧸','Spielspaß pur.',100,'common','#ffffff',0,35,0,0,0,3,0,'',16),
 ('use','Zauberkeks','🍪','Gewinnt jedes Herz.',150,'rare','#ffffff',5,10,0,0,0,10,0,'',17),
 ('hat','Schleife','🎀','Niedlich und schlicht.',150,'common','#ffffff',0,0,0,0,0,0,0,'',20),
 ('hat','Kappe','🧢','Cool durch den Tag.',180,'common','#ffffff',0,0,0,0,0,0,0,'',21),
 ('hat','Strohhut','👒','Für sonnige Tage.',220,'common','#ffffff',0,0,0,0,0,0,0,'',22),
 ('hat','Zylinder','🎩','Sehr elegant.',350,'rare','#ffffff',0,0,0,0,0,0,0,'',23),
 ('hat','Doktorhut','🎓','Für kluge Tiere.',450,'rare','#ffffff',0,0,0,0,0,0,0,'',24),
 ('hat','Krone','👑','Nur für Könige.',900,'epic','#ffffff',0,0,0,0,0,0,0,'',25),
 ('room','Wolken','☁️','Ein himmlisches Zuhause.',200,'common','#dff0ff',0,0,0,0,0,0,0,'',30),
 ('room','Candy-Land','🍭','Süßes Zimmer.',300,'common','#ffe0f0',0,0,0,0,0,0,0,'',31),
 ('room','Wald','🌲','Frische Waldluft.',300,'common','#d9efd5',0,0,0,0,0,0,0,'',32),
 ('room','Wüste','🏜️','Warm und golden.',400,'rare','#ffe9c7',0,0,0,0,0,0,0,'',33),
 ('room','Weltall','🪐','Unendliche Weiten.',700,'epic','#e3defe',0,0,0,0,0,0,0,'',34),
 ('egg','Standard-Ei','🥚','Brütet in 1 Stunde. Meist häufige Tiere.',200,'common','#ffffff',0,0,0,0,0,0,60,'80,18,2,0',40),
 ('egg','Glitzer-Ei','✨','Brütet in 4 Stunden. Gute Chance auf Seltenes.',600,'rare','#ffffff',0,0,0,0,0,0,240,'40,45,13,2',41),
 ('egg','Legendäres Ei','🌟','Brütet in 12 Stunden. Epische und legendäre Tiere.',2000,'legendary','#ffffff',0,0,0,0,0,0,720,'0,30,50,20',42),
 ('boost','Streak-Schutz','🛡️','Rettet deinen Tagesstreak, wenn du einen Tag verpasst.',120,'common','#ffffff',0,0,0,0,0,0,0,'',50),
 ('boost','Wärmelampe','🔥','Verkürzt die Brutzeit eines Eis um 60 Minuten.',100,'common','#ffffff',0,0,0,0,0,0,0,'',51),
 ('boost','Stall-Erweiterung','🏠','Ein zusätzlicher Platz für ein Tier (max. 5).',800,'rare','#ffffff',0,0,0,0,0,0,0,'',52);

INSERT IGNORE INTO settings (k, v) VALUES ('coin_multiplier', '1'), ('minigame_daily_cap', '200');
