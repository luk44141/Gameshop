<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "gameshop";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    header("Content-Type: application/json");
    die(json_encode([
        "success" => false,
        "message" => "Error de conexión con la base de datos"
    ]));
}

$conn->set_charset("utf8mb4");
?>