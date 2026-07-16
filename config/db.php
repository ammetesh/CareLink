<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "carelink_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Optional: Set UTF-8
$conn->set_charset("utf8");

?>