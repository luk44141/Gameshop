<?php

session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

require_once "conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Completá todos los campos"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, username, email, password, saldo, rol
     FROM usuarios
     WHERE email = ?"
);

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Email o contraseña incorrectos"
    ]);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user["password"])) {
    echo json_encode([
        "success" => false,
        "message" => "Email o contraseña incorrectos"
    ]);
    exit;
}

$_SESSION["usuario_id"] = $user["id"];

echo json_encode([
    "success" => true,
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