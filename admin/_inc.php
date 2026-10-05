<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

function admin_header(string $title, string $active): void {
    page_header('Admin: ' . $title);
    $items = ['index' => 'Übersicht', 'users' => 'Benutzer', 'pets' => 'Tiere', 'species' => 'Arten', 'items' => 'Shop-Items', 'relations' => 'Beziehungen', 'settings' => 'Einstellungen'];
    echo '<div class="subnav">';
    foreach ($items as $k => $l) echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . e(url("admin/$k.php")) . '">' . e($l) . '</a>';
    echo '</div><h1>' . e($title) . '</h1>';
}
