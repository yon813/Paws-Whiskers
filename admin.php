<?php

ob_start();
session_start();
require __DIR__ . '/api/db.php'; // provides $pdo

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function peso($n) { return '₱' . number_format((float)$n, 2); }

/* ---------- 1. Admin check ---------- */
$uid = (int)($_SESSION['user_id'] ?? 0);
$admin = null;

if ($uid > 0) {
  $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $st->execute([$uid]);
  $admin = $st->fetch();
}

if (!$admin || ($admin['role'] ?? 'customer') !== 'admin') {
http_response_code(403);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Access denied</title>
  <style>body{font-family:system-ui,sans-serif;background:#F5E9E2;color:#773344;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:20px}
    a{display:inline-block;margin-top:14px;background:#773344;color:#F5E9E2;padding:12px 26px;border-radius:999px;text-decoration:none}</style></head>
<body><div><h1>Admins only</h1><p>You need to be logged in as an admin to view this page.</p>
  <a href="index.html">Back to shop</a></div></body></html>
<?php
exit;
}

/* ---------- 2. CSRF + flash ---------- */
if (empty($_SESSION['admin_csrf'])) {
  $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

$tabs = ['overview' => 'Overview', 'orders' => 'Orders', 'products' => 'Products', 'messages' => 'Messages', 'customers' => 'Customers'];
$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

function go($tab, $msg, $type = 'ok') {
  $_SESSION['flash'] = [$type, $msg];
  header('Location: admin.php?tab=' . urlencode($tab));
  exit;
}

/* ---------- 3. Actions (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    go($tab, 'Session expired. Please try again.', 'err');
  }

  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'order_status') {
      $status = $_POST['status'] ?? '';
      if (!in_array($status, $statuses, true)) go('orders', 'Invalid status.', 'err');
      $st = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
      $st->execute([$status, (int)($_POST['id'] ?? 0)]);
      go('orders', 'Order status updated.');
    }

    if ($action === 'product_update') {
      $name  = trim((string)($_POST['name'] ?? ''));
      $price = $_POST['price'] ?? '';
      $stock = $_POST['stock'] ?? '';
      if ($name === '' || strlen($name) > 120 || !is_numeric($price) || $price < 0 || !ctype_digit((string)$stock)) {
        go('products', 'Please enter a valid name, price and stock.', 'err');
      }
      $st = $pdo->prepare('UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?');
      $st->execute([$name, (float)$price, (int)$stock, (int)($_POST['id'] ?? 0)]);
      go('products', 'Product updated.');
    }

    if ($action === 'message_delete') {
      $st = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
      $st->execute([(int)($_POST['id'] ?? 0)]);
      go('messages', 'Message deleted.');
    }
  } catch (Throwable $e) {
    error_log($e->getMessage());
    go($tab, 'Something went wrong. Please try again.', 'err');
  }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ---------- 4. Data for the current tab ---------- */
function one($pdo, $sql) {
  try { return $pdo->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
}

$data = [];

if ($tab === 'overview') {
  $data['orders']    = (int)one($pdo, 'SELECT COUNT(*) FROM orders');
  $data['revenue']   = (float)one($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled'");
  $data['pending']   = (int)one($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'pending'");
  $data['customers'] = (int)one($pdo, "SELECT COUNT(*) FROM users WHERE role = 'customer'");
  $data['lowstock']  = (int)one($pdo, 'SELECT COUNT(*) FROM products WHERE stock <= 10');
  $data['messages']  = (int)one($pdo, 'SELECT COUNT(*) FROM contact_messages');
  $data['by_status'] = [];
  foreach ($pdo->query('SELECT status, COUNT(*) c FROM orders GROUP BY status') as $r) {
    $data['by_status'][$r['status']] = (int)$r['c'];
  }
  $data['recent'] = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
}

if ($tab === 'orders') {
  $fs = $_GET['status'] ?? '';
  $q  = trim((string)($_GET['q'] ?? ''));
  $sql = 'SELECT * FROM orders WHERE 1=1';
  $args = [];
  if (in_array($fs, $statuses, true)) { $sql .= ' AND status = ?'; $args[] = $fs; }
  if ($q !== '') {
    $sql .= ' AND (order_no LIKE ? OR customer_name LIKE ? OR email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like);
  }
  $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $data['orders'] = $st->fetchAll();

  $itemSt = $pdo->prepare(
    'SELECT oi.option_value, oi.quantity, oi.unit_price, p.name
       FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
      WHERE oi.order_id = ? ORDER BY oi.id'
  );
  foreach ($data['orders'] as &$o) {
    $itemSt->execute([$o['id']]);
    $o['items'] = $itemSt->fetchAll();
  }
  unset($o);
  $data['fs'] = $fs;
  $data['q']  = $q;
}

if ($tab === 'products') {
  $data['products'] = $pdo->query('SELECT id, name, price, category, pet, stock FROM products ORDER BY id')->fetchAll();
}

if ($tab === 'messages') {
  try {
    $data['messages'] = $pdo->query('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 200')->fetchAll();
  } catch (Throwable $e) {
    $data['messages'] = [];
    $data['msg_error'] = true;
  }
}

if ($tab === 'customers') {
  $data['customers'] = $pdo->query(
    "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
            (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS spent
       FROM users u ORDER BY u.id DESC LIMIT 200"
  )->fetchAll();
}

$adminName = $admin['full_name'] ?? ($admin['name'] ?? 'Admin');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin — Paws&amp;Whiskers</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root { --plum:#773344; --blush:#E3B5A4; --shell:#F5E9E2; --card:#fffaf7; --line:rgba(119,51,68,.15); }
    * { box-sizing: border-box; }
    body { margin:0; font:14px/1.55 'Inter',system-ui,sans-serif; background:var(--shell); color:var(--plum); }
    a { color:inherit; text-decoration:none; }
    h1,h2,h3 { font-family:'Cormorant Garamond',Georgia,serif; margin:0; }
    header { display:flex; align-items:center; gap:14px; padding:14px 22px; background:var(--plum); color:var(--shell); flex-wrap:wrap; }
    header h1 { font-size:1.6rem; margin-right:auto; }
    header span { opacity:.85; font-size:13px; }
    .hbtn { border:1px solid rgba(245,233,226,.5); color:var(--shell); background:none; padding:7px 16px; border-radius:999px; cursor:pointer; font:inherit; font-size:13px; }
    .hbtn:hover { background:var(--shell); color:var(--plum); }
    nav { display:flex; gap:6px; padding:12px 22px 0; overflow-x:auto; }
    nav a { padding:9px 18px; border-radius:999px; font-weight:500; white-space:nowrap; border:1px solid var(--line); background:var(--card); }
    nav a.on { background:var(--plum); color:var(--shell); border-color:var(--plum); }
    main { padding:20px 22px 50px; max-width:1200px; margin:0 auto; }
    .flash { padding:12px 16px; border-radius:12px; margin-bottom:16px; font-weight:500; }
    .flash.ok { background:rgba(80,130,90,.14); border:1px solid rgba(80,130,90,.3); }
    .flash.err { background:rgba(119,51,68,.1); border:1px solid rgba(119,51,68,.3); }
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:22px; }
    .stat { background:var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; }
    .stat small { opacity:.7; display:block; }
    .stat b { font:700 1.9rem 'Cormorant Garamond',serif; }
    .panel { background:var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; margin-bottom:18px; }
    .panel h2 { font-size:1.4rem; margin-bottom:12px; }
    .scroll { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width:560px; }
    th { text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.06em; opacity:.7; padding:8px 10px; border-bottom:1px solid var(--line); }
    td { padding:10px; border-bottom:1px solid var(--line); vertical-align:top; }
    .badge { display:inline-block; padding:3px 11px; border-radius:999px; font-size:12px; font-weight:600; text-transform:capitalize; background:var(--blush); }
    .badge.paid,.badge.delivered { background:#cfe6d3; color:#2d5a38; }
    .badge.shipped { background:#d3e1f3; color:#2b4a78; }
    .badge.cancelled { background:#ead0d0; color:#8a2b2b; }
    .badge.pending { background:#f4e0b8; color:#7a5a12; }
    input,select,button.act { font:inherit; color:var(--plum); border:1px solid rgba(119,51,68,.3); border-radius:10px; padding:7px 10px; background:#fff; }
    input[type=number] { width:100px; }
    button.act { background:var(--plum); color:var(--shell); cursor:pointer; border-color:var(--plum); }
    button.act.del { background:transparent; color:#8a2b2b; border-color:#8a2b2b; }
    .filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
    .filters input[type=search] { flex:1; min-width:180px; }
    details summary { cursor:pointer; font-weight:600; }
    .items { margin:8px 0 0; padding:10px 12px; background:rgba(227,181,164,.25); border-radius:10px; font-size:13px; }
    .items div { display:flex; justify-content:space-between; gap:10px; padding:2px 0; }
    .muted { opacity:.65; }
    .chips { display:flex; gap:8px; flex-wrap:wrap; }
    form.inline { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin:0; }
    @media (max-width:600px) { header, nav, main { padding-left:14px; padding-right:14px; } }
  </style>
</head>
<body>

<header>
  <h1>Paws&amp;Whiskers Admin</h1>
  <span>Signed in as <?= h($adminName) ?></span>
  <a class="hbtn" href="index.html">View shop</a>
  <button class="hbtn" type="button" id="logout">Log out</button>
</header>

<nav>
  <?php foreach ($tabs as $k => $label): ?>
    <a href="admin.php?tab=<?= h($k) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<main>
  <?php if ($flash): ?>
    <div class="flash <?= h($flash[0]) ?>"><?= h($flash[1]) ?></div>
  <?php endif; ?>

  <?php /* ================= OVERVIEW ================= */ if ($tab === 'overview'): ?>
    <div class="stats">
      <div class="stat"><small>Total orders</small><b><?= $data['orders'] ?></b></div>
      <div class="stat"><small>Revenue (excl. cancelled)</small><b><?= peso($data['revenue']) ?></b></div>
      <div class="stat"><small>Pending orders</small><b><?= $data['pending'] ?></b></div>
      <div class="stat"><small>Customers</small><b><?= $data['customers'] ?></b></div>
      <div class="stat"><small>Low stock (≤10)</small><b><?= $data['lowstock'] ?></b></div>
      <div class="stat"><small>Messages</small><b><?= $data['messages'] ?></b></div>
    </div>

    <div class="panel">
      <h2>Orders by status</h2>
      <div class="chips">
        <?php foreach ($statuses as $s): ?>
          <a href="admin.php?tab=orders&status=<?= h($s) ?>"><span class="badge <?= h($s) ?>"><?= h($s) ?>: <?= (int)($data['by_status'][$s] ?? 0) ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <h2>Recent orders</h2>
      <div class="scroll">
        <table>
          <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
          <?php foreach ($data['recent'] as $o): ?>
            <tr>
              <td><b><?= h($o['order_no']) ?></b></td>
              <td><?= h($o['customer_name']) ?></td>
              <td><?= peso($o['total']) ?></td>
              <td><span class="badge <?= h($o['status']) ?>"><?= h($o['status']) ?></span></td>
              <td class="muted"><?= h($o['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$data['recent']): ?><tr><td colspan="5" class="muted">No orders yet.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>

  <?php /* ================= ORDERS ================= */ elseif ($tab === 'orders'): ?>
    <div class="panel">
      <h2>Orders</h2>
      <form class="filters" method="get" action="admin.php">
        <input type="hidden" name="tab" value="orders">
        <input type="search" name="q" value="<?= h($data['q']) ?>" placeholder="Search order no., name or email">
        <select name="status">
          <option value="">All statuses</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= h($s) ?>" <?= $data['fs'] === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="act" type="submit">Filter</button>
      </form>

      <div class="scroll">
        <table>
          <tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th></tr>
          <?php foreach ($data['orders'] as $o): ?>
            <tr>
              <td>
                <details>
                  <summary><?= h($o['order_no']) ?></summary>
                  <div class="items">
                    <?php foreach ($o['items'] as $it): ?>
                      <div>
                        <span><?= h($it['name'] ?? 'Deleted product') ?> (<?= h($it['option_value']) ?>) × <?= (int)$it['quantity'] ?></span>
                        <span><?= peso($it['unit_price'] * $it['quantity']) ?></span>
                      </div>
                    <?php endforeach; ?>
                    <div class="muted"><span>Subtotal / Shipping</span><span><?= peso($o['subtotal']) ?> / <?= peso($o['shipping']) ?></span></div>
                    <div><b>Ship to:</b>&nbsp;<?= h($o['address']) ?>, <?= h($o['city']) ?> <?= h($o['zip']) ?></div>
                  </div>
                </details>
                <small class="muted"><?= h($o['created_at']) ?></small>
              </td>
              <td><?= h($o['customer_name']) ?><br><small class="muted"><?= h($o['email']) ?><br><?= h($o['phone']) ?></small></td>
              <td><b><?= peso($o['total']) ?></b></td>
              <td><?= h($o['payment_method']) ?></td>
              <td>
                <form class="inline" method="post" action="admin.php?tab=orders">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="order_status">
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <select name="status">
                    <?php foreach ($statuses as $s): ?>
                      <option value="<?= h($s) ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="act" type="submit">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$data['orders']): ?><tr><td colspan="5" class="muted">No orders found.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>

  <?php /* ================= PRODUCTS ================= */ elseif ($tab === 'products'): ?>
    <div class="panel">
      <h2>Products</h2>
      <p class="muted">Edit the name, price and stock, then press Save on that row.</p>
      <div class="scroll">
        <table>
          <tr><th>#</th><th>Name</th><th>Price (₱)</th><th>Stock</th><th></th></tr>
          <?php foreach ($data['products'] as $p): ?>
            <tr>
              <td>
                <?= (int)$p['id'] ?>
                <form id="pf<?= (int)$p['id'] ?>" method="post" action="admin.php?tab=products">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="product_update">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                </form>
              </td>
              <td>
                <input form="pf<?= (int)$p['id'] ?>" type="text" name="name" value="<?= h($p['name']) ?>" maxlength="120" style="width:100%;min-width:200px">
                <small class="muted"><?= h($p['category']) ?> · <?= h($p['pet']) ?></small>
              </td>
              <td><input form="pf<?= (int)$p['id'] ?>" type="number" name="price" value="<?= h($p['price']) ?>" min="0" step="0.01"></td>
              <td>
                <input form="pf<?= (int)$p['id'] ?>" type="number" name="stock" value="<?= (int)$p['stock'] ?>" min="0" step="1" style="width:80px">
                <?php if ((int)$p['stock'] <= 10): ?><span class="badge cancelled">low</span><?php endif; ?>
              </td>
              <td><button form="pf<?= (int)$p['id'] ?>" class="act" type="submit">Save</button></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>

  <?php /* ================= MESSAGES ================= */ elseif ($tab === 'messages'): ?>
    <div class="panel">
      <h2>Customer messages</h2>
      <?php if (!empty($data['msg_error'])): ?>
        <p class="muted">The <code>contact_messages</code> table was not found. Run <code>admin-setup.sql</code> first.</p>
      <?php endif; ?>
      <?php foreach ($data['messages'] as $m): ?>
        <div class="items" style="margin-bottom:12px">
          <div><b><?= h($m['name']) ?></b><span class="muted"><?= h($m['created_at'] ?? '') ?></span></div>
          <div><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></div>
          <p style="margin:8px 0"><?= nl2br(h($m['message'])) ?></p>
          <form class="inline" method="post" action="admin.php?tab=messages" onsubmit="return confirm('Delete this message?')">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="message_delete">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <a class="act" style="padding:7px 14px;border-radius:10px" href="mailto:<?= h($m['email']) ?>">Reply by email</a>
            <button class="act del" type="submit">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$data['messages'] && empty($data['msg_error'])): ?><p class="muted">No messages yet.</p><?php endif; ?>
    </div>

  <?php /* ================= CUSTOMERS ================= */ elseif ($tab === 'customers'): ?>
    <div class="panel">
      <h2>Customers</h2>
      <div class="scroll">
        <table>
          <tr><th>Name</th><th>Email</th><th>Role</th><th>Orders</th><th>Spent</th><th>Joined</th></tr>
          <?php foreach ($data['customers'] as $u): ?>
            <tr>
              <td><?= h($u['full_name'] ?? ($u['name'] ?? '')) ?></td>
              <td><?= h($u['email']) ?></td>
              <td><span class="badge <?= ($u['role'] ?? '') === 'admin' ? 'shipped' : '' ?>"><?= h($u['role'] ?? 'customer') ?></span></td>
              <td><?= (int)$u['order_count'] ?></td>
              <td><?= peso($u['spent']) ?></td>
              <td class="muted"><?= h($u['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
  document.getElementById('logout').addEventListener('click', async function () {
    try { await fetch('api/logout.php', { method: 'POST' }); } catch (e) {}
    window.location.href = 'index.html';
  });
</script>
</body>
</html>
