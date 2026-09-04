<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "conexion.php";

$result = $conn->query(
    "SELECT id, nombre, descripcion, genero, precio, disponible
     FROM juegos
     WHERE disponible = 1
     ORDER BY nombre ASC"
);

$juegos = [];

while ($row = $result->fetch_assoc()) {
    $juegos[] = [
        "id" => (int)$row["id"],
        "nombre" => $row["nombre"],
        "descripcion" => $row["descripcion"],
        "genero" => $row["genero"],
        "precio" => (float)$row["precio"],
        "disponible" => (bool)$row["disponible"]
    ];
}

echo json_encode($juegos);

$conn->close();
?>