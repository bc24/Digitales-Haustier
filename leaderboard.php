<?php
require __DIR__ . '/inc/bootstrap.php';
$me = current_user();
$tabs = ['trophies' => '🏆 Trophäen', 'level' => '⭐ Level', 'streak' => '🔥 Streak', 'catch' => '🧺 Fänger', 'memory' => '🧠 Memory', 'whack' => '🔨 Tier-Tipp'];
$tab = $_GET['tab'] ?? 'trophies'; if (!isset($tabs[$tab])) $tab = 'trophies';
$scope = ($_GET['scope'] ?? 'all') === 'friends' && $me ? 'friends' : 'all';
$ids = null;
if ($scope === 'friends') {
    $ids = array_map('intval', array_column(rows("SELECT IF(requester_id=?, addressee_id, requester_id) AS f FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?)", [$me['id'], $me['id'], $me['id']]), 'f'));
    $ids[] = (int)$me['id'];
}
$in = $ids ? ' AND u.id IN (' . implode(',', $ids) . ')' : '';
if (in_array($tab, ['catch', 'memory', 'whack'], true)) {
    $list = rows("SELECT u.id, u.username, u.display_name, u.avatar, MAX(r.score) AS val FROM minigame_runs r JOIN users u ON u.id=r.user_id
                  WHERE r.game=? AND r.week=YEARWEEK(NOW(), 3) AND r.finished=1 AND u.is_banned=0 $in GROUP BY u.id HAVING val > 0 ORDER BY val DESC, u.id LIMIT 25", [$tab]);
    $label = 'Punkte (diese Woche)';
} else {
    $col = ['trophies' => 'u.trophies', 'level' => 'u.xp', 'streak' => 'u.best_streak'][$tab];
    $list = rows("SELECT u.id, u.username, u.display_name, u.avatar, $col AS val FROM users u WHERE u.is_banned=0 $in ORDER BY val DESC, u.id LIMIT 25");
    $label = ['trophies' => 'Trophäen', 'level' => 'Level', 'streak' => 'Längster Streak (Tage)'][$tab];
}
page_header('Ranglisten', 'leaderboard');
?>
<h1>Ranglisten</h1>
<div class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="?tab=<?= $k ?>&scope=<?= $scope ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<?php if ($me): ?><div class="tabs"><a class="<?= $scope === 'all' ? 'on' : '' ?>" href="?tab=<?= $tab ?>&scope=all">Alle Spieler</a><a class="<?= $scope === 'friends' ? 'on' : '' ?>" href="?tab=<?= $tab ?>&scope=friends">Nur Freunde</a></div><?php endif; ?>
<div class="card"><table class="rank"><tr><th>#</th><th>Spieler</th><th style="text-align:right"><?= e($label) ?></th></tr>
<?php foreach ($list as $i => $r): ?>
  <tr class="<?= $me && (int)$me['id'] === (int)$r['id'] ? 'me' : '' ?>"><td class="pos"><?= ['🥇', '🥈', '🥉'][$i] ?? $i + 1 ?></td>
    <td><a href="<?= e(url('profile.php?u=' . urlencode($r['username']))) ?>"><?= e($r['avatar'] . ' ' . ($r['display_name'] ?: $r['username'])) ?></a></td>
    <td style="text-align:right"><b><?= $tab === 'level' ? user_level((int)$r['val']) : (int)$r['val'] ?></b></td></tr>
<?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="3" class="muted">Noch keine Einträge.</td></tr><?php endif; ?></table></div>
<?php page_footer();
