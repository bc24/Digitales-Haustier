<?php
function nav_dd(string $label, array $items, string $active, string $badge = ''): string {
    $on = false; $h = '';
    foreach ($items as $file => $text) {
        $cur = basename(strtok($file, '?'), '.php') === $active;
        $on = $on || $cur;
        $h .= '<a class="' . ($cur ? 'on' : '') . '" href="' . e(url($file)) . '">' . e($text) . '</a>';
    }
    return '<div class="dd ' . ($on ? 'on' : '') . '"><button type="button" class="dd-btn" aria-haspopup="true">' . e($label) . $badge . ' <small>▾</small></button><div class="dd-menu">' . $h . '</div></div>';
}

function page_header(string $title, string $active = ''): void {
    $u = current_user();
    $site = setting('site_name', 'Digitales Haustier');
    $pending = $u ? (int)val("SELECT COUNT(*) FROM friendships WHERE addressee_id=? AND status='pending'", [$u['id']]) : 0;
    $dailyReady = $u && $u['last_daily'] !== today();
    $active = $active ?: basename($_SERVER['SCRIPT_NAME'], '.php');
    ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#6c5ce7">
<title><?= e($title) ?> – <?= e($site) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=<?= (int)@filemtime(ROOT . '/assets/style.css') ?>">
</head>
<body>
<header class="top">
  <div class="wrap bar-nav">
    <a class="brand" href="<?= e(url('index.php')) ?>"><span>🐾</span> <?= e($site) ?></a>
    <input type="checkbox" id="nav-toggle" hidden>
    <label for="nav-toggle" class="burger" aria-label="Menü">☰</label>
    <nav>
      <?php if ($u): ?>
        <a class="<?= $active === 'dashboard' ? 'on' : '' ?>" href="<?= e(url('dashboard.php')) ?>">Zuhause</a>
        <?= nav_dd('Spielen', ['games.php' => 'Minispiele', 'arena.php' => 'Arena', 'playdate.php' => 'Spieltreffen', 'eggs.php' => 'Brutkasten'], $active) ?>
        <?= nav_dd('Belohnungen', ['daily.php' => 'Tagesbonus & Rad', 'quests.php' => 'Quests & Erfolge'], $active, $dailyReady ? ' <i class="dot"></i>' : '') ?>
        <?= nav_dd('Shop', ['shop.php' => 'Shop', 'inventory.php' => 'Rucksack', 'adopt.php' => 'Tier adoptieren'], $active) ?>
        <?= nav_dd('Community', ['friends.php' => 'Freunde', 'leaderboard.php' => 'Ranglisten', 'users.php' => 'Spieler entdecken'], $active, $pending ? ' <em>' . $pending . '</em>' : '') ?>
        <span class="hud"><a class="pill coin" href="<?= e(url('shop.php')) ?>" title="Münzen">🪙 <?= number_format((int)$u['coins'], 0, ',', '.') ?></a>
          <a class="pill lvl" href="<?= e(url('quests.php')) ?>" title="Spielerlevel">⭐ <?= user_level((int)$u['xp']) ?></a></span>
        <div class="dd right"><button type="button" class="dd-btn"><?= e($u['avatar']) ?> <small>▾</small></button><div class="dd-menu">
          <a href="<?= e(url('profile.php?u=' . urlencode($u['username']))) ?>">Mein Profil</a>
          <a href="<?= e(url('settings.php')) ?>">Einstellungen</a>
          <?php if ($u['is_admin']): ?><a class="admin-link" href="<?= e(url('admin/index.php')) ?>">Admin</a><?php endif; ?>
          <form method="post" action="<?= e(url('logout.php')) ?>" class="logout"><?= csrf_field() ?><button>Abmelden</button></form>
        </div></div>
      <?php else: ?>
        <a href="<?= e(url('leaderboard.php')) ?>">Ranglisten</a>
        <a href="<?= e(url('users.php')) ?>">Entdecken</a>
        <a href="<?= e(url('login.php')) ?>">Anmelden</a>
        <a class="btn small" href="<?= e(url('register.php')) ?>">Registrieren</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php foreach (flashes() as [$t, $m]): ?><div class="flash <?= e($t) ?>"><?= e($m) ?></div><?php endforeach;
}

function page_footer(): void {
    ?></main>
<footer class="foot">
  <div class="wrap">2026 by. <a href="https://Frank-Panzer.de" target="_blank" rel="noopener">Frank Panzer</a> - Entwickelt von <a href="https://panzerit.de" target="_blank" rel="noopener">Panzer IT</a>
  <div class="legal"><a href="<?= e(url('impressum.php')) ?>">Impressum</a> · <a href="<?= e(url('datenschutz.php')) ?>">Datenschutz</a></div></div>
</footer>
<script src="<?= e(url('assets/app.js')) ?>?v=<?= (int)@filemtime(ROOT . '/assets/app.js') ?>"></script>
</body>
</html>
<?php
}

function pet_card(array $p, bool $link = true): string {
    [$mood, $face] = pet_mood($p);
    return '<a class="pet-card" style="--c:' . e($p['color']) . '" href="' . e(url('pet.php?id=' . $p['id'])) . '">'
       . '<div class="pet-emoji">' . pet_visual($p) . '</div>'
       . '<h3>' . e($p['name']) . ((int)$p['hue'] ? ' ✨' : '') . '</h3>'
       . '<p class="muted">' . e($p['species']) . ' · Level ' . pet_level($p) . '</p>'
       . '<span class="chip">' . $face . ' ' . e($mood) . '</span>' . rarity_chip($p['rarity']) . '</a>';
}

function progress_bar(float $pct, string $cls = 'b-love'): string {
    return '<div class="bar"><i class="' . $cls . '" style="width:' . max(0, min(100, round($pct))) . '%"></i></div>';
}
