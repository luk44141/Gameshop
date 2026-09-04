<?php

session_start();

header("Content-Type: application/json");

require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "No estás conectado"
    ]);
    exit;
}

$id_usuario = (int)$_SESSION["usuario_id"];

$stmt = $conn->prepare(
    "SELECT
        c.id,
        c.fecha,
        c.total,
        cd.id_juego,
        cd.nombre_juego,
        cd.precio
     FROM compras c
     INNER JOIN compra_detalle cd
        ON cd.id_compra = c.id
     WHERE c.id_usuario = ?
     ORDER BY c.fecha DESC, c.id DESC"
);

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$result = $stmt->get_result();

$compras = [];

while ($row = $result->fetch_assoc()) {

    $id = (int)$row["id"];

    if (!isset($compras[$id])) {

        $compras[$id] = [
            "id" => $id,
            "fecha" => $row["fecha"],
            "total" => (float)$row["total"],
            "juegos" => []
        ];
    }

    $compras[$id]["juegos"][] = [
        "id" => (int)$row["id_juego"],
        "nombre" => $row["nombre_juego"],
        "precio" => (float)$row["precio"]
    ];
}

$compras = array_values($compras);

echo json_encode([
    "success" => true,
    "compras" => $compras
]);

$stmt->close();
$conn->close();
?>