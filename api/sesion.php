<?php

session_start();

header("Content-Type: application/json");

require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([
        "success" => false,
        "logged" => false
    ]);
    exit;
}

$id = (int)$_SESSION["usuario_id"];

$stmt = $conn->prepare(
    "SELECT id, username, email, saldo, rol
     FROM usuarios
     WHERE id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();

    echo json_encode([
        "success" => false,
        "logged" => false
    ]);
    exit;
}

$user = $result->fetch_assoc();

echo json_encode([
    "success" => true,
    "logged" => true,
    "user" => [
        "id" => (int)$user["id"],
        "username" => $user["username"],
        "email" => $user["email"],
        "saldo" => (float)$user["saldo"],
        "rol" => $user["rol"]
    ]
]);

$stmt->close();
$conn->close();
?>