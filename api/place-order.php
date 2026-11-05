
<?php
session_start();

require 'database.php';

header('Content-Type: application/json');

function fail($code, $msg) {
  http_response_code($code);
  echo json_encode(['error' => $msg]);
  exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fail(405, 'POST only');
}

// Require a logged-in customer
if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
  fail(401, 'Please log in before placing an order.');
}

$userId = (int) $_SESSION['user_id'];

// Receive checkout data
$in = json_decode(file_get_contents('php://input'), true);

$c       = $in['customer'] ?? null;
$payment = $in['payment'] ?? '';
$items   = $in['items'] ?? [];

// Validate checkout information
if (
  !is_array($c) ||
  !is_array($items) ||
  !$items ||
  !in_array($payment, ['Cash on Delivery', 'GCash'], true)
) {
  fail(400, 'Invalid order');
}

// Validate customer details
foreach (['name', 'email', 'phone', 'addr', 'city', 'zip'] as $k) {
  if (!isset($c[$k]) || !is_string($c[$k]) || trim($c[$k]) === '') {
    fail(400, 'Missing customer details');
  }

  $c[$k] = trim($c[$k]);
}

if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
  fail(400, 'Invalid email');
}

if (!preg_match('/^\d{4}$/', $c['zip'])) {
  fail(400, 'Postal code must be 4 digits');
}

if (strlen($c['phone']) > 20) {
  fail(400, 'Phone number too long');
}

try {
  // Make sure the logged-in account still exists
  $checkUser = $pdo->prepare(
    'SELECT id FROM users WHERE id = ?'
  );
  $checkUser->execute([$userId]);

  if (!$checkUser->fetch()) {
    fail(401, 'Your account could not be verified. Please log in again.');
  }

  $pdo->beginTransaction();

  // Prepare product and option queries
  $getProd = $pdo->prepare(
    'SELECT id, price FROM products WHERE id = ?'
  );

  $getOpt = $pdo->prepare(
    'SELECT id FROM product_options
         WHERE product_id = ? AND `value` = ?'
  );

  $subtotal = 0;
  $lines = [];

  // Validate products and calculate subtotal
  foreach ($items as $it) {
    if (!is_array($it)) {
      throw new InvalidArgumentException('Invalid order item');
    }

    $q = $it['q'] ?? null;

    if (!is_int($q) || $q < 1 || $q > 20) {
      throw new InvalidArgumentException('Invalid quantity');
    }

    $getProd->execute([$it['id'] ?? 0]);
    $prod = $getProd->fetch();

    if (!$prod) {
      throw new InvalidArgumentException('Product not found');
    }

    $option = $it['o'] ?? '';

    $getOpt->execute([$prod['id'], $option]);

    if (!$getOpt->fetch()) {
      throw new InvalidArgumentException('Invalid option');
    }

    $price = (float) $prod['price'];

    $subtotal += $price * $q;

    $lines[] = [
      $prod['id'],
      $option,
      $q,
      $price
    ];
  }

  // Calculate shipping and total
  $shipping = $subtotal >= 3000 ? 0 : 150;
  $total = $subtotal + $shipping;

  // Generate order number
  $orderNo = 'MP-' . random_int(100000, 999999);

  // Save customer order with logged-in user ID
  $insertOrder = $pdo->prepare(
    'INSERT INTO orders
            (user_id, order_no, customer_name, email, phone,
             address, city, zip, payment_method, subtotal,
             shipping, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );

  $insertOrder->execute([
    $userId,
    $orderNo,
    $c['name'],
    $c['email'],
    $c['phone'],
    $c['addr'],
    $c['city'],
    $c['zip'],
    $payment,
    $subtotal,
    $shipping,
    $total
  ]);

  $orderId = $pdo->lastInsertId();

  // Save purchased products
  $insertItem = $pdo->prepare(
    'INSERT INTO order_items
            (order_id, product_id, option_value, quantity, unit_price)
         VALUES (?, ?, ?, ?, ?)'
  );

  foreach ($lines as $line) {
    $insertItem->execute([
      $orderId,
      $line[0],
      $line[1],
      $line[2],
      $line[3]
    ]);
  }

  // Save all changes
  $pdo->commit();

  echo json_encode([
    'success' => true,
    'no' => $orderNo,
    'total' => $total
  ]);

} catch (InvalidArgumentException $e) {

  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  fail(400, $e->getMessage());

} catch (Throwable $e) {

  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log($e->getMessage());

  fail(500, 'Server error');
}
