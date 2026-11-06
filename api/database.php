
<?php

$host = 'localhost';
$dbname = 'pawsandwhiskers';
$username = 'root';
$password = 'pumbamaster';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
