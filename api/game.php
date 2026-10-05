<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function jerr(string $m, int $code = 400): never { http_response_code($code); echo json_encode(['error' => $m]); exit; }

$u = current_user();
if (!$u) jerr('Bitte anmelden.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) jerr('Ungültige Anfrage.', 403);
$uid = (int)$u['id'];
$in = json_decode(file_get_contents('php://input'), true) ?: [];

const GAMES = [
    'catch'  => ['min' => 27, 'max' => 90],
    'memory' => ['min' => 8, 'max' => 900],
    'whack'  => ['min' => 27, 'max' => 90],
];

if (($in['do'] ?? '') === 'start') {
    $g = (string)($in['game'] ?? '');
    if (!isset(GAMES[$g])) jerr('Unbekanntes Spiel.');
    if (throttle_count('mg_start', (string)$uid, 60) >= 60) jerr('Zu viele Spiele in kurzer Zeit. Mach eine kleine Pause.', 429);
    throttle_hit('mg_start', (string)$uid);
    $token = bin2hex(random_bytes(16));
    q('INSERT INTO minigame_runs (user_id, game, token, started_at, week) VALUES (?,?,?,NOW(), YEARWEEK(NOW(), 3))', [$uid, $g, $token]);
    echo json_encode(['token' => $token]);
    exit;
}

if (($in['do'] ?? '') === 'finish') {
    $run = row('SELECT *, TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed FROM minigame_runs WHERE token=? AND user_id=? AND finished=0', [(string)($in['token'] ?? ''), $uid]);
    if (!$run) jerr('Ungültiger oder bereits beendeter Durchlauf.');
    if (q('UPDATE minigame_runs SET finished=1 WHERE id=? AND finished=0', [$run['id']])->rowCount() !== 1) jerr('Bereits beendet.');
    $g = $run['game']; $el = (int)$run['elapsed']; $cfg = GAMES[$g];
    if ($el < $cfg['min'] || $el > $cfg['max']) jerr('Ergebnis nicht plausibel.');
    $score = 0; $coins = 0;
    if ($g === 'catch') {
        $score = max(0, min(70, (int)($in['score'] ?? 0)));
        $coins = min(40, (int)floor($score * 0.4));
    } elseif ($g === 'memory') {
        $moves = max(8, min(200, (int)($in['moves'] ?? 8)));
        $score = max(0, 1000 - 4 * $el - 12 * ($moves - 8));
        $coins = min(35, (int)floor($score / 30));
    } else {
        $hits = max(0, min(45, (int)($in['hits'] ?? 0))); $miss = max(0, min(200, (int)($in['misses'] ?? 0)));
        $score = max(0, $hits * 10 - $miss * 4);
        $coins = min(30, (int)floor($score / 18));
    }
    q('UPDATE minigame_runs SET score=? WHERE id=?', [$score, $run['id']]);
    $remaining = max(0, (int)setting('minigame_daily_cap', '200') - daily_get($uid, 'mg_coins'));
    $got = $coins > 0 && $remaining > 0 ? award($uid, min($coins, $remaining), (int)floor($score / 40) + 3) : award($uid, 0, 2);
    daily_add($uid, 'mg_coins', $got);
    track($uid, 'minigame');
    $best = (int)val('SELECT MAX(score) FROM minigame_runs WHERE user_id=? AND game=? AND week=? AND finished=1', [$uid, $g, $run['week']]);
    echo json_encode(['score' => $score, 'coins' => $got, 'capped' => $remaining <= 0, 'best' => $best, 'newBest' => $score >= $best && $score > 0]);
    exit;
}
jerr('Unbekannte Aktion.');
