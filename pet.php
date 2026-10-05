<?php
require __DIR__ . '/inc/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$p = get_pet($id);
if (!$p) { http_response_code(404); page_header('Nicht gefunden'); echo '<div class="card"><h1>Tier nicht gefunden</h1></div>'; page_footer(); exit; }
$u = current_user();
$own = $u && (int)$u['id'] === (int)$p['user_id'];
$friend = $u && !$own && are_friends((int)$u['id'], (int)$p['user_id']);

if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $act = $_POST['action'] ?? '';
    $me = (int)$u['id'];
    if ($own) {
        if ($act === 'rename') {
            $n = trim($_POST['name'] ?? '');
            if (mb_strlen($n) >= 2 && mb_strlen($n) <= 30) { q('UPDATE pets SET name=? WHERE id=?', [$n, $id]); flash('Name geändert.'); }
            else flash('Der Name muss 2-30 Zeichen lang sein.', 'err');
        } elseif ($act === 'release') {
            q('DELETE FROM pets WHERE id=?', [$id]);
            flash($p['name'] . ' wurde in die Freiheit entlassen.');
            redirect('dashboard.php');
        } elseif ($act === 'use_item') {
            $it = row("SELECT * FROM items WHERE id=? AND kind='use'", [(int)($_POST['item'] ?? 0)]);
            if (!$it) flash('Unbekanntes Item.', 'err');
            else { [$ok, $msg] = use_item_on_pet($p, $it); flash($msg, $ok ? 'ok' : 'err'); }
        } elseif ($act === 'equip') {
            foreach (['hat' => 'hat_id', 'room' => 'room_id'] as $kind => $col) {
                if (!isset($_POST[$kind])) continue;
                $iid = (int)$_POST[$kind];
                if ($iid === 0) q("UPDATE pets SET $col = NULL WHERE id=?", [$id]);
                elseif (row('SELECT 1 FROM items WHERE id=? AND kind=?', [$iid, $kind]) && item_qty($me, $iid) > 0) q("UPDATE pets SET $col = ? WHERE id=?", [$iid, $id]);
            }
            flash('Aussehen gespeichert.');
        } else {
            [$ok, $msg] = pet_action($p, $act, $_POST['food'] ?? null);
            flash($msg, $ok ? 'ok' : 'err');
        }
    } elseif ($friend && $act === 'visit') {
        if (daily_get($me, 'visit') >= 10) flash('Heute hast du schon genug Besuche gemacht.', 'err');
        elseif (q('INSERT IGNORE INTO visits (pet_id, user_id, day) VALUES (?,?,CURDATE())', [$id, $me])->rowCount() === 0) flash('Du hast dieses Tier heute schon besucht.', 'err');
        else {
            $p['fun'] = clamp($p['fun'] + 8); $p['affection'] = clamp($p['affection'] + 0.5); $p['xp'] += 3;
            save_pet($p);
            track($me, 'visit');
            $c = award($me, 6, 4);
            log_activity((int)$p['user_id'], $id, $u['username'] . ' hat ' . $p['name'] . ' besucht und gestreichelt.');
            flash($p['name'] . ' freut sich über deinen Besuch! +' . $c . ' Münzen');
        }
    }
    redirect('pet.php?id=' . $id);
}

$bonds = rows('SELECT b.*, o.id AS oid, o.name AS oname, s.emoji, s.name AS ospecies, u.username
               FROM pet_bonds b JOIN pets o ON o.id = IF(b.pet_a = ?, b.pet_b, b.pet_a)
               JOIN species s ON s.id = o.species_id JOIN users u ON u.id = o.user_id
               WHERE b.pet_a = ? OR b.pet_b = ? ORDER BY b.score DESC', [$id, $id, $id]);
[$mood, $face, $cls] = pet_mood($p);
$lvl = pet_level($p);
[$stageName] = pet_stage($lvl);
$petXpFrom = ($lvl - 1) ** 2 * 10; $petXpTo = $lvl ** 2 * 10;
$useItems = $own ? rows("SELECT i.*, inv.qty FROM inventory inv JOIN items i ON i.id=inv.item_id WHERE inv.user_id=? AND i.kind='use' AND inv.qty>0 ORDER BY i.sort", [$u['id']]) : [];
$hats = $own ? rows("SELECT i.* FROM inventory inv JOIN items i ON i.id=inv.item_id WHERE inv.user_id=? AND i.kind='hat' AND inv.qty>0", [$u['id']]) : [];
$rooms = $own ? rows("SELECT i.* FROM inventory inv JOIN items i ON i.id=inv.item_id WHERE inv.user_id=? AND i.kind='room' AND inv.qty>0", [$u['id']]) : [];
$visited = $friend ? (bool)val('SELECT 1 FROM visits WHERE pet_id=? AND user_id=? AND day=CURDATE()', [$id, $u['id']]) : false;
page_header($p['name'], $own ? 'dashboard' : '');
?>
<div class="pet-view" style="--c:<?= e($p['color']) ?>">
  <div class="card stage <?= $p['room_color'] ? 'room' : '' ?>" style="<?= $p['room_color'] ? '--room:' . e($p['room_color']) : '' ?>">
    <div class="big-pet <?= e($cls) ?>"><?= pet_visual($p, 'xl') ?></div>
    <div class="mood"><?= $face ?> <?= e(ucfirst($mood)) ?></div>
    <div class="bubble"><?= e(pet_say($p)) ?></div>
    <h1><?= e($p['name']) ?><?= (int)$p['hue'] ? ' ✨' : '' ?></h1>
    <p class="muted"><?= e($p['species']) ?> <?= rarity_chip($p['rarity']) ?> <span class="chip"><?= e($stageName) ?></span><br>
      <?= e($p['temperament']) ?> · Level <?= $lvl ?> · Besitzer: <a href="<?= e(url('profile.php?u=' . urlencode($p['username']))) ?>"><?= e($p['owner_name'] ?: $p['username']) ?></a></p>
    <?= progress_bar(($p['xp'] - $petXpFrom) / max(1, $petXpTo - $petXpFrom) * 100, 'b-fun') ?>
    <p class="muted center" style="font-size:.8rem">Erfahrung <?= (int)$p['xp'] ?> / <?= $petXpTo ?></p>
    <?php if ($friend): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="visit">
        <button class="btn" <?= $visited ? 'disabled style="opacity:.5"' : '' ?>><?= $visited ? 'Heute schon besucht' : '🤗 Besuchen &amp; streicheln (+Münzen)' ?></button></form>
    <?php endif; ?>
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
  <h3>Kümmern <span class="muted" style="font-weight:400;font-size:.85rem">· Pflege bringt Münzen und Zuneigung</span></h3>
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
<div class="card">
  <h3>Rucksack</h3>
  <?php if (!$useItems): ?><p class="muted">Keine Items. <a href="<?= e(url('shop.php')) ?>">Zum Shop</a></p><?php else: ?>
  <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="use_item">
    <?php foreach ($useItems as $it): ?><button class="act" name="item" value="<?= $it['id'] ?>" title="<?= e($it['description']) ?>"><?= e($it['emoji'] . ' ' . $it['name']) ?> <span class="muted">×<?= (int)$it['qty'] ?></span></button><?php endforeach; ?>
  </form><?php endif; ?>
</div>
<?php if ($hats || $rooms): ?>
<div class="card"><h3>Aussehen</h3>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="equip">
    <label class="grow">Kopfschmuck<select name="hat"><option value="0">Keiner</option><?php foreach ($hats as $h): ?><option value="<?= $h['id'] ?>" <?= (int)$p['hat_id'] === (int)$h['id'] ? 'selected' : '' ?>><?= e($h['emoji'] . ' ' . $h['name']) ?></option><?php endforeach; ?></select></label>
    <label class="grow">Zimmer<select name="room"><option value="0">Standard</option><?php foreach ($rooms as $h): ?><option value="<?= $h['id'] ?>" <?= (int)$p['room_id'] === (int)$h['id'] ? 'selected' : '' ?>><?= e($h['emoji'] . ' ' . $h['name']) ?></option><?php endforeach; ?></select></label>
    <button class="btn small">Speichern</button></form></div>
<?php endif; endif; ?>

<div class="card">
  <h3>Freundschaften</h3>
  <?php if (!$bonds): ?><p class="muted">Noch keine Treffen mit anderen Tieren.</p><?php endif; ?>
  <ul class="feed"><?php foreach ($bonds as $b): ?>
    <li><a href="<?= e(url('pet.php?id=' . $b['oid'])) ?>"><?= e($b['emoji'] . ' ' . $b['oname']) ?></a> (<?= e($b['ospecies']) ?>, <?= e($b['username']) ?>) – <b><?= e(bond_label((int)$b['score'])) ?></b>, <?= (int)$b['meetings'] ?> Treffen</li>
  <?php endforeach; ?></ul>
  <?php if ($own): ?><a class="btn small" href="<?= e(url('playdate.php?pet=' . $id)) ?>">Spieltreffen planen</a> <a class="btn small ghost" href="<?= e(url('arena.php?pet=' . $id)) ?>">Arena</a><?php endif; ?>
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
