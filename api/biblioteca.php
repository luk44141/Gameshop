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
        j.id,
        j.nombre,
        j.descripcion,
        j.genero,
        cd.precio,
        c.fecha
     FROM compra_detalle cd
     INNER JOIN compras c
        ON c.id = cd.id_compra
     INNER JOIN juegos j
        ON j.id = cd.id_juego
     WHERE c.id_usuario = ?
     ORDER BY c.fecha DESC"
);

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$result = $stmt->get_result();

$biblioteca = [];

while ($row = $result->fetch_assoc()) {

    $biblioteca[] = [
        "id" => (int)$row["id"],
        "nombre" => $row["nombre"],
        "descripcion" => $row["descripcion"],
        "genero" => $row["genero"],
        "precio" => (float)$row["precio"],
        "fecha_compra" => $row["fecha"]
    ];
}

echo json_encode([
    "success" => true,
    "juegos" => $biblioteca
]);

$stmt->close();
$conn->close();
?>