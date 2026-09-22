<?php
/** @var string $csrfToken */
/** @var ?string $flash */
/** @var array<int, array<string,mixed>> $offers */
/** @var array<string,mixed>|null $editing */
/** @var string $adminUrl */
/** @var string $saveUrl */
/** @var string $deleteUrl */
/** @var string $offersUrl */
use CampBuddy\Support\View;

$e = $editing ?? ['id' => '', 'title' => '', 'description' => '', 'url' => '', 'icon' => '🏷', 'sort_order' => 0, 'is_active' => 1];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CampBuddy Admin · Offers</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    body{font-family:system-ui,sans-serif;background:#f4f2ef;margin:0;color:#222}
    header{background:#c33a19;color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
    header nav a{color:#fff;text-decoration:none;margin-right:1rem;font-size:.9rem;opacity:.9}
    header nav a:hover{opacity:1;text-decoration:underline}
    main{max-width:760px;margin:1.5rem auto;padding:0 1rem}
    .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:1.25rem 1.5rem;margin-bottom:1.25rem}
    h2{font-size:1rem;margin:0 0 .75rem}
    label{display:block;font-size:.85rem;margin:.6rem 0 .25rem;color:#444}
    input{width:100%;padding:.5rem .6rem;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;font-size:.95rem}
    button{padding:.55rem 1.1rem;border:0;border-radius:6px;background:#c33a19;color:#fff;font-size:.95rem;cursor:pointer}
    button.btn--danger{background:#a4262c}
    button.btn--ghost{background:#fff;color:#c33a19;border:1px solid #c33a19}
    .flash{background:#eaf6ec;color:#1a7f37;padding:.6rem .75rem;border-radius:6px;margin-bottom:1rem}
    table{width:100%;border-collapse:collapse;font-size:.9rem}
    th,td{padding:.5rem .4rem;text-align:left;border-bottom:1px solid #eee;vertical-align:top}
    .row-actions{display:flex;gap:.4rem;white-space:nowrap}
    .row-actions form{margin:0}
    .row-actions button{padding:.3rem .6rem;font-size:.8rem}
    .inactive{opacity:.5}
    .icon-col{font-size:1.3rem;width:2rem;text-align:center}
    .checkbox-row{display:flex;align-items:center;gap:.5rem;margin-top:.8rem}
    .checkbox-row input{width:auto}
  </style>
</head>
<body>
  <header>
    <strong>CampBuddy Admin</strong>
    <nav><a href="<?= View::e($adminUrl) ?>">Dashboard</a><a href="<?= View::e($offersUrl) ?>">Offers</a></nav>
  </header>
  <main>
    <?php if (!empty($flash)): ?><div class="flash"><?= View::e($flash) ?></div><?php endif; ?>

    <div class="card">
      <h2><?= $e['id'] !== '' ? 'Edit offer' : 'Add new offer' ?></h2>
      <form method="post" action="<?= View::e($saveUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <?php if ($e['id'] !== ''): ?><input type="hidden" name="id" value="<?= View::e($e['id']) ?>"><?php endif; ?>
        <label for="title">Title</label>
        <input id="title" name="title" value="<?= View::e($e['title']) ?>" required maxlength="120">
        <label for="description">Description</label>
        <input id="description" name="description" value="<?= View::e($e['description']) ?>" required maxlength="255">
        <label for="url">URL (affiliate/referral link)</label>
        <input id="url" name="url" type="url" value="<?= View::e($e['url']) ?>" required maxlength="500" placeholder="https://...">
        <label for="icon">Icon (a single emoji)</label>
        <input id="icon" name="icon" value="<?= View::e($e['icon']) ?>" maxlength="10">
        <label for="sort_order">Sort order (lower shows first)</label>
        <input id="sort_order" name="sort_order" type="number" value="<?= View::e($e['sort_order']) ?>">
        <div class="checkbox-row">
          <input id="is_active" name="is_active" type="checkbox" value="1" <?= !empty($e['is_active']) ? 'checked' : '' ?>>
          <label for="is_active" style="margin:0">Active (shown on the Offer tab)</label>
        </div>
        <button type="submit" style="margin-top:1rem"><?= $e['id'] !== '' ? 'Save changes' : 'Add offer' ?></button>
        <?php if ($e['id'] !== ''): ?>
          <a href="<?= View::e($offersUrl) ?>"><button type="button" class="btn--ghost" style="margin-top:1rem">Cancel edit</button></a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <h2>All offers</h2>
      <table>
        <thead><tr><th></th><th>Title</th><th>Description</th><th>Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($offers as $o): ?>
          <tr class="<?= empty($o['is_active']) ? 'inactive' : '' ?>">
            <td class="icon-col"><?= View::e($o['icon']) ?></td>
            <td><?= View::e($o['title']) ?><?= empty($o['is_active']) ? ' <small>(inactive)</small>' : '' ?></td>
            <td><?= View::e($o['description']) ?></td>
            <td><?= View::e($o['sort_order']) ?></td>
            <td class="row-actions">
              <a href="<?= View::e($offersUrl) ?>?edit=<?= (int) $o['id'] ?>"><button type="button">Edit</button></a>
              <form method="post" action="<?= View::e($deleteUrl) ?>" onsubmit="return confirm('Delete the <?= View::e(addslashes($o['title'])) ?> offer?');">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button type="submit" class="btn--danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$offers): ?><tr><td colspan="5">No offers yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
