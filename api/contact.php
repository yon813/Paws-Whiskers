
<?php

header('Content-Type: application/json');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'error' => 'Invalid request method.'
  ]);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$message = trim($data['message'] ?? '');

// Validate submitted information
if (
  strlen($name) < 2 ||
  strlen($name) > 100 ||
  !filter_var($email, FILTER_VALIDATE_EMAIL) ||
  strlen($email) > 150 ||
  strlen($message) < 10
) {
  http_response_code(422);
  echo json_encode([
    'error' => 'Please provide valid contact information and a message of at least 10 characters.'
  ]);
  exit;
}

try {
  $stmt = $pdo->prepare("
        INSERT INTO contact_messages (name, email, message)
        VALUES (:name, :email, :message)
    ");

  $stmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':message' => $message
  ]);

  echo json_encode([
    'success' => true,
    'message' => 'Your message has been sent successfully!'
  ]);

} catch (PDOException $e) {
  error_log($e->getMessage());

  http_response_code(500);
  echo json_encode([
    'error' => 'Unable to save your message. Please try again.'
  ]);
}
