<?php
/** @var string $csrfToken */
/** @var ?string $flash */
/** @var array{status:string,message:?string,fetched_at:string}|null $eventLog */
/** @var array{status:string,message:?string,fetched_at:string}|null $mediaLog */
/** @var array<string,mixed> $overrides */
/** @var array<string,string> $overridableFields */
/** @var string $eventSlug */
/** @var string $logoutUrl */
/** @var string $refreshUrl */
/** @var string $overrideUrl */
/** @var string $settingsUrl */
/** @var string $purgeCacheUrl */
/** @var string $offersUrl */
use CampBuddy\Support\View;

$statusPill = static function (?array $log): string {
    if ($log === null) {
        return '<span style="color:#888">never run</span>';
    }
    $color = $log['status'] === 'success' ? '#1a7f37' : '#a4262c';
    return '<span style="color:' . $color . '">' . View::e($log['status']) . '</span> · ' . View::e($log['fetched_at']) . ' UTC'
        . ($log['message'] ? '<br><small style="color:#888">' . View::e($log['message']) . '</small>' : '');
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CampBuddy Admin</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    body{font-family:system-ui,sans-serif;background:#f4f2ef;margin:0;color:#222}
    header{background:#c33a19;color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
    header a{color:#fff}
    header nav a{text-decoration:none;margin-right:1rem;font-size:.9rem;opacity:.9}
    header nav a:hover{opacity:1;text-decoration:underline}
    main{max-width:760px;margin:1.5rem auto;padding:0 1rem}
    .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:1.25rem 1.5rem;margin-bottom:1.25rem}
    h2{font-size:1rem;margin:0 0 .75rem}
    label{display:block;font-size:.85rem;margin:.6rem 0 .25rem;color:#444}
    input{width:100%;padding:.5rem .6rem;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;font-size:.95rem}
    button{padding:.55rem 1.1rem;border:0;border-radius:6px;background:#c33a19;color:#fff;font-size:.95rem;cursor:pointer}
    button.btn--danger{background:#a4262c}
    .flash{background:#eaf6ec;color:#1a7f37;padding:.6rem .75rem;border-radius:6px;margin-bottom:1rem}
    table{width:100%;border-collapse:collapse}
    td{padding:.35rem 0;vertical-align:top}
    td:first-child{width:110px;color:#666}
  </style>
</head>
<body>
  <header>
    <strong>CampBuddy Admin</strong>
    <div style="display:flex;align-items:center;gap:1rem">
      <nav><a href="<?= View::e($offersUrl) ?>">Offers</a></nav>
      <form method="post" action="<?= View::e($logoutUrl) ?>" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" style="background:transparent;border:1px solid #fff">Log out</button>
      </form>
    </div>
  </header>
  <main>
    <?php if (!empty($flash)): ?><div class="flash"><?= View::e($flash) ?></div><?php endif; ?>

    <div class="card">
      <h2>Auto-fetch status</h2>
      <table>
        <tr><td>Event data</td><td><?= $statusPill($eventLog) ?></td></tr>
        <tr><td>Explore videos</td><td><?= $statusPill($mediaLog) ?></td></tr>
      </table>
      <form method="post" action="<?= View::e($refreshUrl) ?>" style="margin-top:1rem">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit">Refresh now</button>
      </form>
    </div>

    <div class="card">
      <h2>Cache</h2>
      <p style="font-size:.85rem;color:#666;margin-top:0">
        Clears both layers: the server-side event/media data cache (re-fetched immediately, same as
        "Refresh now") and visitors' cached CSS/JS — by bumping the <code>?v=N</code> version on
        <code>styles.css</code>/<code>app.js</code> in <code>index.html</code> and the service worker,
        so every browser fetches fresh copies on next load instead of a stale cached one.
      </p>
      <form method="post" action="<?= View::e($purgeCacheUrl) ?>"
            onsubmit="return confirm('Clear the event/media data cache and bump the CSS/JS version? Every visitor will re-fetch fresh assets on next load.');">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn--danger">Purge all caches</button>
      </form>
    </div>

    <div class="card">
      <h2>Event source</h2>
      <p style="font-size:.85rem;color:#666;margin-top:0">
        The WordCamp.org slug CampBuddy pulls event data for. Changing this refetches
        immediately — no .env edit or deploy needed.
      </p>
      <form method="post" action="<?= View::e($settingsUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label for="event_slug">Event slug</label>
        <input id="event_slug" name="event_slug" value="<?= View::e($eventSlug) ?>" pattern="[a-z0-9-]+" required>
        <button type="submit" style="margin-top:1rem">Save &amp; refresh</button>
      </form>
    </div>

    <div class="card">
      <h2>Event field overrides</h2>
      <p style="font-size:.85rem;color:#666;margin-top:0">
        Leave a field blank to use the live-fetched value. A filled-in value here always wins,
        even after the next auto-refresh.
      </p>
      <form method="post" action="<?= View::e($overrideUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <?php foreach ($overridableFields as $field => $label): ?>
          <label for="f_<?= View::e($field) ?>"><?= View::e($label) ?></label>
          <input id="f_<?= View::e($field) ?>" name="fields[<?= View::e($field) ?>]" value="<?= View::e($overrides[$field] ?? '') ?>">
        <?php endforeach; ?>
        <button type="submit" style="margin-top:1rem">Save overrides</button>
      </form>
    </div>
  </main>
</body>
</html>
