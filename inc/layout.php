<?php
function page_header(string $title, string $active = ''): void {
    $u = current_user();
    $site = setting('site_name', 'Digitales Haustier');
    $pending = $u ? (int)val("SELECT COUNT(*) FROM friendships WHERE addressee_id=? AND status='pending'", [$u['id']]) : 0;
    ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> – <?= e($site) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="top">
  <div class="wrap bar-nav">
    <a class="brand" href="<?= e(url('index.php')) ?>"><span>🐾</span> <?= e($site) ?></a>
    <input type="checkbox" id="nav-toggle" hidden>
    <label for="nav-toggle" class="burger" aria-label="Menü">☰</label>
    <nav>
      <?php if ($u): ?>
        <a class="<?= $active === 'home' ? 'on' : '' ?>" href="<?= e(url('dashboard.php')) ?>">Meine Tiere</a>
        <a class="<?= $active === 'adopt' ? 'on' : '' ?>" href="<?= e(url('adopt.php')) ?>">Adoptieren</a>
        <a class="<?= $active === 'play' ? 'on' : '' ?>" href="<?= e(url('playdate.php')) ?>">Spieltreffen</a>
        <a class="<?= $active === 'friends' ? 'on' : '' ?>" href="<?= e(url('friends.php')) ?>">Freunde<?= $pending ? ' <em>' . $pending . '</em>' : '' ?></a>
        <a class="<?= $active === 'users' ? 'on' : '' ?>" href="<?= e(url('users.php')) ?>">Entdecken</a>
        <a class="<?= $active === 'me' ? 'on' : '' ?>" href="<?= e(url('profile.php?u=' . urlencode($u['username']))) ?>">Profil</a>
        <?php if ($u['is_admin']): ?><a class="admin-link" href="<?= e(url('admin/index.php')) ?>">Admin</a><?php endif; ?>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="logout"><?= csrf_field() ?><button>Abmelden</button></form>
      <?php else: ?>
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
</body>
</html>
<?php
}

function pet_card(array $p, bool $link = true): string {
    [$mood, $face] = pet_mood($p);
    $h = '<a class="pet-card" style="--c:' . e($p['color']) . '" href="' . e(url('pet.php?id=' . $p['id'])) . '">'
       . '<div class="pet-emoji">' . e($p['emoji']) . '</div>'
       . '<h3>' . e($p['name']) . '</h3>'
       . '<p class="muted">' . e($p['species']) . ' · Level ' . pet_level($p) . '</p>'
       . '<span class="chip">' . $face . ' ' . e($mood) . '</span></a>';
    return $h;
}
