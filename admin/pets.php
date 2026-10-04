<?php
require __DIR__ . '/_inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';
    if ($do === 'delete') { q('DELETE FROM pets WHERE id=?', [$id]); flash('Tier gelöscht.'); }
    elseif ($do === 'reset') { q("UPDATE pets SET food=80, fun=70, clean=80, energy=90, health=100, last_update=NOW() WHERE id=?", [$id]); flash('Werte zurückgesetzt.'); }
    elseif ($do === 'rename') {
        $n = trim($_POST['name'] ?? '');
        if (mb_strlen($n) >= 2 && mb_strlen($n) <= 30) { q('UPDATE pets SET name=? WHERE id=?', [$n, $id]); flash('Umbenannt.'); } else flash('Name: 2-30 Zeichen.', 'err');
    }
    redirect('admin/pets.php');
}
$list = rows('SELECT p.id, p.name, p.xp, p.affection, s.emoji, s.name AS species, u.username FROM pets p JOIN species s ON s.id=p.species_id JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 300');
admin_header('Tiere', 'pets');
?>
<div class="card scroll"><table>
<tr><th>ID</th><th>Tier</th><th>Art</th><th>Besitzer</th><th>XP</th><th>Zuneigung</th><th>Aktionen</th></tr>
<?php foreach ($list as $x): ?>
<tr><td><?= $x['id'] ?></td><td><a href="<?= e(url('pet.php?id=' . $x['id'])) ?>"><?= e($x['name']) ?></a></td><td><?= e($x['emoji'] . ' ' . $x['species']) ?></td><td><?= e($x['username']) ?></td><td><?= (int)$x['xp'] ?></td><td><?= round($x['affection']) ?></td>
<td><form method="post" class="row wrap-r"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
  <input name="name" value="<?= e($x['name']) ?>" class="mini" maxlength="30"><button class="btn small ghost" name="do" value="rename">Umbenennen</button>
  <button class="btn small ghost" name="do" value="reset">Werte zurücksetzen</button>
  <button class="btn small danger" name="do" value="delete" onclick="return confirm('Tier löschen?')">Löschen</button></form></td></tr>
<?php endforeach; ?></table></div>
<?php page_footer();
