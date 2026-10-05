# Digitales Haustier

Tamagotchi-artiges Haustier-Spiel in PHP (8.1+) und MySQL/MariaDB.

## Installation
1. Dateien auf einen PHP-Webserver kopieren (Apache/Nginx oder `php -S localhost:8080`).
2. `install.php` im Browser öffnen: Datenbank-Zugang und Admin-Konto eintragen.
3. `install.php` danach löschen. Der Admin-Bereich liegt unter `/admin/`.

## Funktionen
**Spielschleife**
- Pflege (füttern, spielen, streicheln, waschen, pflegen, schlafen) bringt Münzen, XP und Zuneigung (mit Tageslimit)
- Spielerlevel mit Level-Up-Bonus, Tier-Entwicklungsstufen (Baby, Jungtier, Erwachsen, Meister)
- Tagesbonus mit Serie (Streak, Wochentruhe, Streak-Schutz), tägliches Glücksrad, 3 Tagesquests plus Tagestruhe, 16 Erfolge
- Shop: Futter und Pflege, Kopfschmuck, Zimmer, Tierheim-Anträge, Spezial; Rucksack
- Tierheim: Vermittlungsanträge mit Wartezeit, Seltenheit (gewöhnlich bis legendär) und seltenen Schillerfarben; seltene Arten gibt es nur über das Tierheim
- 3 Minispiele (Leckerli-Fänger, Tier-Memory, Tier-Tipp) mit serverseitiger Prüfung, Tageslimit für Münzen und Wochen-Highscores
- Arena (Trophäen), Freundschaftsduelle, Ranglisten (alle / nur Freunde)

**Mit Freunden**
- Freunde, Online-Status, Pinnwand, tägliche Geschenke, Besuche (Tiere streicheln), Fürsprache für Vermittlungen, Spieltreffen, Duelle

**Technik**
- Datenbank-Migrationen laufen automatisch (`migrations/`), bestehende Installationen werden beim ersten Aufruf aktualisiert
- Admin: Münz-Multiplikator für Events, Minispiel-Limit, Shop-Items, Arten mit Seltenheit und Preis, Münzen vergeben

**Basis**
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
