<?php
// Wirtschaft & Fortschritt: Münzen, Level, Tageszähler, Quests, Erfolge, Inventar

const RARITY = ['common' => 'Gewöhnlich', 'rare' => 'Selten', 'epic' => 'Episch', 'legendary' => 'Legendär'];

const QUESTS = [
    'feed'     => ['Füttere deine Tiere', 3, 25, 10],
    'play'     => ['Spiele mit deinen Tieren', 3, 25, 10],
    'pet'      => ['Streichle deine Tiere', 4, 20, 8],
    'wash'     => ['Wasche ein Tier', 2, 20, 8],
    'minigame' => ['Spiele Minispiele', 2, 35, 15],
    'race'     => ['Tritt in der Arena an', 2, 35, 15],
    'playdate' => ['Organisiere ein Spieltreffen', 1, 30, 12],
    'gift'     => ['Schicke Freunden Geschenke', 1, 25, 10],
    'visit'    => ['Besuche Tiere von Freunden', 2, 30, 12],
];

const ACHIEVEMENTS = [
    'first_pet'  => ['🐣', 'Tierfreund', 'Adoptiere dein erstes Tier', 'adopt', 1, 50],
    'feed_50'    => ['🍖', 'Feinschmecker', 'Füttere 50-mal', 'feed', 50, 100],
    'play_50'    => ['🎾', 'Spielkamerad', 'Spiele 50-mal mit deinen Tieren', 'play', 50, 100],
    'pet_50'     => ['🤚', 'Kuschelprofi', 'Streichle 50-mal', 'pet', 50, 100],
    'friends_3'  => ['🤝', 'Gesellig', 'Habe 3 Freunde', 'friends', 3, 100],
    'friends_10' => ['🎉', 'Beliebt', 'Habe 10 Freunde', 'friends', 10, 300],
    'streak_7'   => ['🔥', 'Wochenstreak', '7 Tage in Folge einloggen', 'best_streak', 7, 150],
    'streak_30'  => ['🌋', 'Monatsstreak', '30 Tage in Folge einloggen', 'best_streak', 30, 600],
    'games_20'   => ['🎮', 'Zocker', 'Spiele 20 Minispiele', 'minigame', 20, 150],
    'wins_10'    => ['🏆', 'Champion', 'Gewinne 10 Arena-Kämpfe', 'race_win', 10, 250],
    'dates_10'   => ['💞', 'Kuppler', '10 Spieltreffen', 'playdate', 10, 150],
    'gifts_10'   => ['🎁', 'Spendabel', 'Verschenke 10 Geschenke', 'gift', 10, 150],
    'hatch_3'    => ['🏠', 'Tierretter', 'Hole 3 Tiere aus dem Tierheim ab', 'hatch', 3, 200],
    'species_6'  => ['📚', 'Sammler', 'Besitze 6 verschiedene Arten', 'species', 6, 300],
    'level_10'   => ['⭐', 'Erfahren', 'Erreiche Spielerlevel 10', 'level', 10, 400],
    'rich'       => ['💰', 'Reich', 'Verdiene insgesamt 5000 Münzen', 'coins_earned', 5000, 300],
];

function today(): string { return date('Y-m-d'); }

function user_level(int $xp): int { return (int)floor(sqrt($xp / 40)) + 1; }
function level_xp(int $lvl): int { return (int)(($lvl - 1) ** 2 * 40); }

// ---- Statistiken & Tageszähler ----
function stat_get(int $uid, string $k): int { return (int)val('SELECT v FROM stats WHERE user_id=? AND k=?', [$uid, $k]); }
function stat_add(int $uid, string $k, int $n = 1): void {
    q('INSERT INTO stats (user_id, k, v) VALUES (?,?,?) ON DUPLICATE KEY UPDATE v = v + VALUES(v)', [$uid, $k, $n]);
}
function stat_max(int $uid, string $k, int $v): void {
    q('INSERT INTO stats (user_id, k, v) VALUES (?,?,?) ON DUPLICATE KEY UPDATE v = GREATEST(v, VALUES(v))', [$uid, $k, $v]);
}
function daily_get(int $uid, string $k): int { return (int)val('SELECT v FROM daily WHERE user_id=? AND day=? AND k=?', [$uid, today(), $k]); }
function daily_add(int $uid, string $k, int $n = 1): void {
    q('INSERT INTO daily (user_id, day, k, v) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE v = v + VALUES(v)', [$uid, today(), $k, $n]);
    if (random_int(1, 100) === 1) q('DELETE FROM daily WHERE day < (CURDATE() - INTERVAL 7 DAY)');
}

// Ereignis erfassen: Lebenszeit-Statistik + Tageszähler + Erfolge prüfen
function track(int $uid, string $key, int $n = 1): void {
    stat_add($uid, $key, $n);
    daily_add($uid, $key, $n);
    check_achievements($uid);
}

function achievement_value(int $uid, string $stat): int {
    if ($stat === 'level') return user_level((int)val('SELECT xp FROM users WHERE id=?', [$uid]));
    if ($stat === 'species') return (int)val('SELECT COUNT(DISTINCT species_id) FROM pets WHERE user_id=?', [$uid]);
    if ($stat === 'friends') return (int)val("SELECT COUNT(*) FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?)", [$uid, $uid]);
    if ($stat === 'best_streak') return (int)val('SELECT best_streak FROM users WHERE id=?', [$uid]);
    return stat_get($uid, $stat);
}

function check_achievements(int $uid): void {
    $have = array_column(rows('SELECT akey FROM user_achievements WHERE user_id=?', [$uid]), 'akey');
    foreach (ACHIEVEMENTS as $key => [$icon, $name, $desc, $stat, $target, $coins]) {
        if (in_array($key, $have, true)) continue;
        if (achievement_value($uid, $stat) < $target) continue;
        if (q('INSERT IGNORE INTO user_achievements (user_id, akey, unlocked_at) VALUES (?,?,NOW())', [$uid, $key])->rowCount() === 1) {
            q('UPDATE users SET coins = coins + ? WHERE id=?', [$coins, $uid]);
            log_activity($uid, null, "Erfolg freigeschaltet: $icon $name (+$coins Münzen)");
        }
    }
}

// ---- Münzen & XP ----
function coin_mult(): float { return max(0.1, min(10, (float)setting('coin_multiplier', '1'))); }

function award(int $uid, int $coins = 0, int $xp = 0): int {
    if ($coins > 0) $coins = max(1, (int)round($coins * coin_mult()));
    $oldXp = (int)val('SELECT xp FROM users WHERE id=?', [$uid]);
    q('UPDATE users SET coins = coins + ?, xp = xp + ? WHERE id=?', [$coins, $xp, $uid]);
    if ($coins > 0) stat_add($uid, 'coins_earned', $coins);
    $o = user_level($oldXp); $n = user_level($oldXp + $xp);
    if ($n > $o) {
        $bonus = 25 * $n;
        q('UPDATE users SET coins = coins + ? WHERE id=?', [$bonus, $uid]);
        log_activity($uid, null, "Level-Up! Du bist jetzt Spielerlevel $n (+$bonus Münzen).");
        check_achievements($uid);
    }
    return $coins;
}

function spend(int $uid, int $amount): bool {
    return q('UPDATE users SET coins = coins - ? WHERE id = ? AND coins >= ?', [$amount, $uid, $amount])->rowCount() === 1;
}

// Belohnung für Pflege-Aktionen, mit Tageslimit gegen Münz-Farming
function care_reward(int $uid): void {
    $cap = 80;
    $got = daily_get($uid, 'care_coins');
    if ($got < $cap) { $c = award($uid, 2, 3); daily_add($uid, 'care_coins', $c); }
    else award($uid, 0, 1);
}

// ---- Inventar ----
function add_item(int $uid, int $itemId, int $qty = 1): void {
    q('INSERT INTO inventory (user_id, item_id, qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$uid, $itemId, $qty]);
}
function take_item(int $uid, int $itemId): bool {
    return q('UPDATE inventory SET qty = qty - 1 WHERE user_id=? AND item_id=? AND qty > 0', [$uid, $itemId])->rowCount() === 1;
}
function item_qty(int $uid, int $itemId): int { return (int)val('SELECT qty FROM inventory WHERE user_id=? AND item_id=?', [$uid, $itemId]); }
function item_by_name(string $name): ?array { return row('SELECT * FROM items WHERE name=?', [$name]); }

function give_egg(int $uid, int $itemId): bool {
    $it = row("SELECT * FROM items WHERE id=? AND kind='egg'", [$itemId]);
    if (!$it) return false;
    q('INSERT INTO eggs (user_id, item_id, hatch_at, created_at) VALUES (?,?, NOW() + INTERVAL ? MINUTE, NOW())', [$uid, $itemId, (int)$it['hatch_minutes']]);
    return true;
}

function max_pets_for(array $u): int { return (int)setting('max_pets', '5') + (int)$u['extra_slots']; }

// ---- Quests ----
function quests_for(int $uid): array {
    $keys = array_keys(QUESTS);
    usort($keys, fn($a, $b) => crc32($uid . today() . $a) <=> crc32($uid . today() . $b));
    $out = [];
    foreach (array_slice($keys, 0, 3) as $k) {
        [$label, $target, $coins, $xp] = QUESTS[$k];
        $out[] = ['key' => $k, 'label' => "$label ($target)", 'target' => $target, 'coins' => $coins, 'xp' => $xp,
                  'progress' => min($target, daily_get($uid, $k)), 'claimed' => daily_get($uid, 'claimed_' . $k) > 0];
    }
    return $out;
}

// ---- Fortschrittsanzeige ----
function xp_progress(array $u): array {
    $lvl = user_level((int)$u['xp']);
    $from = level_xp($lvl); $to = level_xp($lvl + 1);
    return [$lvl, (int)$u['xp'] - $from, $to - $from, round(((int)$u['xp'] - $from) / max(1, $to - $from) * 100)];
}

function rarity_chip(string $r): string {
    return '<span class="chip r-' . e($r) . '">' . e(RARITY[$r] ?? $r) . '</span>';
}

// Zufallsart anhand Gewichten (gewöhnlich, selten, episch, legendär)
function roll_species(string $weights): ?array {
    $w = array_map('intval', explode(',', $weights . ',0,0,0,0'));
    $names = ['common', 'rare', 'epic', 'legendary'];
    $total = array_sum(array_slice($w, 0, 4));
    if ($total <= 0) $w = [1, 0, 0, 0];
    $roll = random_int(1, max(1, array_sum(array_slice($w, 0, 4))));
    $r = 'common';
    foreach ($names as $i => $n) { if ($roll <= $w[$i]) { $r = $n; break; } $roll -= $w[$i]; }
    $s = row('SELECT * FROM species WHERE active=1 AND rarity=? ORDER BY RAND() LIMIT 1', [$r]);
    return $s ?: row('SELECT * FROM species WHERE active=1 ORDER BY RAND() LIMIT 1');
}
function roll_hue(): int { return random_int(1, 100) <= 10 ? [60, 120, 180, 240, 300][random_int(0, 4)] : 0; }

function create_pet(int $uid, array $species, string $name, int $hue = 0): int {
    q('INSERT INTO pets (user_id, species_id, name, affection, hue, last_update, created_at) VALUES (?,?,?,?,?,NOW(),NOW())',
      [$uid, $species['id'], $name, $species['base_affection'], $hue]);
    $id = insert_id();
    track($uid, 'adopt');
    return $id;
}
