<?php
/**
 * Database configuration and connection using PDO
 */
$host = 'localhost';
$dbname = 'mangima_resort';
$username = 'root';  // default XAMPP credentials
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Helper function to get PDO instance
 * This function MUST be defined here so it's available globally
 */
function getDB() {
    global $pdo;
    return $pdo;
}
?>