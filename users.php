<?php
require __DIR__ . '/inc/bootstrap.php';
$search = trim($_GET['q'] ?? '');
$like = '%' . addcslashes($search, '%_\\') . '%';
$list = rows('SELECT u.id, u.username, u.display_name, u.avatar, (SELECT COUNT(*) FROM pets WHERE user_id=u.id) AS pets
              FROM users u WHERE u.is_banned=0 AND (u.username LIKE ? OR u.display_name LIKE ?) ORDER BY u.created_at DESC LIMIT 60', [$like, $like]);
page_header('Entdecken', 'users');
?>
<h1>Spieler entdecken</h1>
<form class="row card" method="get"><input class="grow" name="q" value="<?= e($search) ?>" placeholder="Name suchen..."><button class="btn">Suchen</button></form>
<div class="grid small">
<?php foreach ($list as $x): ?>
  <a class="pet-card" href="<?= e(url('profile.php?u=' . urlencode($x['username']))) ?>"><div class="pet-emoji"><?= e($x['avatar']) ?></div><h3><?= e($x['display_name'] ?: $x['username']) ?></h3><p class="muted">@<?= e($x['username']) ?> · <?= (int)$x['pets'] ?> Tier(e)</p></a>
<?php endforeach; ?>
<?php if (!$list): ?><p class="muted">Nichts gefunden.</p><?php endif; ?>
</div>
<?php page_footer();
