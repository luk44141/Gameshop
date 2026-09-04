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

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Tenés que iniciar sesión"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$items = $data["items"] ?? [];

if (!is_array($items) || count($items) === 0) {
    echo json_encode([
        "success" => false,
        "message" => "El carrito está vacío"
    ]);
    exit;
}

$id_usuario = (int)$_SESSION["usuario_id"];

$conn->begin_transaction();

try {

    /*
     * 1. Obtener el saldo del usuario.
     * FOR UPDATE bloquea la fila durante la transacción.
     */

    $userStmt = $conn->prepare(
        "SELECT saldo
         FROM usuarios
         WHERE id = ?
         FOR UPDATE"
    );

    $userStmt->bind_param("i", $id_usuario);
    $userStmt->execute();

    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        throw new Exception("Usuario no encontrado");
    }

    $user = $userResult->fetch_assoc();

    $saldo = (float)$user["saldo"];

    /*
     * 2. Preparar consultas.
     */

    $gameStmt = $conn->prepare(
        "SELECT id, nombre, precio
         FROM juegos
         WHERE id = ? AND disponible = 1"
    );

    $ownedStmt = $conn->prepare(
        "SELECT cd.id_juego
         FROM compra_detalle cd
         INNER JOIN compras c
             ON c.id = cd.id_compra
         WHERE c.id_usuario = ?
           AND cd.id_juego = ?
         LIMIT 1"
    );

    /*
     * 3. Revisar todos los juegos y calcular el total
     *    usando los precios reales de la BD.
     */

    $juegosCompra = [];
    $total = 0;

    foreach ($items as $item) {

        $id_juego = (int)($item["id"] ?? 0);

        if ($id_juego <= 0) {
            throw new Exception("Juego inválido");
        }

        $gameStmt->bind_param("i", $id_juego);
        $gameStmt->execute();

        $gameResult = $gameStmt->get_result();

        if ($gameResult->num_rows === 0) {
            throw new Exception("El juego no existe o no está disponible");
        }

        $juego = $gameResult->fetch_assoc();

        /*
         * Comprobar si ya pertenece a la biblioteca.
         */

        $ownedStmt->bind_param(
            "ii",
            $id_usuario,
            $id_juego
        );

        $ownedStmt->execute();

        $ownedResult = $ownedStmt->get_result();

        if ($ownedResult->num_rows > 0) {
            throw new Exception(
                "Ya tenés el juego: " . $juego["nombre"]
            );
        }

        $precio = (float)$juego["precio"];

        $juegosCompra[] = [
            "id" => (int)$juego["id"],
            "nombre" => $juego["nombre"],
            "precio" => $precio
        ];

        $total += $precio;
    }

    /*
     * 4. Verificar saldo.
     */

    if ($saldo < $total) {
        throw new Exception(
            "Saldo insuficiente. Tenés $" .
            number_format($saldo, 2, ",", ".") .
            " y necesitás $" .
            number_format($total, 2, ",", ".")
        );
    }

    /*
     * 5. Crear la compra.
     */

    $compraStmt = $conn->prepare(
        "INSERT INTO compras
         (id_usuario, fecha, total)
         VALUES (?, NOW(), ?)"
    );

    $compraStmt->bind_param(
        "id",
        $id_usuario,
        $total
    );

    if (!$compraStmt->execute()) {
        throw new Exception("No se pudo crear la compra");
    }

    $id_compra = $conn->insert_id;

    /*
     * 6. Guardar cada juego comprado.
     */

    $detalleStmt = $conn->prepare(
        "INSERT INTO compra_detalle
         (id_compra, id_juego, nombre_juego, precio)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($juegosCompra as $juego) {

        $detalleStmt->bind_param(
            "iisd",
            $id_compra,
            $juego["id"],
            $juego["nombre"],
            $juego["precio"]
        );

        if (!$detalleStmt->execute()) {
            throw new Exception(
                "No se pudo guardar el detalle de la compra"
            );
        }
    }

    /*
     * 7. Descontar saldo.
     */

    $nuevoSaldo = $saldo - $total;

    $saldoStmt = $conn->prepare(
        "UPDATE usuarios
         SET saldo = ?
         WHERE id = ?"
    );

    $saldoStmt->bind_param(
        "di",
        $nuevoSaldo,
        $id_usuario
    );

    if (!$saldoStmt->execute()) {
        throw new Exception("No se pudo actualizar el saldo");
    }

    /*
     * 8. Todo salió bien.
     */

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Compra realizada correctamente",
        "id_compra" => $id_compra,
        "total" => $total,
        "saldo" => $nuevoSaldo
    ]);

} catch (Exception $e) {

    /*
     * Si algo falla, se deshace absolutamente todo.
     */

    $conn->rollback();

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>