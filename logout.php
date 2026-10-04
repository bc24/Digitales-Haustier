<?php
require __DIR__ . '/inc/bootstrap.php';
require_post();
$_SESSION = [];
session_destroy();
session_start();
flash('Du wurdest abgemeldet.');
redirect('index.php');
