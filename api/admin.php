<?php

session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

require_once "conexion.php";

/*
 * Verificar sesión.
 */

if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "No estás conectado"
    ]);

    exit;
}

$id_admin = (int)$_SESSION["usuario_id"];

/*
 * Verificar que sea administrador.
 */

$check = $conn->prepare(
    "SELECT rol
     FROM usuarios
     WHERE id = ?"
);

$check->bind_param("i", $id_admin);
$check->execute();

$result = $check->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Usuario no encontrado"
    ]);

    exit;
}

$admin = $result->fetch_assoc();

if ($admin["rol"] !== "admin") {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "No tenés permisos de administrador"
    ]);

    exit;
}

/*
 * GET → listar usuarios.
 */

if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $result = $conn->query(
        "SELECT id, username, email, saldo, rol
         FROM usuarios
         ORDER BY username ASC"
    );

    $usuarios = [];

    while ($row = $result->fetch_assoc()) {

        $usuarios[] = [
            "id" => (int)$row["id"],
            "username" => $row["username"],
            "email" => $row["email"],
            "saldo" => (float)$row["saldo"],
            "rol" => $row["rol"]
        ];
    }

    echo json_encode([
        "success" => true,
        "usuarios" => $usuarios
    ]);

    exit;
}

/*
 * POST → modificar saldo.
 */

$data = json_decode(file_get_contents("php://input"), true);

$accion = $data["accion"] ?? "";

if ($accion !== "saldo") {

    echo json_encode([
        "success" => false,
        "message" => "Acción inválida"
    ]);

    exit;
}

$id_usuario = (int)($data["id_usuario"] ?? 0);
$saldo = $data["saldo"] ?? null;

if ($id_usuario <= 0 || $saldo === null || !is_numeric($saldo)) {

    echo json_encode([
        "success" => false,
        "message" => "Datos inválidos"
    ]);

    exit;
}

$saldo = (float)$saldo;

if ($saldo < 0) {

    echo json_encode([
        "success" => false,
        "message" => "El saldo no puede ser negativo"
    ]);

    exit;
}

$stmt = $conn->prepare(
    "UPDATE usuarios
     SET saldo = ?
     WHERE id = ?"
);

$stmt->bind_param(
    "di",
    $saldo,
    $id_usuario
);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "No se pudo modificar el saldo"
    ]);

    exit;
}

if ($stmt->affected_rows === 0) {

    /*
     * Puede significar que el usuario no existe
     * o que el saldo ya tenía ese valor.
     */

    $checkUser = $conn->prepare(
        "SELECT id
         FROM usuarios
         WHERE id = ?"
    );

    $checkUser->bind_param("i", $id_usuario);
    $checkUser->execute();

    $userResult = $checkUser->get_result();

    if ($userResult->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "message" => "Usuario no encontrado"
        ]);

        exit;
    }
}

echo json_encode([
    "success" => true,
    "message" => "Saldo actualizado correctamente",
    "saldo" => $saldo
]);

$stmt->close();
$conn->close();
?>