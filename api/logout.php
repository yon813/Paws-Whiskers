
<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'Invalid request method.'
  ]);
  exit;
}

$_SESSION = [];

session_destroy();

echo json_encode([
  'success' => true,
  'message' => 'You have successfully logged out.'
]);
