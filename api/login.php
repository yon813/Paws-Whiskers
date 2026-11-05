
<?php
session_start();

header('Content-Type: application/json');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'Invalid request method.'
  ]);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Please enter your email and password.'
  ]);
  exit;
}

try {
  $stmt = $pdo->prepare(
    'SELECT id, full_name, email, password
         FROM users
         WHERE email = ?'
  );

  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
      'success' => false,
      'message' => 'Incorrect email or password.'
    ]);
    exit;
  }

  session_regenerate_id(true);

  $_SESSION['user_id'] = $user['id'];
  $_SESSION['user_name'] = $user['full_name'];
  $_SESSION['user_email'] = $user['email'];

  echo json_encode([
    'success' => true,
    'message' => 'Welcome back, ' . $user['full_name'] . '!',
    'user' => [
      'id' => $user['id'],
      'name' => $user['full_name'],
      'email' => $user['email']
    ]
  ]);

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Unable to log in. Please try again.'
  ]);
}
