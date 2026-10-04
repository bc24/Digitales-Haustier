<?php
require __DIR__ . '/inc/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$p = get_pet($id);
if (!$p) { http_response_code(404); page_header('Nicht gefunden'); echo '<div class="card"><h1>Tier nicht gefunden</h1></div>'; page_footer(); exit; }
$u = current_user();
$own = $u && (int)$u['id'] === (int)$p['user_id'];

if ($own && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    if (($_POST['action'] ?? '') === 'rename') {
        $n = trim($_POST['name'] ?? '');
        if (mb_strlen($n) >= 2 && mb_strlen($n) <= 30) { q('UPDATE pets SET name=? WHERE id=?', [$n, $id]); flash('Name geändert.'); }
        else flash('Der Name muss 2-30 Zeichen lang sein.', 'err');
    } elseif (($_POST['action'] ?? '') === 'release') {
        q('DELETE FROM pets WHERE id=?', [$id]);
        flash($p['name'] . ' wurde in die Freiheit entlassen.');
        redirect('dashboard.php');
    } else {
        [$ok, $msg] = pet_action($p, $_POST['action'] ?? '', $_POST['food'] ?? null);
        flash($msg, $ok ? 'ok' : 'err');
    }
    redirect('pet.php?id=' . $id);
}

$bonds = rows('SELECT b.*, o.id AS oid, o.name AS oname, s.emoji, s.name AS ospecies, u.username
               FROM pet_bonds b JOIN pets o ON o.id = IF(b.pet_a = ?, b.pet_b, b.pet_a)
               JOIN species s ON s.id = o.species_id JOIN users u ON u.id = o.user_id
               WHERE b.pet_a = ? OR b.pet_b = ? ORDER BY b.score DESC', [$id, $id, $id]);
[$mood, $face, $cls] = pet_mood($p);
page_header($p['name'], $own ? 'home' : '');
?>
<div class="pet-view" style="--c:<?= e($p['color']) ?>">
  <div class="card stage">
    <div class="big-pet <?= e($cls) ?>"><?= e($p['emoji']) ?></div>
    <div class="mood"><?= $face ?> <?= e(ucfirst($mood)) ?></div>
    <div class="bubble"><?= e(pet_say($p)) ?></div>
    <h1><?= e($p['name']) ?></h1>
    <p class="muted"><?= e($p['species']) ?> · <?= e($p['temperament']) ?> · Level <?= pet_level($p) ?> · Besitzer: <a href="<?= e(url('profile.php?u=' . urlencode($p['username']))) ?>"><?= e($p['owner_name'] ?: $p['username']) ?></a></p>
  </div>
  <div class="card">
    <h3>Zustand</h3>
    <?= mood_bar('Sättigung', $p['food'], 'b-food') ?>
    <?= mood_bar('Spaß', $p['fun'], 'b-fun') ?>
    <?= mood_bar('Sauberkeit', $p['clean'], 'b-clean') ?>
    <?= mood_bar('Energie', $p['energy'], 'b-energy') ?>
    <?= mood_bar('Gesundheit', $p['health'], 'b-health') ?>
    <?= mood_bar('Zuneigung', $p['affection'], 'b-love') ?>
    <p class="muted center">Beziehung zu <?= $own ? 'dir' : 'dem Besitzer' ?>: <b><?= e(affection_label((float)$p['affection'])) ?></b></p>
  </div>
</div>

<?php if ($own): ?>
<div class="card">
  <h3>Kümmern</h3>
  <form method="post" class="actions"><?= csrf_field() ?>
    <button class="act" name="action" value="feed" onclick="this.form.food.value='normal'">🍖 Füttern</button>
    <button class="act" name="action" value="feed" onclick="this.form.food.value='treat'">🍪 Leckerli</button>
    <button class="act" name="action" value="feed" onclick="this.form.food.value='favorite'">⭐ <?= e($p['favorite_food']) ?></button>
    <button class="act" name="action" value="play">🎾 Spielen</button>
    <button class="act" name="action" value="pet">🤚 Streicheln</button>
    <button class="act" name="action" value="wash">🛁 Waschen</button>
    <button class="act" name="action" value="heal">💊 Pflegen</button>
    <button class="act" name="action" value="sleep">💤 Schlafen</button>
    <input type="hidden" name="food" value="normal">
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>Freundschaften</h3>
  <?php if (!$bonds): ?><p class="muted">Noch keine Treffen mit anderen Tieren.</p><?php endif; ?>
  <ul class="feed"><?php foreach ($bonds as $b): ?>
    <li><a href="<?= e(url('pet.php?id=' . $b['oid'])) ?>"><?= e($b['emoji'] . ' ' . $b['oname']) ?></a> (<?= e($b['ospecies']) ?>, <?= e($b['username']) ?>) – <b><?= e(bond_label((int)$b['score'])) ?></b>, <?= (int)$b['meetings'] ?> Treffen</li>
  <?php endforeach; ?></ul>
  <?php if ($own): ?><a class="btn small" href="<?= e(url('playdate.php?pet=' . $id)) ?>">Spieltreffen planen</a><?php endif; ?>
</div>

<?php if ($own): ?>
<details class="card"><summary>Einstellungen</summary>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="rename">
    <input name="name" value="<?= e($p['name']) ?>" maxlength="30" required><button class="btn small">Umbenennen</button></form>
  <form method="post" onsubmit="return confirm('Dieses Tier wirklich abgeben?')"><?= csrf_field() ?><input type="hidden" name="action" value="release">
    <button class="btn danger small">Tier abgeben</button></form>
</details>
<?php endif;
page_footer();
