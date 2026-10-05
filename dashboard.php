<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$pets = user_pets($uid);
$max = max_pets_for($u);
$news = rows('SELECT a.*, p.name AS pet_name FROM activity a LEFT JOIN pets p ON p.id = a.pet_id WHERE a.user_id = ? ORDER BY a.id DESC LIMIT 8', [$uid]);
$requests = (int)val("SELECT COUNT(*) FROM friendships WHERE addressee_id=? AND status='pending'", [$uid]);
$quests = quests_for($uid);
$claimable = count(array_filter($quests, fn($q) => !$q['claimed'] && $q['progress'] >= $q['target']));
$eggsReady = (int)val('SELECT COUNT(*) FROM eggs WHERE user_id=? AND hatch_at <= NOW()', [$uid]);
$eggsWait = (int)val('SELECT COUNT(*) FROM eggs WHERE user_id=? AND hatch_at > NOW()', [$uid]);
$giftsWaiting = (int)val('SELECT COUNT(*) FROM gifts WHERE to_id=? AND claimed=0', [$uid]);
$dailyReady = $u['last_daily'] !== today();
$online = rows("SELECT u.username, u.display_name, u.avatar FROM friendships f JOIN users u ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
                WHERE f.status='accepted' AND (f.requester_id=? OR f.addressee_id=?) AND u.last_seen > (NOW() - INTERVAL 5 MINUTE) LIMIT 8", [$uid, $uid, $uid]);
[$lvl, $cur, $need, $pct] = xp_progress($u);
$needy = array_filter($pets, fn($p) => pet_mood($p)[2] === 'sad' || pet_mood($p)[2] === 'sick');
page_header('Zuhause', 'dashboard');
?>
<div class="card hud-card">
  <div class="lv"><?= $lvl ?><small class="muted" style="display:block;font-size:.75rem;font-weight:700">LEVEL</small></div>
  <div><b><?= e($u['display_name'] ?: $u['username']) ?></b> <span class="muted">· <?= (int)$cur ?> / <?= (int)$need ?> XP</span>
    <?= progress_bar((float)$pct, 'b-love') ?>
    <div class="toast-row" style="margin-top:8px"><span class="pill coin">🪙 <?= number_format((int)$u['coins'], 0, ',', '.') ?></span>
      <span class="pill lvl">🔥 Streak: <?= (int)$u['streak'] ?></span><span class="pill lvl">🏆 <?= (int)$u['trophies'] ?></span></div></div>
  <div><?php if ($dailyReady): ?><a class="btn" href="<?= e(url('daily.php')) ?>">🎁 Tagesbonus abholen</a><?php else: ?><span class="chip">Tagesbonus abgeholt</span><?php endif; ?></div>
</div>

<?php if ($requests): ?><div class="flash ok">Du hast <?= $requests ?> offene Freundschaftsanfrage(n). <a href="<?= e(url('friends.php')) ?>">Ansehen</a></div><?php endif; ?>
<?php if ($eggsReady): ?><div class="flash ok">🥚 <?= $eggsReady ?> Ei(er) bereit zum Ausbrüten! <a href="<?= e(url('eggs.php')) ?>">Zum Brutkasten</a></div><?php endif; ?>
<?php if ($giftsWaiting): ?><div class="flash ok">🎁 <?= $giftsWaiting ?> Geschenk(e) von Freunden warten auf dich. <a href="<?= e(url('daily.php')) ?>">Abholen</a></div><?php endif; ?>
<?php if ($claimable): ?><div class="flash ok">✅ <?= $claimable ?> Quest(s) erledigt, Belohnung wartet. <a href="<?= e(url('quests.php')) ?>">Abholen</a></div><?php endif; ?>
<?php if ($needy): ?><div class="flash err">Manche Tiere brauchen dich: <?= e(implode(', ', array_map(fn($p) => $p['name'], $needy))) ?></div><?php endif; ?>

<h2>Meine Tiere (<?= count($pets) ?>/<?= $max ?>)</h2>
<div class="grid">
<?php foreach ($pets as $p) echo pet_card($p); ?>
<?php if (count($pets) < $max): ?>
  <a class="pet-card add" href="<?= e(url('adopt.php')) ?>"><div class="pet-emoji">＋</div><h3>Tier adoptieren</h3></a>
<?php endif; ?>
</div>

<div class="two" style="margin-top:8px">
  <div class="card"><h3>Tagesquests</h3>
    <?php foreach ($quests as $q): ?>
      <div class="quest <?= $q['claimed'] ? 'done' : '' ?>"><span class="q-label"><?= e($q['label']) ?></span><span class="reward">🪙 <?= $q['coins'] ?></span>
        <?= progress_bar($q['progress'] / $q['target'] * 100, 'b-energy') ?></div>
    <?php endforeach; ?>
    <a class="btn small ghost" href="<?= e(url('quests.php')) ?>">Alle Quests &amp; Erfolge</a></div>
  <div class="card"><h3>Schnellzugriff</h3>
    <div class="actions">
      <a class="act" href="<?= e(url('games.php')) ?>">🎮 Minispiele</a>
      <a class="act" href="<?= e(url('arena.php')) ?>">🏟️ Arena</a>
      <a class="act" href="<?= e(url('eggs.php')) ?>">🥚 Brutkasten<?= $eggsWait ? " ($eggsWait)" : '' ?></a>
      <a class="act" href="<?= e(url('shop.php')) ?>">🛍️ Shop</a>
    </div>
    <?php if ($online): ?><h3 style="margin-top:16px">Freunde online</h3><div class="chips"><?php foreach ($online as $f): ?><a class="chip big" href="<?= e(url('profile.php?u=' . urlencode($f['username']))) ?>"><i class="online"></i><?= e($f['avatar'] . ' ' . ($f['display_name'] ?: $f['username'])) ?></a><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>

<h2>Neuigkeiten</h2>
<div class="card">
<?php if (!$news): ?><p class="muted">Noch nichts passiert.</p><?php endif; ?>
<ul class="feed"><?php foreach ($news as $n): ?><li><?= e($n['message']) ?> <span class="muted"><?= e(time_ago($n['created_at'])) ?></span></li><?php endforeach; ?></ul>
</div>
<?php page_footer();
