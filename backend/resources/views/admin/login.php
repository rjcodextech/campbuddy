<?php
/** @var string $csrfToken */
/** @var ?string $error */
/** @var string $loginUrl */
use CampBuddy\Support\View;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CampBuddy Admin · Sign in</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    body{font-family:system-ui,sans-serif;background:#f4f2ef;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
    .card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);width:100%;max-width:340px}
    h1{font-size:1.1rem;margin:0 0 1.25rem}
    label{display:block;font-size:.85rem;margin-bottom:.25rem;color:#444}
    input{width:100%;padding:.55rem .65rem;margin-bottom:1rem;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;font-size:1rem}
    button{width:100%;padding:.6rem;border:0;border-radius:6px;background:#c33a19;color:#fff;font-size:1rem;cursor:pointer}
    .error{background:#fdecea;color:#a4262c;padding:.6rem .75rem;border-radius:6px;font-size:.85rem;margin-bottom:1rem}
  </style>
</head>
<body>
  <form class="card" method="post" action="<?= View::e($loginUrl) ?>">
    <h1>CampBuddy Admin</h1>
    <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <label for="username">Username</label>
    <input id="username" name="username" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Sign in</button>
  </form>
</body>
</html>
