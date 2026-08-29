<?php
 
 
if (session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['simulation_read_only'])
    && !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Role simulation is read-only. Return to the simulation page to exit preview mode.');
}

 
$host = "localhost";
$username = "root";
$password = "";
$database = "circulapu_db";

 
$conn = new mysqli($host, $username, $password, $database);

 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
