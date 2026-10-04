<?php
require __DIR__ . '/inc/bootstrap.php';
$u = current_user();
if ($u) redirect('dashboard.php');
$species = rows('SELECT * FROM species WHERE active = 1 ORDER BY id');
$stats = [
    'Spieler' => (int)val('SELECT COUNT(*) FROM users'),
    'Tiere' => (int)val('SELECT COUNT(*) FROM pets'),
    'Freundschaften' => (int)val("SELECT COUNT(*) FROM friendships WHERE status='accepted'"),
];
page_header('Willkommen');
?>
<section class="hero">
  <h1>Dein eigenes digitales Haustier</h1>
  <p>Wähle ein Tier, füttere, spiele und kümmere dich. Gewinne sein Herz, finde Freunde und lass eure Tiere miteinander spielen – oder streiten.</p>
  <div class="row center"><a class="btn big" href="<?= e(url('register.php')) ?>">Jetzt kostenlos starten</a> <a class="btn ghost big" href="<?= e(url('login.php')) ?>">Anmelden</a></div>
  <div class="stats-row"><?php foreach ($stats as $k => $n): ?><div><b><?= $n ?></b><span><?= e($k) ?></span></div><?php endforeach; ?></div>
</section>
<h2>Diese Tiere warten auf dich</h2>
<div class="grid">
<?php foreach ($species as $s): ?>
  <div class="pet-card static" style="--c:<?= e($s['color']) ?>">
    <div class="pet-emoji"><?= e($s['emoji']) ?></div>
    <h3><?= e($s['name']) ?></h3>
    <p class="muted"><?= e($s['description']) ?></p>
    <span class="chip"><?= e($s['temperament']) ?></span>
  </div>
<?php endforeach; ?>
</div>
<?php page_footer();
