
<?php
session_start();

require 'database.php';

header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'GET only'
  ]);
  exit;
}

// Require a logged-in customer
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode([
    'success' => false,
    'message' => 'Please log in to view your orders.'
  ]);
  exit;
}

try {
  $userId = (int) $_SESSION['user_id'];

  // Retrieve only this customer's orders
  $getOrders = $pdo->prepare(
    'SELECT
            id,
            order_no,
            payment_method,
            subtotal,
            shipping,
            total,
            status,
            created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC'
  );

  $getOrders->execute([$userId]);
  $orders = $getOrders->fetchAll();

  // Retrieve products for each order
  $getItems = $pdo->prepare(
    'SELECT
            product_id,
            option_value,
            quantity,
            unit_price
         FROM order_items
         WHERE order_id = ?
         ORDER BY id ASC'
  );

  foreach ($orders as &$order) {
    $getItems->execute([$order['id']]);
    $order['items'] = $getItems->fetchAll();
  }

  unset($order);

  echo json_encode([
    'success' => true,
    'orders' => $orders
  ]);

} catch (Throwable $e) {
  error_log($e->getMessage());

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Unable to retrieve your orders.'
  ]);
}
