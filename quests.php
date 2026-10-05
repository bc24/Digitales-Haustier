<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $quests = quests_for($uid);
    if (($_POST['do'] ?? '') === 'claim') {
        foreach ($quests as $qst) {
            if ($qst['key'] !== ($_POST['key'] ?? '')) continue;
            if ($qst['claimed'] || $qst['progress'] < $qst['target']) { flash('Quest noch nicht abschließbar.', 'err'); break; }
            if (q('INSERT IGNORE INTO daily (user_id, day, k, v) VALUES (?,CURDATE(),?,1)', [$uid, 'claimed_' . $qst['key']])->rowCount() === 0) break;
            $c = award($uid, $qst['coins'], $qst['xp']);
            flash("Quest abgeschlossen: +$c Münzen, +{$qst['xp']} XP");
        }
    } elseif (($_POST['do'] ?? '') === 'chest') {
        $all = count(array_filter($quests, fn($x) => $x['claimed'])) === 3;
        if (!$all) flash('Schließe zuerst alle drei Quests ab.', 'err');
        elseif (q("INSERT IGNORE INTO daily (user_id, day, k, v) VALUES (?,CURDATE(),'chest',1)", [$uid])->rowCount() === 0) flash('Die Truhe hast du heute schon geöffnet.', 'err');
        else {
            $c = award($uid, 60, 20); $msg = "Tagestruhe: +$c Münzen";
            if (random_int(1, 100) <= 25 && (int)val('SELECT COUNT(*) FROM eggs WHERE user_id=?', [$uid]) < 3) { give_egg($uid, (int)item_by_name('Standard-Ei')['id']); $msg .= ' und ein Ei!'; }
            else { add_item($uid, (int)item_by_name('Gourmet-Menü')['id']); $msg .= ' und ein Gourmet-Menü!'; }
            flash($msg);
        }
    }
    redirect('quests.php');
}
$quests = quests_for($uid);
$allDone = count(array_filter($quests, fn($x) => $x['claimed'])) === 3;
$chestOpen = daily_get($uid, 'chest') > 0;
$have = array_column(rows('SELECT akey, unlocked_at FROM user_achievements WHERE user_id=?', [$uid]), 'unlocked_at', 'akey');
[$lvl, $cur, $need, $pct] = xp_progress($u);
page_header('Quests & Erfolge', 'quests');
?>
<h1>Quests &amp; Erfolge</h1>
<div class="card"><b>Spielerlevel <?= $lvl ?></b> <span class="muted">· <?= $cur ?> / <?= $need ?> XP bis Level <?= $lvl + 1 ?> (Level-Up bringt <?= 25 * ($lvl + 1) ?> Münzen)</span><?= progress_bar((float)$pct) ?></div>
<h2>Heutige Quests</h2>
<div class="card">
<?php foreach ($quests as $qst): $ready = !$qst['claimed'] && $qst['progress'] >= $qst['target']; ?>
  <div class="quest <?= $qst['claimed'] ? 'done' : '' ?>">
    <span class="q-label"><?= e($qst['label']) ?> – <?= $qst['progress'] ?>/<?= $qst['target'] ?> <span class="reward">🪙 <?= $qst['coins'] ?></span> <span class="reward">⭐ <?= $qst['xp'] ?> XP</span></span>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="claim"><input type="hidden" name="key" value="<?= e($qst['key']) ?>">
      <button class="btn small <?= $ready ? '' : 'ghost' ?>" <?= $ready ? '' : 'disabled' ?>><?= $qst['claimed'] ? 'Erledigt' : ($ready ? 'Abholen' : 'Offen') ?></button></form>
    <?= progress_bar($qst['progress'] / $qst['target'] * 100, 'b-energy') ?>
  </div>
<?php endforeach; ?>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="do" value="chest">
    <button class="btn" <?= $allDone && !$chestOpen ? '' : 'disabled style="opacity:.5"' ?>>🎁 Tagestruhe <?= $chestOpen ? '(geöffnet)' : ($allDone ? 'öffnen' : '(alle 3 Quests nötig)') ?></button></form>
</div>
<h2>Erfolge (<?= count($have) ?>/<?= count(ACHIEVEMENTS) ?>)</h2>
<div class="ach">
<?php foreach (ACHIEVEMENTS as $key => [$icon, $name, $desc, $stat, $target, $coins]): $done = isset($have[$key]); $val = $done ? $target : min($target, achievement_value($uid, $stat)); ?>
  <div><span class="ai <?= $done ? '' : 'lock' ?>"><?= $icon ?></span><b><?= e($name) ?> <span class="reward">🪙 <?= $coins ?></span></b>
    <p><?= e($desc) ?> (<?= $val ?>/<?= $target ?>)</p></div>
<?php endforeach; ?>
</div>
<?php page_footer();
