<?php
// config.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'localhost');
define('DB_USER', 'localhost');
define('DB_PASS', 'localhost');

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Connection Error: " . $e->getMessage());
    die("Koneksi database gagal. Silakan coba beberapa saat lagi.");
}
