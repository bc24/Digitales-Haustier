<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
const GAME_INFO = [
    'catch'  => ['🧺', 'Leckerli-Fänger', 'Fange fallende Leckerli mit dem Korb. Goldene zählen dreifach, Steine kosten Punkte. 30 Sekunden.'],
    'memory' => ['🧠', 'Tier-Memory', 'Finde alle 8 Paare, so schnell und mit so wenig Zügen wie möglich.'],
    'whack'  => ['🔨', 'Tier-Tipp', 'Tippe die Tiere an, die aus den Löchern lugen. 30 Sekunden.'],
];
$week = (int)val('SELECT YEARWEEK(NOW(), 3)');
$cap = (int)setting('minigame_daily_cap', '200');
$earned = daily_get($uid, 'mg_coins');
page_header('Minispiele', 'games');
?>
<h1>Minispiele</h1>
<div class="card"><b>Münzen heute: <?= $earned ?> / <?= $cap ?></b><?= progress_bar($earned / max(1, $cap) * 100, 'b-fun') ?>
  <p class="muted">Nach dem Tageslimit kannst du weiter für die Wochenrangliste spielen, bekommst aber keine Münzen mehr.</p></div>
<div class="grid">
<?php foreach (GAME_INFO as $k => [$icon, $name, $desc]):
  $best = (int)val('SELECT MAX(score) FROM minigame_runs WHERE user_id=? AND game=? AND week=? AND finished=1', [$uid, $k, $week]);
  $top = rows('SELECT u.username, u.avatar, MAX(r.score) AS s FROM minigame_runs r JOIN users u ON u.id=r.user_id WHERE r.game=? AND r.week=? AND r.finished=1 AND u.is_banned=0 GROUP BY u.id ORDER BY s DESC LIMIT 3', [$k, $week]); ?>
  <div class="pet-card static"><div class="pet-emoji"><?= $icon ?></div><h3><?= e($name) ?></h3><p class="muted"><?= e($desc) ?></p>
    <p>Dein Wochenbestwert: <b><?= $best ?></b></p>
    <?php foreach ($top as $i => $t): ?><div class="muted" style="font-size:.85rem"><?= ['🥇', '🥈', '🥉'][$i] ?> <?= e($t['avatar'] . ' ' . $t['username']) ?>: <?= (int)$t['s'] ?></div><?php endforeach; ?>
    <a class="btn" style="margin-top:10px" href="<?= e(url('game.php?g=' . $k)) ?>">Spielen</a></div>
<?php endforeach; ?>
</div>
<p class="muted center">Die Wochenrangliste findest du unter <a href="<?= e(url('leaderboard.php?tab=catch')) ?>">Community → Ranglisten</a>.</p>
<?php page_footer();
