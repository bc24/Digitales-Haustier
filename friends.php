<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$me = (int)$u['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $other = (int)($_POST['user'] ?? 0);
    $do = $_POST['do'] ?? '';
    $target = row('SELECT id, username FROM users WHERE id = ? AND is_banned = 0', [$other]);
    $back = $_POST['back'] ?? '';
    if (!$target || $other === $me) { flash('Ungültiger Nutzer.', 'err'); redirect('friends.php'); }
    $existing = row('SELECT * FROM friendships WHERE (requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?)', [$me, $other, $other, $me]);
    if ($do === 'request') {
        if ($existing) flash('Es besteht bereits eine Anfrage oder Freundschaft.', 'err');
        else { q("INSERT INTO friendships (requester_id, addressee_id, status, created_at) VALUES (?,?, 'pending', NOW())", [$me, $other]); flash('Anfrage gesendet.'); }
    } elseif ($do === 'accept' && $existing && $existing['status'] === 'pending' && (int)$existing['addressee_id'] === $me) {
        q("UPDATE friendships SET status='accepted' WHERE id=?", [$existing['id']]);
        log_activity($other, null, $u['username'] . ' hat deine Freundschaftsanfrage angenommen.');
        check_achievements($me); check_achievements($other);
        flash('Ihr seid jetzt befreundet.');
    } elseif (in_array($do, ['decline', 'remove', 'cancel'], true) && $existing) {
        q('DELETE FROM friendships WHERE id=?', [$existing['id']]);
        flash($do === 'remove' ? 'Freundschaft beendet.' : 'Erledigt.');
    }
    redirect($back === 'profile' ? 'profile.php?u=' . urlencode($target['username']) : 'friends.php');
}
$incoming = rows("SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen FROM friendships f JOIN users u ON u.id=f.requester_id WHERE f.addressee_id=? AND f.status='pending'", [$me]);
$outgoing = rows("SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen FROM friendships f JOIN users u ON u.id=f.addressee_id WHERE f.requester_id=? AND f.status='pending'", [$me]);
$friends = rows("SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen FROM friendships f JOIN users u ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
                 WHERE f.status='accepted' AND (f.requester_id=? OR f.addressee_id=?) AND u.is_banned=0 ORDER BY u.username", [$me, $me, $me]);
function friend_row(array $f, array $buttons): void { ?>
  <li class="row"><span class="av-s"><?= e($f['avatar']) ?></span><?= !empty($f['last_seen']) && time() - strtotime($f['last_seen']) < 300 ? '<i class="online" title="online"></i>' : '' ?>
    <a class="grow" href="<?= e(url('profile.php?u=' . urlencode($f['username']))) ?>"><?= e($f['display_name'] ?: $f['username']) ?> <span class="muted">@<?= e($f['username']) ?></span></a>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="user" value="<?= (int)$f['id'] ?>">
      <?php foreach ($buttons as $do => $label): ?><button class="btn small <?= $do === 'accept' ? '' : 'ghost' ?>" name="do" value="<?= e($do) ?>"><?= e($label) ?></button><?php endforeach; ?></form></li>
<?php }
page_header('Freunde', 'friends');
?>
<h1>Freunde</h1>
<?php if ($incoming): ?><h2>Anfragen an dich</h2><ul class="list card"><?php foreach ($incoming as $f) friend_row($f, ['accept' => 'Annehmen', 'decline' => 'Ablehnen']); ?></ul><?php endif; ?>
<h2>Meine Freunde (<?= count($friends) ?>)</h2>
<ul class="list card"><?php foreach ($friends as $f) friend_row($f, ['remove' => 'Entfernen']); ?>
<?php if (!$friends): ?><li class="muted">Noch keine Freunde. <a href="<?= e(url('users.php')) ?>">Spieler entdecken</a></li><?php endif; ?></ul>
<?php if ($outgoing): ?><h2>Gesendete Anfragen</h2><ul class="list card"><?php foreach ($outgoing as $f) friend_row($f, ['cancel' => 'Zurückziehen']); ?></ul><?php endif; ?>
<?php page_footer();
