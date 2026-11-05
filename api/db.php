<?php
$pdo = new PDO(
  'mysql:host=127.0.0.1;port=3306;dbname=pawsandwhiskers;charset=utf8mb4',
  'root',
  'pumbamaster',
  [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);
