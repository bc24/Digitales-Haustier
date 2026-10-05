<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$g = $_GET['g'] ?? '';
$titles = ['catch' => 'Leckerli-Fänger', 'memory' => 'Tier-Memory', 'whack' => 'Tier-Tipp'];
if (!isset($titles[$g])) redirect('games.php');
$emojis = array_column(rows('SELECT emoji FROM species WHERE active=1 ORDER BY RAND() LIMIT 8'), 'emoji');
while (count($emojis) < 8) $emojis[] = ['🍎', '🍓', '🍪', '🍩', '🧸', '🎾', '⭐', '🍕'][count($emojis)];
page_header($titles[$g], 'games');
?>
<h1><?= e($titles[$g]) ?></h1>
<div class="game-wrap">
  <div class="game-hud"><span>Punkte: <b id="g-score">0</b></span><span id="g-extra"></span></div>
  <div class="game-box" id="g-box"><div class="overlay" id="g-over"><h2 style="margin:0"><?= e($titles[$g]) ?></h2>
    <p class="muted">Bereit?</p><button class="btn big" id="g-start">Los geht's</button></div></div>
  <a class="muted" href="<?= e(url('games.php')) ?>">Zurück zu den Minispielen</a>
</div>
<script>window.GAME = <?= json_encode(['game' => $g, 'csrf' => csrf_token(), 'api' => url('api/game.php'), 'emojis' => array_values($emojis)]) ?>;</script>
<script src="<?= e(url('assets/games.js')) ?>?v=<?= (int)@filemtime(ROOT . '/assets/games.js') ?>"></script>
<?php page_footer();
