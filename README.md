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
