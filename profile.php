<?php
require __DIR__ . '/inc/bootstrap.php';
$me = current_user();
$name = trim($_GET['u'] ?? '');
$user = row('SELECT * FROM users WHERE username = ?', [$name]);
if (!$user || ($user['is_banned'] && !($me && $me['is_admin']))) {
    http_response_code(404); page_header('Nicht gefunden'); echo '<div class="card"><h1>Profil nicht gefunden</h1></div>'; page_footer(); exit;
}
$uid = (int)$user['id'];
$isMe = $me && (int)$me['id'] === $uid;
$pets = user_pets($uid);
$friends = rows("SELECT u.username, u.display_name, u.avatar FROM friendships f
                 JOIN users u ON u.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
                 WHERE f.status='accepted' AND (f.requester_id = ? OR f.addressee_id = ?) AND u.is_banned = 0 ORDER BY u.username", [$uid, $uid, $uid]);
$rel = null;
if ($me && !$isMe) {
    $rel = row('SELECT * FROM friendships WHERE (requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?)', [$me['id'], $uid, $uid, $me['id']]);
}
page_header($user['display_name'] ?: $user['username'], $isMe ? 'me' : '');
?>
<div class="card profile-head">
  <div class="avatar"><?= e($user['avatar']) ?></div>
  <div class="grow">
    <h1><?= e($user['display_name'] ?: $user['username']) ?> <?= $user['is_admin'] ? '<span class="chip">Admin</span>' : '' ?></h1>
    <p class="muted">@<?= e($user['username']) ?> · Dabei seit <?= e(date('d.m.Y', strtotime($user['created_at']))) ?> · <?= count($pets) ?> Tier(e) · <?= count($friends) ?> Freund(e)</p>
    <?php if (trim((string)$user['bio']) !== ''): ?><p><?= nl2br(e($user['bio'])) ?></p><?php endif; ?>
    <?php if ($isMe): ?><a class="btn small" href="<?= e(url('settings.php')) ?>">Profil bearbeiten</a>
    <?php elseif ($me): ?>
      <form method="post" action="<?= e(url('friends.php')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="user" value="<?= $uid ?>">
      <?php if (!$rel): ?><button class="btn small" name="do" value="request">Freund hinzufügen</button>
      <?php elseif ($rel['status'] === 'accepted'): ?><span class="chip">Ihr seid befreundet</span>
        <button class="btn ghost small" name="do" value="remove" onclick="return confirm('Freundschaft beenden?')">Entfernen</button>
      <?php elseif ((int)$rel['requester_id'] === (int)$me['id']): ?><span class="chip">Anfrage gesendet</span>
      <?php else: ?><button class="btn small" name="do" value="accept">Anfrage annehmen</button><?php endif; ?>
      </form>
    <?php else: ?><a class="btn small" href="<?= e(url('login.php')) ?>">Anmelden, um Freunde hinzuzufügen</a><?php endif; ?>
  </div>
</div>
<h2>Tiere</h2>
<div class="grid"><?php foreach ($pets as $p) echo pet_card($p); ?><?php if (!$pets): ?><p class="muted">Noch keine Tiere.</p><?php endif; ?></div>
<h2>Freunde</h2>
<div class="card"><?php if (!$friends): ?><p class="muted">Noch keine Freunde.</p><?php endif; ?>
<div class="chips"><?php foreach ($friends as $f): ?><a class="chip big" href="<?= e(url('profile.php?u=' . urlencode($f['username']))) ?>"><?= e($f['avatar']) ?> <?= e($f['display_name'] ?: $f['username']) ?></a><?php endforeach; ?></div></div>
<?php page_footer();
