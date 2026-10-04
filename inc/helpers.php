<?php
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Ungültige Anfrage (CSRF).');
    }
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = row('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!$user || $user['is_banned']) { $user = null; unset($_SESSION['uid']); }
        }
    }
    return $user;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('Bitte melde dich zuerst an.', 'err'); redirect('login.php'); }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if (!$u['is_admin']) { http_response_code(403); exit('Kein Zugriff.'); }
    return $u;
}

function time_ago(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'gerade eben';
    if ($d < 3600) return 'vor ' . floor($d / 60) . ' Min.';
    if ($d < 86400) return 'vor ' . floor($d / 3600) . ' Std.';
    return 'vor ' . floor($d / 86400) . ' Tagen';
}
function clamp(float $v, float $min = 0, float $max = 100): float { return max($min, min($max, $v)); }
