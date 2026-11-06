<?php

function connect() {
  $host = '	sql107.infinityfree.com';
  $dbname = 'if0_43372689_pawsandwhiskers';
  $username = '	if0_43372689';
  $password = 'VenturaMacasa';

  try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connection successful!";
  } catch (PDOException $e) {
    echo "Connection failed:" . $e->getMessage();
  }
}

connect();
