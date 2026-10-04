# Digitales Haustier

Tamagotchi-artiges Haustier-Spiel in PHP (8.1+) und MySQL/MariaDB.

## Installation
1. Dateien auf einen PHP-Webserver kopieren (Apache/Nginx oder `php -S localhost:8080`).
2. `install.php` im Browser öffnen: Datenbank-Zugang und Admin-Konto eintragen.
3. `install.php` danach löschen. Der Admin-Bereich liegt unter `/admin/`.

## Funktionen
- 12 Tierarten, Füttern, Spielen, Streicheln, Waschen, Pflegen, Schlafen; Werte sinken über die Zeit
- Zuneigung: misstrauische Arten weichen anfangs aus, mit Pflege und Futter mögen sie dich
- Öffentliche Profile, Freundschaftssystem (Anfrage, Annahme, Entfernen)
- Spieltreffen: Ergebnis hängt von Artenverhältnis (-2 bis +2), Zuneigung, Pflegezustand und Vorgeschichte ab
- Admin: Benutzer, Tiere, Arten, Artenbeziehungen, Einstellungen

## Sicherheit
- Login-Bremse (5 Fehlversuche pro Konto / 20 pro IP in 15 Min.), Registrierungs-Limit (5 pro IP und Stunde)
- Passwort vergessen per E-Mail-Link (1 Std. gültig, einmalig). Benötigt funktionierendes `mail()` auf dem Server;
  im Admin unter Einstellungen die Seiten-URL und Absender-E-Mail setzen.
- Abmelden per POST mit CSRF-Schutz
- Hinter einem Reverse-Proxy ist `REMOTE_ADDR` evtl. die Proxy-IP, dann greift die IP-Bremse für alle gemeinsam.
