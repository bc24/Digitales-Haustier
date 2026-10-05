<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
const WHEEL = [
    ['🪙 10 Münzen', 'coins', 10, 28], ['🪙 25 Münzen', 'coins', 25, 24], ['🪙 50 Münzen', 'coins', 50, 14], ['🪙 150 Münzen', 'coins', 150, 4],
    ['🍎 Apfel', 'item', 'Apfel', 10], ['🍰 Torte', 'item', 'Torte', 8], ['🍪 Zauberkeks', 'item', 'Zauberkeks', 6],
    ['🛡️ Streak-Schutz', 'item', 'Streak-Schutz', 4], ['🥚 Standard-Ei', 'egg', 'Standard-Ei', 2],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $do = $_POST['do'] ?? '';
    if ($do === 'claim') {
        $last = $u['last_daily'];
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $note = '';
        if ($last === today()) { flash('Den Tagesbonus hast du heute schon abgeholt.', 'err'); redirect('daily.php'); }
        if ($last === $yesterday) $streak = (int)$u['streak'] + 1;
        elseif ($last === date('Y-m-d', strtotime('-2 days')) && (int)$u['streak'] > 0 && ($f = item_by_name('Streak-Schutz')) && take_item($uid, (int)$f['id'])) { $streak = (int)$u['streak'] + 1; $note = ' Dein Streak-Schutz hat deine Serie gerettet.'; }
        else $streak = 1;
        $claimed = q('UPDATE users SET streak=?, best_streak=GREATEST(best_streak, ?), last_daily=? WHERE id=? AND (last_daily IS NULL OR last_daily < ?)', [$streak, $streak, today(), $uid, today()])->rowCount();
        if (!$claimed) { redirect('daily.php'); }
        $d = (($streak - 1) % 7) + 1;
        $coins = award($uid, 20 + 10 * $d, 10 + 2 * min($streak, 30));
        $msg = "Tag $streak: +$coins Münzen.$note";
        if ($d === 7) {
            award($uid, 100, 30);
            if (random_int(1, 100) <= 40 && (int)val('SELECT COUNT(*) FROM eggs WHERE user_id=?', [$uid]) < 3) { give_egg($uid, (int)item_by_name('Standard-Ei')['id']); $msg .= ' Wochentruhe: +100 Münzen und ein Ei!'; }
            else { add_item($uid, (int)item_by_name('Zauberkeks')['id']); $msg .= ' Wochentruhe: +100 Münzen und ein Zauberkeks!'; }
        }
        check_achievements($uid);
        flash($msg);
    } elseif ($do === 'spin') {
        if (daily_get($uid, 'spin') > 0) { flash('Du hast heute schon gedreht.', 'err'); redirect('daily.php'); }
        daily_add($uid, 'spin');
        $total = array_sum(array_column(WHEEL, 3)); $roll = random_int(1, $total); $prize = WHEEL[0];
        foreach (WHEEL as $w) { if ($roll <= $w[3]) { $prize = $w; break; } $roll -= $w[3]; }
        [$label, $type, $arg] = $prize;
        if ($type === 'coins') award($uid, $arg, 3);
        elseif ($type === 'item') add_item($uid, (int)item_by_name($arg)['id']);
        else {
            if ((int)val('SELECT COUNT(*) FROM eggs WHERE user_id=?', [$uid]) < 3) give_egg($uid, (int)item_by_name($arg)['id']);
            else { award($uid, 100); $label = '🪙 100 Münzen (Brutkasten voll)'; }
        }
        $_SESSION['spin'] = $label;
        flash("Glücksrad: $label");
    } elseif ($do === 'gift') {
        $to = (int)($_POST['user'] ?? 0);
        if (!are_friends($uid, $to)) flash('Nur an Freunde.', 'err');
        elseif (q('INSERT IGNORE INTO gifts (from_id, to_id, day) VALUES (?,?,CURDATE())', [$uid, $to])->rowCount() === 0) flash('Du hast diesem Freund heute schon ein Geschenk geschickt.', 'err');
        else {
            track($uid, 'gift');
            $c = award($uid, 5, 3);
            log_activity($to, null, $u['username'] . ' hat dir ein Geschenk geschickt. Hol es dir unter Belohnungen!');
            flash("Geschenk gesendet! +$c Münzen für dich.");
        }
    } elseif ($do === 'claim_gifts') {
        $left = max(0, 10 - daily_get($uid, 'gift_claim'));
        $got = 0;
        foreach (rows('SELECT id FROM gifts WHERE to_id=? AND claimed=0 ORDER BY id LIMIT ' . (int)$left, [$uid]) as $g) {
            if (q('UPDATE gifts SET claimed=1 WHERE id=? AND claimed=0', [$g['id']])->rowCount() === 1) {
                $got++; award($uid, 15, 2);
                if (random_int(1, 100) <= 30) add_item($uid, (int)item_by_name('Apfel')['id']);
            }
        }
        daily_add($uid, 'gift_claim', $got);
        flash($got ? "$got Geschenk(e) abgeholt: +" . $got * 15 . ' Münzen' : 'Keine Geschenke zum Abholen (oder Tageslimit erreicht).', $got ? 'ok' : 'err');
    }
    redirect('daily.php');
}
$u = row('SELECT * FROM users WHERE id=?', [$uid]);
$ready = $u['last_daily'] !== today();
$curStreak = (int)$u['streak'];
if ($u['last_daily'] !== today() && $u['last_daily'] !== date('Y-m-d', strtotime('-1 day'))) $curStreak = $curStreak > 0 && $u['last_daily'] === date('Y-m-d', strtotime('-2 days')) ? $curStreak : 0;
$spun = daily_get($uid, 'spin') > 0;
$gifts = (int)val('SELECT COUNT(*) FROM gifts WHERE to_id=? AND claimed=0', [$uid]);
$friends = rows("SELECT u.id, u.username, u.display_name, u.avatar, (SELECT 1 FROM gifts g WHERE g.from_id=? AND g.to_id=u.id AND g.day=CURDATE()) AS sent
                 FROM friendships f JOIN users u ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
                 WHERE f.status='accepted' AND (f.requester_id=? OR f.addressee_id=?) AND u.is_banned=0 ORDER BY u.username", [$uid, $uid, $uid, $uid]);
$nextDay = $ready ? $curStreak + 1 : (int)$u['streak'];
$pos = $ready ? (($nextDay - 1) % 7) + 1 : (((int)$u['streak'] - 1) % 7) + 1;
page_header('Tagesbonus', 'daily');
?>
<h1>Tagesbonus</h1>
<div class="card">
  <h3>🔥 Serie: <?= (int)$u['streak'] ?> Tag(e) <span class="muted" style="font-weight:400">· Rekord: <?= (int)$u['best_streak'] ?></span></h3>
  <div class="streak"><?php for ($i = 1; $i <= 7; $i++): $hit = $ready ? $i < $pos : $i <= $pos; ?>
    <div class="<?= $hit ? 'hit' : '' ?> <?= $i === 7 ? 'chest' : '' ?>">Tag <?= $i ?><b><?= $i === 7 ? '🎁' : '🪙' ?></b><?= 20 + 10 * $i ?><?= $i === 7 ? ' + Truhe' : '' ?></div>
  <?php endfor; ?></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="claim">
    <button class="btn big" <?= $ready ? '' : 'disabled style="opacity:.5"' ?>><?= $ready ? '🎁 Tagesbonus abholen' : 'Heute schon abgeholt – komm morgen wieder!' ?></button></form>
  <p class="muted">Verpasst du einen Tag, bricht die Serie ab. Ein <a href="<?= e(url('shop.php?tab=boost')) ?>">Streak-Schutz</a> rettet sie einmalig.</p>
</div>

<div class="two">
  <div class="card center"><h3>🎡 Tägliches Glücksrad</h3>
    <?php if (!empty($_SESSION['spin'])): ?><div class="spin"><?= e($_SESSION['spin']) ?></div><?php unset($_SESSION['spin']); endif; ?>
    <p class="muted">Einmal pro Tag kostenlos drehen. Gewinne Münzen, Items oder ein Ei.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="spin"><button class="btn" <?= $spun ? 'disabled style="opacity:.5"' : '' ?>><?= $spun ? 'Heute schon gedreht' : 'Rad drehen' ?></button></form>
  </div>
  <div class="card"><h3>🎁 Geschenke von Freunden</h3>
    <p><?= $gifts ? "<b>$gifts</b> Geschenk(e) warten auf dich." : 'Keine neuen Geschenke.' ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="claim_gifts"><button class="btn" <?= $gifts ? '' : 'disabled style="opacity:.5"' ?>>Abholen</button></form>
  </div>
</div>

<h2>Freunden ein Geschenk schicken</h2>
<div class="card"><p class="muted">Pro Freund und Tag ein Geschenk. Du bekommst 5 Münzen, dein Freund 15.</p>
  <?php if (!$friends): ?><p class="muted">Du hast noch keine Freunde. <a href="<?= e(url('users.php')) ?>">Spieler entdecken</a></p><?php endif; ?>
  <ul class="list" style="padding:0"><?php foreach ($friends as $f): ?>
    <li class="row"><span class="av-s"><?= e($f['avatar']) ?></span><a class="grow" href="<?= e(url('profile.php?u=' . urlencode($f['username']))) ?>"><?= e($f['display_name'] ?: $f['username']) ?></a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="gift"><input type="hidden" name="user" value="<?= $f['id'] ?>">
        <button class="btn small <?= $f['sent'] ? 'ghost' : '' ?>" <?= $f['sent'] ? 'disabled' : '' ?>><?= $f['sent'] ? 'Gesendet' : '🎁 Senden' ?></button></form></li>
  <?php endforeach; ?></ul></div>
<?php page_footer();
