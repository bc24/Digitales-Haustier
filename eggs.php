<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$count = (int)val('SELECT COUNT(*) FROM pets WHERE user_id=?', [$uid]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $do = $_POST['do'] ?? '';
    $eggId = (int)($_POST['egg'] ?? 0);
    if ($do === 'hatch') {
        $egg = row('SELECT e.*, i.weights FROM eggs e JOIN items i ON i.id=e.item_id WHERE e.id=? AND e.user_id=?', [$eggId, $uid]);
        $name = trim($_POST['name'] ?? '');
        if (!$egg) flash('Ei nicht gefunden.', 'err');
        elseif (strtotime($egg['hatch_at']) > time()) flash('Das Ei ist noch nicht bereit.', 'err');
        elseif ($count >= max_pets_for($u)) flash('Du hast keinen freien Platz für ein weiteres Tier. Gib ein Tier ab oder kaufe eine Stall-Erweiterung.', 'err');
        elseif (mb_strlen($name) < 2 || mb_strlen($name) > 30) flash('Der Name muss 2-30 Zeichen lang sein.', 'err');
        elseif (q('DELETE FROM eggs WHERE id=? AND user_id=? AND hatch_at <= NOW()', [$eggId, $uid])->rowCount() === 1) {
            $s = roll_species($egg['weights']); $hue = roll_hue();
            $pid = create_pet($uid, $s, $name, $hue);
            track($uid, 'hatch');
            award($uid, 0, 15);
            log_activity($uid, $pid, "$name ist geschlüpft: {$s['emoji']} {$s['name']} (" . RARITY[$s['rarity']] . ')' . ($hue ? ' mit seltener Farbe!' : ''));
            flash("Es ist geschlüpft: {$s['emoji']} {$s['name']} (" . RARITY[$s['rarity']] . ')' . ($hue ? ' in einer seltenen Schillerfarbe!' : '!'));
            redirect('pet.php?id=' . $pid);
        }
    } elseif ($do === 'lamp') {
        $lamp = item_by_name('Wärmelampe');
        if (!row('SELECT 1 FROM eggs WHERE id=? AND user_id=? AND hatch_at > NOW()', [$eggId, $uid])) flash('Ei nicht gefunden oder schon bereit.', 'err');
        elseif (!$lamp || !take_item($uid, (int)$lamp['id'])) flash('Du hast keine Wärmelampe. Im Shop erhältlich.', 'err');
        else { q('UPDATE eggs SET hatch_at = hatch_at - INTERVAL 60 MINUTE WHERE id=?', [$eggId]); flash('Die Wärmelampe verkürzt die Brutzeit um 60 Minuten.'); }
    } elseif ($do === 'warm') {
        $egg = row('SELECT e.*, u.username FROM eggs e JOIN users u ON u.id=e.user_id WHERE e.id=? AND e.hatch_at > NOW()', [$eggId]);
        if (!$egg || !are_friends($uid, (int)$egg['user_id'])) flash('Dieses Ei kannst du nicht wärmen.', 'err');
        elseif (daily_get($uid, 'warm') >= 10) flash('Heute hast du genug Eier gewärmt.', 'err');
        elseif (q('INSERT IGNORE INTO egg_warm (egg_id, user_id) VALUES (?,?)', [$eggId, $uid])->rowCount() === 0) flash('Du hast dieses Ei schon gewärmt.', 'err');
        else {
            q('UPDATE eggs SET hatch_at = hatch_at - INTERVAL 15 MINUTE WHERE id=?', [$eggId]);
            daily_add($uid, 'warm'); $c = award($uid, 4, 2);
            log_activity((int)$egg['user_id'], null, $u['username'] . ' hat dein Ei gewärmt (-15 Min.).');
            flash("Du hast das Ei von {$egg['username']} gewärmt. +$c Münzen");
        }
    }
    redirect('eggs.php');
}
$eggs = rows('SELECT e.*, i.name, i.emoji, i.rarity, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), e.hatch_at)) AS secs FROM eggs e JOIN items i ON i.id=e.item_id WHERE e.user_id=? ORDER BY e.hatch_at', [$uid]);
$friendEggs = rows("SELECT e.id, i.name AS iname, i.emoji, u.username, u.display_name, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), e.hatch_at)) AS secs,
                    (SELECT 1 FROM egg_warm w WHERE w.egg_id=e.id AND w.user_id=?) AS warmed
                    FROM eggs e JOIN items i ON i.id=e.item_id JOIN users u ON u.id=e.user_id
                    WHERE e.hatch_at > NOW() AND e.user_id IN (SELECT IF(requester_id=?, addressee_id, requester_id) FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?))
                    ORDER BY e.hatch_at LIMIT 20", [$uid, $uid, $uid, $uid]);
$lamps = item_by_name('Wärmelampe') ? item_qty($uid, (int)item_by_name('Wärmelampe')['id']) : 0;
page_header('Brutkasten', 'eggs');
?>
<h1>Brutkasten</h1>
<p class="muted">Eier bekommst du im <a href="<?= e(url('shop.php?tab=egg')) ?>">Shop</a>, über das Glücksrad und Truhen. Freunde können dein Ei wärmen (−15 Min. pro Freund). Wärmelampen im Rucksack: <b><?= $lamps ?></b></p>
<?php if (!$eggs): ?><div class="card"><p class="muted">Kein Ei im Brutkasten. <a href="<?= e(url('shop.php?tab=egg')) ?>">Eier kaufen</a></p></div><?php endif; ?>
<?php foreach ($eggs as $e): $ready = (int)$e['secs'] === 0; ?>
  <div class="card egg-card"><div class="ie"><?= e($e['emoji']) ?></div>
    <div><h3 style="margin:0"><?= e($e['name']) ?> <?= rarity_chip($e['rarity']) ?></h3>
      <p class="muted" style="margin:4px 0"><?= $ready ? 'Das Ei wackelt, es ist bereit zum Schlüpfen!' : 'Schlüpft in: <b data-countdown="' . (int)$e['secs'] . '" data-reload="1"></b>' ?></p></div>
    <div>
    <?php if ($ready): ?>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="do" value="hatch"><input type="hidden" name="egg" value="<?= $e['id'] ?>">
        <input name="name" placeholder="Name des Tieres" maxlength="30" required style="width:160px"><button class="btn small">Ausbrüten</button></form>
    <?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="lamp"><input type="hidden" name="egg" value="<?= $e['id'] ?>"><button class="btn small ghost" <?= $lamps ? '' : 'disabled' ?>>🔥 Wärmelampe</button></form>
    <?php endif; ?></div>
  </div>
<?php endforeach; ?>
<h2>Eier von Freunden wärmen</h2>
<div class="card"><?php if (!$friendEggs): ?><p class="muted">Gerade brütet keiner deiner Freunde.</p><?php endif; ?>
<ul class="list" style="padding:0"><?php foreach ($friendEggs as $f): ?>
  <li class="row"><span class="av-s"><?= e($f['emoji']) ?></span><span class="grow"><?= e($f['display_name'] ?: $f['username']) ?> · <?= e($f['iname']) ?> <span class="muted">(<span data-countdown="<?= (int)$f['secs'] ?>"></span>)</span></span>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="warm"><input type="hidden" name="egg" value="<?= $f['id'] ?>">
      <button class="btn small <?= $f['warmed'] ? 'ghost' : '' ?>" <?= $f['warmed'] ? 'disabled' : '' ?>><?= $f['warmed'] ? 'Gewärmt' : '🔥 Wärmen (+4 🪙)' ?></button></form></li>
<?php endforeach; ?></ul></div>
<?php page_footer();
