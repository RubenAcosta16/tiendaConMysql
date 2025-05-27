<?php
// db.php
require_once 'config.php';

function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }
    // Establecer el charset a UTF-8 para evitar problemas con caracteres especiales
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>