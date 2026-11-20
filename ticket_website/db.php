<?php
// Connection settings for MySQL Server (the one managed in MySQL Workbench).
// $pass = the root password you chose when installing MySQL.
$host = "localhost";
$user = "root";
$pass = "your-root-password";
$dbname = "ConcertTicketDB";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
