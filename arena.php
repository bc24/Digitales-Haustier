<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
const RBONUS = ['common' => 0, 'rare' => 3, 'epic' => 6, 'legendary' => 10];
const DAILY_FIGHTS = 8;

function pet_power(array $p): float {
    return pet_level($p) * 4 + ($p['food'] + $p['fun'] + $p['clean']) / 6 + $p['energy'] * 0.25 + $p['health'] * 0.2 + $p['affection'] * 0.15 + RBONUS[$p['rarity']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $a = get_pet((int)($_POST['mine'] ?? 0));
    $b = get_pet((int)($_POST['opp'] ?? 0));
    $err = null;
    if (!$a || (int)$a['user_id'] !== $uid) $err = 'Wähle eines deiner Tiere.';
    elseif (!$b || (int)$b['user_id'] === $uid) $err = 'Ungültiger Gegner.';
    elseif (daily_get($uid, 'race') >= DAILY_FIGHTS) $err = 'Du hast heute alle ' . DAILY_FIGHTS . ' Kämpfe verbraucht.';
    elseif ($a['energy'] < 20) $err = $a['name'] . ' ist zu müde (mind. 20 Energie nötig).';
    elseif ($a['health'] < 20) $err = $a['name'] . ' ist zu krank für einen Kampf.';
    $friendly = !$err && are_friends($uid, (int)$b['user_id']);
    if (!$err && !$friendly && daily_get($uid, 'vs' . $b['user_id']) >= 2) $err = 'Gegen diesen Spieler hast du heute schon zweimal gekämpft.';
    if ($err) { flash($err, 'err'); redirect('arena.php?pet=' . (int)($_POST['mine'] ?? 0)); }

    $pa = pet_power($a) + random_int(0, 30);
    $pb = pet_power($b) + random_int(0, 30);
    $win = $pa > $pb;
    $a['energy'] = clamp($a['energy'] - 20);
    $a['fun'] = clamp($a['fun'] + ($win ? 5 : -5));
    $a['xp'] += $win ? 10 : 4;
    save_pet($a);
    daily_add($uid, 'race');
    if ($win) track($uid, 'race_win');
    if ($friendly) {
        $c = award($uid, $win ? 12 : 5, $win ? 8 : 4); $tr = 0;
    } else {
        daily_add($uid, 'vs' . $b['user_id']);
        $diff = pet_level($b) - pet_level($a);
        $tr = $win ? max(8, min(30, 15 + $diff * 2)) : -max(5, min(15, 10 - $diff));
        if ($tr >= 0) q('UPDATE users SET trophies = trophies + ? WHERE id=?', [$tr, $uid]);
        else q('UPDATE users SET trophies = IF(trophies > ?, trophies - ?, 0) WHERE id=?', [-$tr, -$tr, $uid]);
        $c = award($uid, $win ? 30 : 8, $win ? 15 : 6);
    }
    $verb = $win ? 'gewonnen' : 'verloren';
    log_activity($uid, (int)$a['id'], "{$a['name']} hat in der Arena gegen {$b['name']} $verb.");
    log_activity((int)$b['user_id'], (int)$b['id'], "{$b['name']} wurde von {$u['username']}s {$a['name']} " . ($friendly ? 'zum Freundschaftsduell' : 'in der Arena') . ' herausgefordert und hat ' . ($win ? 'verloren' : 'gewonnen') . '.');
    $_SESSION['battle'] = ['a' => $a, 'b' => $b, 'pa' => round($pa), 'pb' => round($pb), 'win' => $win, 'tr' => $tr, 'coins' => $c, 'friendly' => $friendly];
    track($uid, 'race');
    redirect('arena.php?pet=' . $a['id']);
}

$mine = user_pets($uid);
$selId = (int)($_GET['pet'] ?? ($mine[0]['id'] ?? 0));
$sel = null; foreach ($mine as $m) if ((int)$m['id'] === $selId) $sel = $m;
$sel = $sel ?: ($mine[0] ?? null);
$battle = $_SESSION['battle'] ?? null; unset($_SESSION['battle']);
$left = DAILY_FIGHTS - daily_get($uid, 'race');
$opps = []; $friendPets = [];
if ($sel) {
    $lv = pet_level($sel);
    $ids = array_column(rows("SELECT p.id FROM pets p JOIN users us ON us.id=p.user_id WHERE p.user_id <> ? AND us.is_banned=0
        AND p.user_id NOT IN (SELECT IF(requester_id=?, addressee_id, requester_id) FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?))
        AND FLOOR(SQRT(p.xp/10))+1 BETWEEN ? AND ? ORDER BY RAND() LIMIT 4", [$uid, $uid, $uid, $uid, $lv - 4, $lv + 4]), 'id');
    $opps = array_values(array_filter(array_map('get_pet', $ids)));
    $fids = array_column(rows("SELECT p.id FROM pets p WHERE p.user_id IN (SELECT IF(requester_id=?, addressee_id, requester_id) FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?)) LIMIT 12", [$uid, $uid, $uid]), 'id');
    $friendPets = array_values(array_filter(array_map('get_pet', $fids)));
}
page_header('Arena', 'arena');
?>
<h1>Arena <span class="pill lvl" style="font-size:1rem">🏆 <?= (int)$u['trophies'] ?></span></h1>
<p class="muted">Kämpfe heute: noch <b><?= max(0, $left) ?></b> von <?= DAILY_FIGHTS ?>. Stärke hängt von Level, Pflegezustand, Zuneigung und Seltenheit ab, dazu kommt Glück. Ein Kampf kostet 20 Energie.</p>
<?php if ($battle): $ba = $battle['a']; $bb = $battle['b']; ?>
  <div class="card center"><div class="battle"><div><?= pet_visual($ba) ?><h3><?= e($ba['name']) ?></h3><b><?= $battle['pa'] ?></b></div><div style="font-size:2rem">⚔️</div><div><?= pet_visual($bb) ?><h3><?= e($bb['name']) ?></h3><b><?= $battle['pb'] ?></b></div></div>
    <h2 style="margin-bottom:4px"><?= $battle['win'] ? '🎉 Sieg!' : '😢 Niederlage' ?></h2>
    <p><?= $battle['friendly'] ? 'Freundschaftsduell (keine Trophäen)' : 'Trophäen: ' . ($battle['tr'] >= 0 ? '+' : '') . $battle['tr'] ?> · +<?= $battle['coins'] ?> Münzen</p></div>
<?php endif; ?>
<?php if (!$mine): ?><div class="flash err">Du brauchst zuerst ein Tier.</div><?php else: ?>
<div class="tabs"><?php foreach ($mine as $m): ?><a class="<?= $sel && (int)$m['id'] === (int)$sel['id'] ? 'on' : '' ?>" href="?pet=<?= $m['id'] ?>"><?= e($m['emoji'] . ' ' . $m['name']) ?></a><?php endforeach; ?></div>
<div class="card"><b><?= e($sel['name']) ?></b> · Stärke ca. <b><?= round(pet_power($sel)) ?></b> · Energie <?= round($sel['energy']) ?> · Gesundheit <?= round($sel['health']) ?></div>
<h2>Arena-Gegner</h2>
<div class="grid">
<?php foreach ($opps as $o): ?>
  <div class="pet-card static" style="--c:<?= e($o['color']) ?>"><div class="pet-emoji"><?= pet_visual($o) ?></div><h3><?= e($o['name']) ?></h3>
    <p class="muted"><?= e($o['species']) ?> · Lv <?= pet_level($o) ?> · @<?= e($o['username']) ?><br>Stärke ca. <?= round(pet_power($o)) ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="mine" value="<?= $sel['id'] ?>"><input type="hidden" name="opp" value="<?= $o['id'] ?>"><button class="btn small">⚔️ Herausfordern</button></form></div>
<?php endforeach; ?>
<?php if (!$opps): ?><p class="muted">Gerade keine passenden Gegner. Schau später wieder vorbei.</p><?php endif; ?>
</div>
<h2>Freundschaftsduelle</h2>
<div class="grid small">
<?php foreach ($friendPets as $o): ?>
  <div class="pet-card static" style="--c:<?= e($o['color']) ?>"><div class="pet-emoji"><?= pet_visual($o) ?></div><h3><?= e($o['name']) ?></h3><p class="muted">@<?= e($o['username']) ?> · Lv <?= pet_level($o) ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="mine" value="<?= $sel['id'] ?>"><input type="hidden" name="opp" value="<?= $o['id'] ?>"><button class="btn small ghost">Duell</button></form></div>
<?php endforeach; ?>
<?php if (!$friendPets): ?><p class="muted">Deine Freunde haben noch keine Tiere.</p><?php endif; ?>
</div>
<?php endif;
page_footer();
