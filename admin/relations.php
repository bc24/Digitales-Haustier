<?php
require __DIR__ . '/_inc.php';
$species = rows('SELECT id, name, emoji FROM species ORDER BY id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    foreach ($species as $a) foreach ($species as $b) {
        if ($a['id'] > $b['id']) continue;
        $v = (int)($_POST["r_{$a['id']}_{$b['id']}"] ?? 0);
        $v = max(-2, min(2, $v));
        q('INSERT INTO relations (species_a, species_b, affinity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE affinity=VALUES(affinity)', [$a['id'], $b['id'], $v]);
    }
    flash('Beziehungen gespeichert.');
    redirect('admin/relations.php');
}
$map = [];
foreach (rows('SELECT * FROM relations') as $r) $map[$r['species_a'] . '_' . $r['species_b']] = (int)$r['affinity'];
admin_header('Beziehungen der Arten', 'relations');
?>
<p class="muted">Bestimmt, wie gut sich Arten verstehen. Bei Treffen fließen Zuneigung und Pflege zusätzlich ein, auch Erzfeinde können Freunde werden.</p>
<form method="post" class="card"><?= csrf_field() ?>
<div class="rel-grid">
<?php foreach ($species as $a) foreach ($species as $b): if ($a['id'] > $b['id']) continue; $cur = $map[$a['id'] . '_' . $b['id']] ?? ($a['id'] === $b['id'] ? 1 : 0); ?>
  <label class="rel"><span><?= e($a['emoji'] . ' ' . $a['name']) ?> + <?= e($b['emoji'] . ' ' . $b['name']) ?></span>
    <select name="r_<?= $a['id'] ?>_<?= $b['id'] ?>"><?php foreach ([-2, -1, 0, 1, 2] as $o): ?><option value="<?= $o ?>" <?= $cur === $o ? 'selected' : '' ?>><?= e(affinity_label($o)) ?></option><?php endforeach; ?></select></label>
<?php endforeach; ?>
</div>
<button class="btn">Alle speichern</button></form>
<?php page_footer();
