<?php
// A role simulation may read live application data, but it must never write it.
// Role pages start the session before loading this shared database bootstrap.
if (session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['simulation_read_only'])
    && !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Role simulation is read-only. Return to the simulation page to exit preview mode.');
}

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "circulapu_db";

// Create database connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
