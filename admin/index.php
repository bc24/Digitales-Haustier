<?php
require __DIR__ . '/_inc.php';
$s = [
    'Benutzer' => val('SELECT COUNT(*) FROM users'),
    'Gesperrt' => val('SELECT COUNT(*) FROM users WHERE is_banned=1'),
    'Tiere' => val('SELECT COUNT(*) FROM pets'),
    'Arten' => val('SELECT COUNT(*) FROM species'),
    'Freundschaften' => val("SELECT COUNT(*) FROM friendships WHERE status='accepted'"),
    'Offene Anfragen' => val("SELECT COUNT(*) FROM friendships WHERE status='pending'"),
    'Spieltreffen' => val('SELECT COALESCE(SUM(meetings),0) FROM pet_bonds'),
];
$latest = rows('SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT 8');
$act = rows('SELECT a.message, a.created_at, u.username FROM activity a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 12');
admin_header('Übersicht', 'index');
?>
<div class="stats-row"><?php foreach ($s as $k => $n): ?><div><b><?= (int)$n ?></b><span><?= e($k) ?></span></div><?php endforeach; ?></div>
<div class="two">
  <div class="card"><h3>Neue Benutzer</h3><ul class="feed"><?php foreach ($latest as $x): ?><li><a href="<?= e(url('profile.php?u=' . urlencode($x['username']))) ?>"><?= e($x['username']) ?></a> <span class="muted"><?= e($x['email']) ?> · <?= e(time_ago($x['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
  <div class="card"><h3>Letzte Aktivität</h3><ul class="feed"><?php foreach ($act as $x): ?><li><b><?= e($x['username']) ?>:</b> <?= e($x['message']) ?> <span class="muted"><?= e(time_ago($x['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
</div>
<?php page_footer();
