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

$username = trim($data["username"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($username === "" || $email === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Completá todos los campos"
    ]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode([
        "success" => false,
        "message" => "La contraseña debe tener al menos 6 caracteres"
    ]);
    exit;
}

$check = $conn->prepare(
    "SELECT id
     FROM usuarios
     WHERE email = ? OR username = ?"
);

$check->bind_param("ss", $email, $username);
$check->execute();

$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "El usuario o email ya existe"
    ]);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO usuarios
     (username, email, password, saldo, rol)
     VALUES (?, ?, ?, 0.00, 'usuario')"
);

$stmt->bind_param(
    "sss",
    $username,
    $email,
    $passwordHash
);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "No se pudo crear el usuario"
    ]);
    exit;
}

$id = $conn->insert_id;

$_SESSION["usuario_id"] = $id;

echo json_encode([
    "success" => true,
    "user" => [
        "id" => $id,
        "username" => $username,
        "email" => $email,
        "saldo" => 0,
        "rol" => "usuario"
    ]
]);

$stmt->close();
$conn->close();
?>