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

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45); }

// Einfache Bremse gegen Brute-Force und Spam
function throttle_count(string $kind, string $ident, int $minutes): int {
    return (int)val('SELECT COUNT(*) FROM throttle WHERE kind=? AND ident=? AND created_at > (NOW() - INTERVAL ? MINUTE)', [$kind, mb_strtolower($ident), $minutes]);
}
function throttle_hit(string $kind, string $ident): void {
    q('INSERT INTO throttle (kind, ident, created_at) VALUES (?,?,NOW())', [$kind, mb_strtolower($ident)]);
    if (random_int(1, 50) === 1) q('DELETE FROM throttle WHERE created_at < (NOW() - INTERVAL 2 DAY)');
}

function site_base_url(): string {
    $u = rtrim(setting('site_url', ''), '/');
    if ($u !== '') return $u;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) $host = 'localhost';
    return (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $host . BASE;
}
function send_mail(string $to, string $subject, string $body): bool {
    $from = setting('mail_from', '');
    if ($from === '') $from = 'noreply@' . preg_replace('/:\d+$/', '', parse_url(site_base_url(), PHP_URL_HOST) ?: 'localhost');
    $headers = "From: " . setting('site_name', 'Digitales Haustier') . " <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
