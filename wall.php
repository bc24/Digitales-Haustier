<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
require_post();
$uid = (int)$u['id'];
$profile = row('SELECT id, username FROM users WHERE id=? AND is_banned=0', [(int)($_POST['profile'] ?? 0)]);
if (!$profile) { flash('Profil nicht gefunden.', 'err'); redirect('dashboard.php'); }
$pid = (int)$profile['id'];
$back = 'profile.php?u=' . urlencode($profile['username']) . '#wall';
if (($_POST['do'] ?? '') === 'post') {
    $body = trim(preg_replace('/\s+/u', ' ', $_POST['body'] ?? ''));
    if ($pid !== $uid && !are_friends($uid, $pid)) flash('Nur Freunde können auf die Pinnwand schreiben.', 'err');
    elseif (mb_strlen($body) < 1 || mb_strlen($body) > 300) flash('Die Nachricht muss 1-300 Zeichen lang sein.', 'err');
    elseif (throttle_count('wall', (string)$uid, 60) >= 15) flash('Zu viele Nachrichten. Bitte warte etwas.', 'err');
    else {
        throttle_hit('wall', (string)$uid);
        q('INSERT INTO profile_posts (profile_id, author_id, body, created_at) VALUES (?,?,?,NOW())', [$pid, $uid, $body]);
        if ($pid !== $uid) log_activity($pid, null, $u['username'] . ' hat auf deine Pinnwand geschrieben.');
        flash('Nachricht gepostet.');
    }
} elseif (($_POST['do'] ?? '') === 'delete') {
    $post = row('SELECT * FROM profile_posts WHERE id=? AND profile_id=?', [(int)($_POST['post'] ?? 0), $pid]);
    if ($post && ($u['is_admin'] || $pid === $uid || (int)$post['author_id'] === $uid)) { q('DELETE FROM profile_posts WHERE id=?', [$post['id']]); flash('Nachricht gelöscht.'); }
}
redirect($back);
