<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$me = (int)$u['id'];
$mine = user_pets($me);

// Mögliche Partner: eigene andere Tiere + Tiere von Freunden
$ids = array_column(rows("SELECT p.id FROM pets p WHERE p.user_id = ? OR p.user_id IN (
    SELECT IF(requester_id=?, addressee_id, requester_id) FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?)) ORDER BY p.user_id, p.id", [$me, $me, $me, $me]), 'id');
$partners = array_values(array_filter(array_map('get_pet', $ids)));

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $a = get_pet((int)($_POST['mine'] ?? 0));
    $b = get_pet((int)($_POST['partner'] ?? 0));
    $allowedIds = array_column($partners, 'id');
    if (!$a || (int)$a['user_id'] !== $me) flash('Wähle eines deiner Tiere.', 'err');
    elseif (!$b || !in_array($b['id'], $allowedIds) || $a['id'] === $b['id']) flash('Dieses Tier kannst du nicht einladen. Nur eigene Tiere oder Tiere von Freunden.', 'err');
    else {
        [$ok, $msg, $res] = playdate($a, $b);
        flash($msg, $ok ? ($res === 'conflict' ? 'err' : 'ok') : 'err');
        redirect('playdate.php?pet=' . $a['id'] . '&with=' . $b['id']);
    }
    redirect('playdate.php');
}
$selA = (int)($_GET['pet'] ?? 0);
$selB = (int)($_GET['with'] ?? 0);
page_header('Spieltreffen', 'play');
?>
<h1>Spieltreffen</h1>
<p class="muted">Lass dein Tier mit einem Tier von Freunden (oder deinen eigenen) spielen. Nicht jede Art versteht sich: Zuneigung, Laune und Vorgeschichte beeinflussen das Ergebnis.</p>
<?php if (!$mine): ?><div class="flash err">Du brauchst zuerst ein Tier. <a href="<?= e(url('adopt.php')) ?>">Adoptieren</a></div>
<?php else: ?>
<form method="post" class="card form-wide"><?= csrf_field() ?>
  <div class="two">
    <label>Dein Tier<select name="mine" id="mine"><?php foreach ($mine as $p): ?><option value="<?= $p['id'] ?>" <?= $selA === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['emoji'] . ' ' . $p['name']) ?></option><?php endforeach; ?></select></label>
    <label>Spielpartner<select name="partner" id="partner"><?php foreach ($partners as $p): ?><option value="<?= $p['id'] ?>" <?= $selB === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['emoji'] . ' ' . $p['name'] . ' (' . ((int)$p['user_id'] === $me ? 'eigenes Tier' : '@' . $p['username']) . ')') ?></option><?php endforeach; ?></select></label>
  </div>
  <button class="btn">Treffen starten</button>
</form>
<?php if ($selA && $selB && ($pa = get_pet($selA)) && ($pb = get_pet($selB))):
  $aff = species_affinity((int)$pa['species_id'], (int)$pb['species_id']); $bond = get_bond($selA, $selB); ?>
  <div class="card center">
    <div class="vs"><span><?= e($pa['emoji']) ?></span><b>&amp;</b><span><?= e($pb['emoji']) ?></span></div>
    <p><?= e($pa['species']) ?> und <?= e($pb['species']) ?>: <b><?= e(affinity_label($aff)) ?></b></p>
    <p class="muted">Beziehung der beiden Tiere: <?= e(bond_label((int)$bond['score'])) ?> (<?= (int)$bond['meetings'] ?> Treffen)</p>
  </div>
<?php endif; ?>
<p class="muted">Hinweis: Wie gut sich Arten grundsätzlich verstehen, legt der Admin fest. Hohe Zuneigung und gute Pflege gleichen Abneigungen teilweise aus.</p>
<?php endif;
page_footer();
