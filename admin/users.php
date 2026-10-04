<?php
require __DIR__ . '/_inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';
    $t = row('SELECT * FROM users WHERE id=?', [$id]);
    if (!$t) flash('Benutzer nicht gefunden.', 'err');
    elseif ($id === (int)$admin['id'] && in_array($do, ['ban', 'demote', 'delete'], true)) flash('Das kannst du bei dir selbst nicht tun.', 'err');
    else switch ($do) {
        case 'ban': q('UPDATE users SET is_banned=1 WHERE id=?', [$id]); flash('Gesperrt.'); break;
        case 'unban': q('UPDATE users SET is_banned=0 WHERE id=?', [$id]); flash('Entsperrt.'); break;
        case 'promote': q('UPDATE users SET is_admin=1 WHERE id=?', [$id]); flash('Zum Admin ernannt.'); break;
        case 'demote': q('UPDATE users SET is_admin=0 WHERE id=?', [$id]); flash('Adminrechte entzogen.'); break;
        case 'delete': q('DELETE FROM users WHERE id=?', [$id]); flash('Benutzer samt Tieren gelöscht.'); break;
        case 'password':
            $pw = $_POST['password'] ?? '';
            if (strlen($pw) < 8) flash('Passwort: mindestens 8 Zeichen.', 'err');
            else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]); flash('Passwort gesetzt.'); }
            break;
    }
    redirect('admin/users.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}
$search = trim($_GET['q'] ?? '');
$like = '%' . addcslashes($search, '%_\\') . '%';
$list = rows('SELECT u.*, (SELECT COUNT(*) FROM pets WHERE user_id=u.id) AS pets FROM users u WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 200', [$like, $like]);
admin_header('Benutzer', 'users');
?>
<form class="row card" method="get"><input class="grow" name="q" value="<?= e($search) ?>" placeholder="Name oder E-Mail"><button class="btn">Suchen</button></form>
<div class="card scroll"><table>
<tr><th>ID</th><th>Name</th><th>E-Mail</th><th>Tiere</th><th>Status</th><th>Aktionen</th></tr>
<?php foreach ($list as $x): ?>
<tr><td><?= $x['id'] ?></td>
<td><a href="<?= e(url('profile.php?u=' . urlencode($x['username']))) ?>"><?= e($x['username']) ?></a></td>
<td><?= e($x['email']) ?></td><td><?= (int)$x['pets'] ?></td>
<td><?= $x['is_admin'] ? '<span class="chip">Admin</span> ' : '' ?><?= $x['is_banned'] ? '<span class="chip bad">Gesperrt</span>' : '' ?></td>
<td><form method="post" class="row wrap-r"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><input type="hidden" name="q" value="<?= e($search) ?>">
  <?php if ($x['is_banned']): ?><button class="btn small ghost" name="do" value="unban">Entsperren</button><?php else: ?><button class="btn small ghost" name="do" value="ban">Sperren</button><?php endif; ?>
  <?php if ($x['is_admin']): ?><button class="btn small ghost" name="do" value="demote">Admin entziehen</button><?php else: ?><button class="btn small ghost" name="do" value="promote">Zum Admin</button><?php endif; ?>
  <input name="password" type="password" placeholder="Neues Passwort" class="mini"><button class="btn small ghost" name="do" value="password">Setzen</button>
  <button class="btn small danger" name="do" value="delete" onclick="return confirm('Benutzer und alle Tiere endgültig löschen?')">Löschen</button>
</form></td></tr>
<?php endforeach; ?>
</table></div>
<?php page_footer();
