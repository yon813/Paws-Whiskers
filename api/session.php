<?php
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
  echo json_encode([
    'success' => true,
    'user' => [
      'id' => $_SESSION['user_id'],
      'name' => $_SESSION['user_name'],
      'email' => $_SESSION['user_email'],
      'role' => $_SESSION['user_role'] ?? 'customer'
    ]
  ]);
} else {
  echo json_encode([
    'success' => false,
    'user' => null
  ]);
}
