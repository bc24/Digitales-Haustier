<?php
// Spiellogik: Zerfall über die Zeit, Aktionen, Stimmung, Tier-Beziehungen

const FOODS = [
    'normal'   => ['label' => 'Futter',  'food' => 30, 'fun' => 0,  'aff' => 1, 'max_food' => 92],
    'treat'    => ['label' => 'Leckerli', 'food' => 10, 'fun' => 10, 'aff' => 2, 'max_food' => 90],
    'favorite' => ['label' => 'Lieblingsfutter', 'food' => 30, 'fun' => 5, 'aff' => 5, 'max_food' => 92],
];

function get_pet(int $id): ?array {
    $p = row('SELECT p.*, s.name AS species, s.emoji, s.color, s.temperament, s.favorite_food,
                     s.rarity, s.food_decay, s.fun_decay, s.clean_decay, s.energy_decay, u.username, u.display_name AS owner_name,
                     h.emoji AS hat, r.color AS room_color, r.name AS room_name
              FROM pets p JOIN species s ON s.id = p.species_id JOIN users u ON u.id = p.user_id
              LEFT JOIN items h ON h.id = p.hat_id LEFT JOIN items r ON r.id = p.room_id WHERE p.id = ?', [$id]);
    return $p ? apply_decay($p) : null;
}

function apply_decay(array $p): array {
    $hours = min(48, max(0, (time() - strtotime($p['last_update'])) / 3600));
    if ($hours < 0.02) return $p;
    $p['food']   = clamp($p['food']   - $p['food_decay']   * $hours);
    $p['fun']    = clamp($p['fun']    - $p['fun_decay']    * $hours);
    $p['clean']  = clamp($p['clean']  - $p['clean_decay']  * $hours);
    $p['energy'] = clamp($p['energy'] - $p['energy_decay'] * $hours);
    // Gesundheit sinkt bei Vernachlässigung, erholt sich sonst langsam
    $worst = min($p['food'], $p['clean']);
    $p['health'] = clamp($p['health'] + ($worst < 20 ? -3 : ($worst > 50 ? 1.5 : 0)) * $hours);
    // Zuneigung schwindet bei dauerhafter Vernachlässigung
    if (($p['food'] + $p['fun'] + $p['clean']) / 3 < 25) $p['affection'] = clamp($p['affection'] - 0.7 * $hours);
    $p['last_update'] = date('Y-m-d H:i:s');
    save_pet($p);
    return $p;
}

function save_pet(array $p): void {
    q('UPDATE pets SET food=?, fun=?, clean=?, energy=?, health=?, affection=?, xp=?, last_update=? WHERE id=?',
      [$p['food'], $p['fun'], $p['clean'], $p['energy'], $p['health'], $p['affection'], $p['xp'], date('Y-m-d H:i:s'), $p['id']]);
}

function pet_level(array $p): int { return (int)floor(sqrt($p['xp'] / 10)) + 1; }

// Entwicklungsstufe nach Level: [Name, Skalierung]
function pet_stage(int $lvl): array {
    if ($lvl >= 16) return ['Meister', 1.0];
    if ($lvl >= 9)  return ['Erwachsen', 0.92];
    if ($lvl >= 4)  return ['Jungtier', 0.78];
    return ['Baby', 0.62];
}

// Tier als HTML (Emoji mit Farbvariante, Hut und Entwicklungsstufe)
function pet_visual(array $p, string $cls = '', bool $stage = true): string {
    $scale = $stage ? pet_stage(pet_level($p))[1] : 1;
    $style = 'font-size:' . round($scale, 2) . 'em;' . ((int)$p['hue'] ? 'filter:hue-rotate(' . (int)$p['hue'] . 'deg) drop-shadow(0 8px 8px rgba(0,0,0,.15));' : '');
    return '<span class="pv ' . e($cls) . '"><span class="pv-body" style="' . $style . '">' . e($p['emoji']) . '</span>'
         . (!empty($p['hat']) ? '<span class="pv-hat">' . e($p['hat']) . '</span>' : '') . '</span>';
}

function pet_mood(array $p): array {
    $avg = ($p['food'] + $p['fun'] + $p['clean'] + $p['energy'] + $p['health']) / 5;
    if ($p['health'] < 30)  return ['krank', '🤒', 'sick'];
    if ($p['energy'] < 15)  return ['todmüde', '😴', 'sleepy'];
    if ($p['food'] < 20)    return ['hungrig', '😫', 'sad'];
    if ($avg >= 75 && $p['affection'] >= 50) return ['überglücklich', '🥰', 'happy'];
    if ($avg >= 60)         return ['zufrieden', '😊', 'happy'];
    if ($avg >= 40)         return ['gelangweilt', '😐', 'meh'];
    return ['traurig', '😢', 'sad'];
}

function affection_label(float $a): string {
    if ($a < 15) return 'Misstrauisch';
    if ($a < 35) return 'Zurückhaltend';
    if ($a < 55) return 'Vertraut dir';
    if ($a < 80) return 'Mag dich sehr';
    return 'Liebt dich';
}

function pet_say(array $p): string {
    [, , $cls] = pet_mood($p);
    if ($p['affection'] < 15 && $cls !== 'sad') return 'Hmpf. Ich beobachte dich erstmal...';
    return match ($cls) {
        'sick' => 'Mir geht es gar nicht gut...',
        'sleepy' => 'Gähn... ich brauche Schlaf.',
        'sad' => $p['food'] < 20 ? 'Mein Bauch knurrt!' : 'Ich fühle mich einsam.',
        'meh' => 'Hast du Lust auf ein Spiel?',
        default => $p['affection'] >= 55 ? 'Du bist mein Lieblingsmensch!' : 'Mir geht es gut!',
    };
}

function log_activity(int $userId, ?int $petId, string $msg): void {
    q('INSERT INTO activity (user_id, pet_id, message, created_at) VALUES (?,?,?,NOW())', [$userId, $petId, mb_substr($msg, 0, 255)]);
}

// Aktion ausführen. Gibt [Erfolg, Nachricht] zurück.
function pet_action(array &$p, string $action, ?string $food = null): array {
    $name = $p['name'];
    // Misstrauische Tiere verweigern manchmal – Beharrlichkeit zahlt sich aus
    if ($p['affection'] < 20 && $action !== 'sleep' && random_int(1, 100) <= (20 - $p['affection']) * 3) {
        $p['affection'] = clamp($p['affection'] + 1);
        $p['xp'] += 1;
        save_pet($p);
        return [false, "$name ist noch misstrauisch und weicht aus. Bleib dran, es mag dich bald mehr."];
    }
    switch ($action) {
        case 'feed':
            $f = FOODS[$food] ?? null;
            if (!$f) return [false, 'Unbekanntes Futter.'];
            if ($p['food'] > $f['max_food']) return [false, "$name ist schon satt."];
            $p['food'] = clamp($p['food'] + $f['food']);
            $p['fun'] = clamp($p['fun'] + $f['fun']);
            $p['affection'] = clamp($p['affection'] + $f['aff']);
            $label = $food === 'favorite' ? $p['favorite_food'] : $f['label'];
            $msg = "$name hat $label gefressen.";
            break;
        case 'play':
            if ($p['energy'] < 15) return [false, "$name ist zu müde zum Spielen."];
            $p['fun'] = clamp($p['fun'] + 25);
            $p['energy'] = clamp($p['energy'] - 15);
            $p['food'] = clamp($p['food'] - 5);
            $p['affection'] = clamp($p['affection'] + 2);
            $msg = "Du hast mit $name gespielt.";
            break;
        case 'pet':
            $p['affection'] = clamp($p['affection'] + 3);
            $p['fun'] = clamp($p['fun'] + 5);
            $msg = "$name genießt das Streicheln.";
            break;
        case 'wash':
            if ($p['clean'] > 90) return [false, "$name ist schon sauber."];
            $p['clean'] = clamp($p['clean'] + 35);
            $p['affection'] = clamp($p['affection'] + 1);
            $msg = "$name wurde gewaschen.";
            break;
        case 'heal':
            if ($p['health'] > 85) return [false, "$name ist gesund."];
            $p['health'] = clamp($p['health'] + 25);
            $p['affection'] = clamp($p['affection'] + 2);
            $msg = "$name wurde versorgt und fühlt sich besser.";
            break;
        case 'sleep':
            if ($p['energy'] > 85) return [false, "$name ist hellwach."];
            $p['energy'] = clamp($p['energy'] + 40);
            $p['fun'] = clamp($p['fun'] - 5);
            $msg = "$name hat ein Nickerchen gemacht.";
            break;
        default:
            return [false, 'Unbekannte Aktion.'];
    }
    $p['xp'] += 5;
    save_pet($p);
    log_activity((int)$p['user_id'], (int)$p['id'], $msg);
    track((int)$p['user_id'], $action);
    care_reward((int)$p['user_id']);
    return [true, $msg];
}

// Verträglichkeit zweier Arten: -2 (Erzfeinde) bis +2 (beste Freunde)
function species_affinity(int $a, int $b): int {
    if ($a === $b) return 1;
    $v = val('SELECT affinity FROM relations WHERE species_a = ? AND species_b = ?', [min($a, $b), max($a, $b)]);
    return $v === null ? 0 : (int)$v;
}
function affinity_label(int $v): string {
    return [-2 => 'Erzfeinde', -1 => 'Verstehen sich schlecht', 0 => 'Neutral', 1 => 'Mögen sich', 2 => 'Beste Freunde'][$v] ?? 'Neutral';
}

function get_bond(int $a, int $b): array {
    $r = row('SELECT * FROM pet_bonds WHERE pet_a = ? AND pet_b = ?', [min($a, $b), max($a, $b)]);
    return $r ?: ['score' => 0, 'meetings' => 0, 'last_meeting' => null];
}
function bond_label(int $score): string {
    if ($score <= -15) return 'Verfeindet';
    if ($score < 10)  return 'Bekannt';
    if ($score < 40)  return 'Freunde';
    return 'Beste Freunde';
}

// Spieltreffen zweier Tiere
function playdate(array &$a, array &$b): array {
    $cool = (int)setting('playdate_cooldown_min', '30');
    $bond = get_bond((int)$a['id'], (int)$b['id']);
    if ($bond['last_meeting'] && time() - strtotime($bond['last_meeting']) < $cool * 60) {
        $rest = (int)ceil(($cool * 60 - (time() - strtotime($bond['last_meeting']))) / 60);
        return [false, "Die beiden brauchen noch $rest Min. Pause.", null];
    }
    foreach ([$a, $b] as $t) {
        if ($t['energy'] < 10) return [false, $t['name'] . ' ist zu müde für ein Treffen.', null];
    }
    $aff = species_affinity((int)$a['species_id'], (int)$b['species_id']);
    $score = $aff * 15
        + ($a['affection'] + $b['affection']) / 10
        + (($a['food'] + $a['fun'] + $a['clean'] + $b['food'] + $b['fun'] + $b['clean']) / 6 - 50) / 5
        + $bond['score'] / 5
        + random_int(-10, 10);
    if ($score >= 22)      { $res = 'great';    $d = 10;  $fun = 20;  $msg = "{$a['name']} und {$b['name']} hatten einen tollen Spieltag!"; }
    elseif ($score >= 5)   { $res = 'ok';       $d = 4;   $fun = 10;  $msg = "{$a['name']} und {$b['name']} haben vorsichtig miteinander gespielt."; }
    else                   { $res = 'conflict'; $d = -6;  $fun = -10; $msg = "{$a['name']} und {$b['name']} haben sich gezankt."; }
    foreach ([&$a, &$b] as &$t) {
        $t['fun'] = clamp($t['fun'] + $fun);
        $t['energy'] = clamp($t['energy'] - 10);
        $t['xp'] += 8;
        save_pet($t);
    }
    unset($t);
    q('INSERT INTO pet_bonds (pet_a, pet_b, score, meetings, last_meeting) VALUES (?,?,?,1,NOW())
       ON DUPLICATE KEY UPDATE score = score + VALUES(score), meetings = meetings + 1, last_meeting = NOW()',
      [min($a['id'], $b['id']), max($a['id'], $b['id']), $d]);
    log_activity((int)$a['user_id'], (int)$a['id'], $msg);
    if ($b['user_id'] !== $a['user_id']) {
        log_activity((int)$b['user_id'], (int)$b['id'], $msg);
        award((int)$b['user_id'], 5, 4);
    }
    track((int)$a['user_id'], 'playdate');
    award((int)$a['user_id'], ['great' => 15, 'ok' => 8, 'conflict' => 3][$res], 8);
    return [true, $msg, $res];
}

function mood_bar(string $label, float $v, string $cls): string {
    return '<div class="stat"><span>' . e($label) . '</span><div class="bar"><i class="' . $cls . '" style="width:' . round($v) . '%"></i></div><b>' . round($v) . '</b></div>';
}

function are_friends(int $a, int $b): bool {
    return (bool)val("SELECT 1 FROM friendships WHERE status='accepted' AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))", [$a, $b, $b, $a]);
}

function user_pets(int $uid): array {
    $ids = array_column(rows('SELECT id FROM pets WHERE user_id = ? ORDER BY id', [$uid]), 'id');
    return array_values(array_filter(array_map('get_pet', $ids)));
}


// Item aus dem Rucksack an einem Tier verwenden
function use_item_on_pet(array &$p, array $item): array {
    $name = $p['name'];
    if (!take_item((int)$p['user_id'], (int)$item['id'])) return [false, 'Du besitzt dieses Item nicht (mehr).'];
    $p['food'] = clamp($p['food'] + $item['food_val']);
    $p['fun'] = clamp($p['fun'] + $item['fun_val']);
    $p['clean'] = clamp($p['clean'] + $item['clean_val']);
    $p['energy'] = clamp($p['energy'] + $item['energy_val']);
    $p['health'] = clamp($p['health'] + $item['health_val']);
    $p['affection'] = clamp($p['affection'] + $item['aff_val']);
    $p['xp'] += 8;
    save_pet($p);
    $msg = "$name hat {$item['emoji']} {$item['name']} bekommen.";
    log_activity((int)$p['user_id'], (int)$p['id'], $msg);
    award((int)$p['user_id'], 0, 4);
    return [true, $msg];
}
