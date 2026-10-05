<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Berlin');

// Basis-URL (Unterordner-Installation) automatisch ermitteln
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$base = '';
if ($docRoot && str_starts_with(ROOT, $docRoot)) {
    $base = str_replace('\\', '/', substr(ROOT, strlen($docRoot)));
}
define('BASE', rtrim($base, '/'));

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

require __DIR__ . '/helpers.php';

if (!is_file(ROOT . '/config.local.php') && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    redirect('install.php');
}
if (is_file(ROOT . '/config.local.php')) {
    require __DIR__ . '/db.php';
    require __DIR__ . '/migrate.php';
    migrate($pdo);
    require __DIR__ . '/economy.php';
    require __DIR__ . '/game.php';
    require __DIR__ . '/layout.php';
}
