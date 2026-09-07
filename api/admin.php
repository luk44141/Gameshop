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
|--------------------------------------------------------------------------
| Verificar sesión
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["usuario_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "No estás conectado"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar que el usuario sea administrador
|--------------------------------------------------------------------------
*/

$id_admin = (int) $_SESSION["usuario_id"];

$stmt = $conn->prepare(
    "SELECT rol
     FROM usuarios
     WHERE id = ?"
);

$stmt->bind_param("i", $id_admin);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Usuario no encontrado"
    ]);

    exit;
}

$admin = $result->fetch_assoc();

$stmt->close();


if ($admin["rol"] !== "admin") {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "No tenés permisos de administrador"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
| Devuelve todos los usuarios para el panel de administración.
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "GET") {

    $result = $conn->query(
        "SELECT id, username, email, saldo, rol
         FROM usuarios
         ORDER BY id ASC"
    );

    $usuarios = [];

    while ($row = $result->fetch_assoc()) {

        $usuarios[] = [
            "id" => (int) $row["id"],
            "username" => $row["username"],
            "email" => $row["email"],
            "saldo" => (float) $row["saldo"],
            "rol" => $row["rol"]
        ];
    }

    echo json_encode([
        "success" => true,
        "usuarios" => $usuarios
    ]);

    $conn->close();

    exit;
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $accion = $data["accion"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | Modificar saldo
    |--------------------------------------------------------------------------
    */

    if ($accion === "actualizar_saldo") {

        $id_usuario = (int) ($data["id_usuario"] ?? 0);
        $saldo = $data["saldo"] ?? null;

        if ($id_usuario <= 0) {

            echo json_encode([
                "success" => false,
                "message" => "Usuario inválido"
            ]);

            exit;
        }

        if ($saldo === null || !is_numeric($saldo)) {

            echo json_encode([
                "success" => false,
                "message" => "Saldo inválido"
            ]);

            exit;
        }

        $saldo = (float) $saldo;

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
                "message" => "No se pudo actualizar el saldo"
            ]);

            $stmt->close();
            exit;
        }

        $stmt->close();


        echo json_encode([
            "success" => true,
            "message" => "Saldo actualizado correctamente"
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Agregar juego a la biblioteca
    |--------------------------------------------------------------------------
    | Se crea una compra de prueba con el juego.
    | NO se descuenta saldo.
    |--------------------------------------------------------------------------
    */

    if ($accion === "agregar_juego") {

        $id_usuario = (int) ($data["id_usuario"] ?? 0);
        $id_juego = (int) ($data["id_juego"] ?? 0);

        if ($id_usuario <= 0 || $id_juego <= 0) {

            echo json_encode([
                "success" => false,
                "message" => "Usuario o juego inválido"
            ]);

            exit;
        }


        try {

            $conn->begin_transaction();


            /*
            | Verificar usuario
            */

            $stmt = $conn->prepare(
                "SELECT id
                 FROM usuarios
                 WHERE id = ?"
            );

            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("El usuario no existe");
            }

            $stmt->close();


            /*
            | Obtener juego
            */

            $stmt = $conn->prepare(
                "SELECT id, nombre, precio
                 FROM juegos
                 WHERE id = ?"
            );

            $stmt->bind_param("i", $id_juego);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("El juego no existe");
            }

            $juego = $result->fetch_assoc();

            $stmt->close();


            /*
            | Verificar si ya lo tiene
            */

            $stmt = $conn->prepare(
                "SELECT cd.id
                 FROM compra_detalle cd
                 INNER JOIN compras c
                    ON c.id = cd.id_compra
                 WHERE c.id_usuario = ?
                   AND cd.id_juego = ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "ii",
                $id_usuario,
                $id_juego
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                throw new Exception(
                    "El usuario ya tiene este juego en su biblioteca"
                );
            }

            $stmt->close();


            /*
            | Crear compra de prueba
            |
            | No se descuenta saldo.
            */

            $total = (float) $juego["precio"];

            $stmt = $conn->prepare(
                "INSERT INTO compras
                    (id_usuario, total)
                 VALUES (?, ?)"
            );

            $stmt->bind_param(
                "id",
                $id_usuario,
                $total
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "No se pudo crear la compra"
                );
            }

            $id_compra = $conn->insert_id;

            $stmt->close();


            /*
            | Agregar juego a la compra
            */

            $stmt = $conn->prepare(
                "INSERT INTO compra_detalle
                    (id_compra, id_juego, nombre_juego, precio)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iisd",
                $id_compra,
                $id_juego,
                $juego["nombre"],
                $juego["precio"]
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "No se pudo agregar el juego a la biblioteca"
                );
            }

            $stmt->close();


            $conn->commit();


            echo json_encode([
                "success" => true,
                "message" =>
                    "Juego agregado a la biblioteca correctamente"
            ]);

            exit;

        } catch (Exception $e) {

            $conn->rollback();

            echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Sacar juego de la biblioteca
    |--------------------------------------------------------------------------
    | Esto elimina solamente la propiedad del usuario.
    |
    | NO elimina el juego de la tabla juegos.
    | NO afecta a otros usuarios.
    |
    | Si la compra queda vacía, también se elimina la compra.
    |--------------------------------------------------------------------------
    */

    if ($accion === "sacar_juego") {

        $id_usuario = (int) ($data["id_usuario"] ?? 0);
        $id_juego = (int) ($data["id_juego"] ?? 0);

        if ($id_usuario <= 0 || $id_juego <= 0) {

            echo json_encode([
                "success" => false,
                "message" => "Usuario o juego inválido"
            ]);

            exit;
        }


        try {

            $conn->begin_transaction();


            /*
            | Buscar la compra que contiene el juego
            */

            $stmt = $conn->prepare(
                "SELECT
                    cd.id AS detalle_id,
                    cd.id_compra
                 FROM compra_detalle cd
                 INNER JOIN compras c
                    ON c.id = cd.id_compra
                 WHERE c.id_usuario = ?
                   AND cd.id_juego = ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "ii",
                $id_usuario,
                $id_juego
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {

                throw new Exception(
                    "El usuario no tiene este juego en su biblioteca"
                );
            }

            $compra = $result->fetch_assoc();

            $detalle_id = (int) $compra["detalle_id"];
            $id_compra = (int) $compra["id_compra"];

            $stmt->close();


            /*
            | Eliminar solamente el detalle del juego
            */

            $stmt = $conn->prepare(
                "DELETE FROM compra_detalle
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "i",
                $detalle_id
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "No se pudo sacar el juego de la biblioteca"
                );
            }

            $stmt->close();


            /*
            | Comprobar si la compra quedó sin juegos
            */

            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cantidad
                 FROM compra_detalle
                 WHERE id_compra = ?"
            );

            $stmt->bind_param(
                "i",
                $id_compra
            );

            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $cantidad = (int) $row["cantidad"];

            $stmt->close();


            /*
            | Si no quedan juegos, eliminar la compra vacía
            */

            if ($cantidad === 0) {

                $stmt = $conn->prepare(
                    "DELETE FROM compras
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "i",
                    $id_compra
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        "No se pudo eliminar la compra vacía"
                    );
                }

                $stmt->close();
            }


            $conn->commit();


            echo json_encode([
                "success" => true,
                "message" =>
                    "Juego sacado de la biblioteca correctamente"
            ]);

            exit;

        } catch (Exception $e) {

            $conn->rollback();

            echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Acción desconocida
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => false,
        "message" => "Acción no válida"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Método HTTP no permitido
|--------------------------------------------------------------------------
*/

http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Método no permitido"
]);

$conn->close();

?>