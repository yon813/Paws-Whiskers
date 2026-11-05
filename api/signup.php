
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

$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';
$confirm = $data['confirm_password'] ?? '';

if ($name === '' || $email === '' || $password === '' || $confirm === '') {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Please complete all fields.'
  ]);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Please enter a valid email address.'
  ]);
  exit;
}

if (strlen($password) < 8) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Password must be at least 8 characters.'
  ]);
  exit;
}

if ($password !== $confirm) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Passwords do not match.'
  ]);
  exit;
}

try {
  $check = $pdo->prepare(
    'SELECT id FROM users WHERE email = ?'
  );
  $check->execute([$email]);

  if ($check->fetch()) {
    http_response_code(409);
    echo json_encode([
      'success' => false,
      'message' => 'This email is already registered.'
    ]);
    exit;
  }

  $hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
  );

  $insert = $pdo->prepare(
    'INSERT INTO users (full_name, email, password)
         VALUES (?, ?, ?)'
  );

  $insert->execute([
    $name,
    $email,
    $hashedPassword
  ]);

  echo json_encode([
    'success' => true,
    'message' => 'Account created successfully! You can now log in.'
  ]);

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Something went wrong while creating your account.'
  ]);
}
