<?php
// Tierheim: Vermittlungsanträge mit Wartezeit (intern: Tabelle "eggs")
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$count = (int)val('SELECT COUNT(*) FROM pets WHERE user_id=?', [$uid]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $do = $_POST['do'] ?? '';
    $reqId = (int)($_POST['egg'] ?? 0);
    if ($do === 'hatch') {
        $req = row('SELECT e.*, i.weights FROM eggs e JOIN items i ON i.id=e.item_id WHERE e.id=? AND e.user_id=?', [$reqId, $uid]);
        $name = trim($_POST['name'] ?? '');
        if (!$req) flash('Vermittlung nicht gefunden.', 'err');
        elseif (strtotime($req['hatch_at']) > time()) flash('Die Vermittlung ist noch nicht abgeschlossen.', 'err');
        elseif ($count >= max_pets_for($u)) flash('Du hast keinen freien Platz für ein weiteres Tier. Gib ein Tier ab oder kaufe eine Stall-Erweiterung.', 'err');
        elseif (mb_strlen($name) < 2 || mb_strlen($name) > 30) flash('Der Name muss 2-30 Zeichen lang sein.', 'err');
        elseif (q('DELETE FROM eggs WHERE id=? AND user_id=? AND hatch_at <= NOW()', [$reqId, $uid])->rowCount() === 1) {
            $s = roll_species($req['weights']); $hue = roll_hue();
            $pid = create_pet($uid, $s, $name, $hue);
            track($uid, 'hatch');
            award($uid, 0, 15);
            log_activity($uid, $pid, "$name ({$s['emoji']} {$s['name']}, " . RARITY[$s['rarity']] . ') wurde aus dem Tierheim vermittelt' . ($hue ? ' und hat eine seltene Farbe!' : '.'));
            flash("Das Tierheim hat jemanden für dich gefunden: {$s['emoji']} {$s['name']} (" . RARITY[$s['rarity']] . ')' . ($hue ? ' in einer seltenen Schillerfarbe!' : '!'));
            redirect('pet.php?id=' . $pid);
        }
    } elseif ($do === 'lamp') {
        $rush = item_by_name('Eilvermerk');
        if (!row('SELECT 1 FROM eggs WHERE id=? AND user_id=? AND hatch_at > NOW()', [$reqId, $uid])) flash('Vermittlung nicht gefunden oder schon abgeschlossen.', 'err');
        elseif (!$rush || !take_item($uid, (int)$rush['id'])) flash('Du hast keinen Eilvermerk. Im Shop erhältlich.', 'err');
        else { q('UPDATE eggs SET hatch_at = hatch_at - INTERVAL 60 MINUTE WHERE id=?', [$reqId]); flash('Der Eilvermerk verkürzt die Wartezeit um 60 Minuten.'); }
    } elseif ($do === 'warm') {
        $req = row('SELECT e.*, u.username FROM eggs e JOIN users u ON u.id=e.user_id WHERE e.id=? AND e.hatch_at > NOW()', [$reqId]);
        if (!$req || !are_friends($uid, (int)$req['user_id'])) flash('Für diese Vermittlung kannst du dich nicht einsetzen.', 'err');
        elseif (daily_get($uid, 'warm') >= 10) flash('Heute hast du dich schon genug eingesetzt.', 'err');
        elseif (q('INSERT IGNORE INTO egg_warm (egg_id, user_id) VALUES (?,?)', [$reqId, $uid])->rowCount() === 0) flash('Du hast dich für diese Vermittlung schon eingesetzt.', 'err');
        else {
            q('UPDATE eggs SET hatch_at = hatch_at - INTERVAL 15 MINUTE WHERE id=?', [$reqId]);
            daily_add($uid, 'warm'); $c = award($uid, 4, 2);
            log_activity((int)$req['user_id'], null, $u['username'] . ' hat sich für deine Tierheim-Vermittlung eingesetzt (-15 Min.).');
            flash("Du hast dich für die Vermittlung von {$req['username']} eingesetzt. +$c Münzen");
        }
    }
    redirect('shelter.php');
}
$reqs = rows('SELECT e.*, i.name, i.emoji, i.rarity, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), e.hatch_at)) AS secs FROM eggs e JOIN items i ON i.id=e.item_id WHERE e.user_id=? ORDER BY e.hatch_at', [$uid]);
$friendReqs = rows("SELECT e.id, i.name AS iname, i.emoji, u.username, u.display_name, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), e.hatch_at)) AS secs,
                    (SELECT 1 FROM egg_warm w WHERE w.egg_id=e.id AND w.user_id=?) AS helped
                    FROM eggs e JOIN items i ON i.id=e.item_id JOIN users u ON u.id=e.user_id
                    WHERE e.hatch_at > NOW() AND e.user_id IN (SELECT IF(requester_id=?, addressee_id, requester_id) FROM friendships WHERE status='accepted' AND (requester_id=? OR addressee_id=?))
                    ORDER BY e.hatch_at LIMIT 20", [$uid, $uid, $uid, $uid]);
$rushItem = item_by_name('Eilvermerk');
$rushQty = $rushItem ? item_qty($uid, (int)$rushItem['id']) : 0;
page_header('Tierheim', 'shelter');
?>
<h1>Tierheim</h1>
<p class="muted">Hier stellst du Vermittlungsanträge: Nach der Wartezeit hat das Tierheim ein passendes Tier für dich, je nach Antrag auch seltene Arten. Anträge gibt es im <a href="<?= e(url('shop.php?tab=egg')) ?>">Shop</a>, über das Glücksrad und Truhen. Freunde können sich für dich einsetzen (−15 Min. pro Freund). Eilvermerke im Rucksack: <b><?= $rushQty ?></b></p>
<?php if (!$reqs): ?><div class="card"><p class="muted">Keine laufende Vermittlung. <a href="<?= e(url('shop.php?tab=egg')) ?>">Antrag stellen</a></p></div><?php endif; ?>
<?php foreach ($reqs as $r): $ready = (int)$r['secs'] === 0; ?>
  <div class="card egg-card"><div class="ie"><?= e($r['emoji']) ?></div>
    <div><h3 style="margin:0"><?= e($r['name']) ?> <?= rarity_chip($r['rarity']) ?></h3>
      <p class="muted" style="margin:4px 0"><?= $ready ? 'Ein passendes Tier wartet auf dich!' : 'Vermittlung in: <b data-countdown="' . (int)$r['secs'] . '" data-reload="1"></b>' ?></p></div>
    <div>
    <?php if ($ready): ?>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="do" value="hatch"><input type="hidden" name="egg" value="<?= $r['id'] ?>">
        <input name="name" placeholder="Name des Tieres" maxlength="30" required style="width:160px"><button class="btn small">Tier abholen</button></form>
    <?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="lamp"><input type="hidden" name="egg" value="<?= $r['id'] ?>"><button class="btn small ghost" <?= $rushQty ? '' : 'disabled' ?>>⏩ Eilvermerk</button></form>
    <?php endif; ?></div>
  </div>
<?php endforeach; ?>
<h2>Für Freunde einsetzen</h2>
<div class="card"><?php if (!$friendReqs): ?><p class="muted">Gerade wartet keiner deiner Freunde auf eine Vermittlung.</p><?php endif; ?>
<ul class="list" style="padding:0"><?php foreach ($friendReqs as $f): ?>
  <li class="row"><span class="av-s"><?= e($f['emoji']) ?></span><span class="grow"><?= e($f['display_name'] ?: $f['username']) ?> · <?= e($f['iname']) ?> <span class="muted">(<span data-countdown="<?= (int)$f['secs'] ?>"></span>)</span></span>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="warm"><input type="hidden" name="egg" value="<?= $f['id'] ?>">
      <button class="btn small <?= $f['helped'] ? 'ghost' : '' ?>" <?= $f['helped'] ? 'disabled' : '' ?>><?= $f['helped'] ? 'Eingesetzt' : '🤝 Fürsprache (+4 🪙)' ?></button></form></li>
<?php endforeach; ?></ul></div>
<?php page_footer();
