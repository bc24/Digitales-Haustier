<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$pets = user_pets((int)$u['id']);
$max = (int)setting('max_pets', '5');
$news = rows('SELECT a.*, p.name AS pet_name FROM activity a LEFT JOIN pets p ON p.id = a.pet_id WHERE a.user_id = ? ORDER BY a.id DESC LIMIT 10', [$u['id']]);
$requests = (int)val("SELECT COUNT(*) FROM friendships WHERE addressee_id=? AND status='pending'", [$u['id']]);
page_header('Meine Tiere', 'home');
?>
<h1>Hallo, <?= e($u['display_name'] ?: $u['username']) ?></h1>
<?php if ($requests): ?><div class="flash ok">Du hast <?= $requests ?> offene Freundschaftsanfrage(n). <a href="<?= e(url('friends.php')) ?>">Ansehen</a></div><?php endif; ?>
<h2>Meine Tiere (<?= count($pets) ?>/<?= $max ?>)</h2>
<div class="grid">
<?php foreach ($pets as $p) echo pet_card($p); ?>
<?php if (count($pets) < $max): ?>
  <a class="pet-card add" href="<?= e(url('adopt.php')) ?>"><div class="pet-emoji">＋</div><h3>Tier adoptieren</h3></a>
<?php endif; ?>
</div>
<h2>Neuigkeiten</h2>
<div class="card">
<?php if (!$news): ?><p class="muted">Noch nichts passiert.</p><?php endif; ?>
<ul class="feed"><?php foreach ($news as $n): ?><li><?= e($n['message']) ?> <span class="muted"><?= e(time_ago($n['created_at'])) ?></span></li><?php endforeach; ?></ul>
</div>
<?php page_footer();
