<?php

session_start(); 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_cliente'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("
            SELECT c.cliente_id, dp.nombre, dp.email, c.password 
            FROM clientes c
            JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
            WHERE dp.email = ?
        ");

        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de cliente: ' . $conn->error]);
            $conn->close();
            exit();
        }

        $stmt->bind_param("s", $email);

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error al ejecutar la consulta de cliente: ' . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit();
        }

        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();

        if ($cliente && password_verify($password, $cliente['password'])) {
            $_SESSION['user_id'] = $cliente['cliente_id'];
            $_SESSION['user_name'] = $cliente['nombre'];
            $_SESSION['user_role'] = 'cliente';
            echo json_encode(['success' => true, 'role' => 'cliente', 'userName' => $cliente['nombre']]); 
        } else {
            echo json_encode(['success' => false, 'message' => 'Credenciales de cliente incorrectas o cliente no encontrado.']);
        }
    } elseif (isset($_POST['login_admin'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $admin_username = 'admin';
$admin_password = '12345678';

if ($username === $admin_username && $password === $admin_password) {
    $_SESSION['user_id'] = 0; 
    $_SESSION['user_name'] = 'Administrador';
    $_SESSION['user_role'] = 'admin';
    echo json_encode(['success' => true, 'role' => 'admin', 'userName' => 'Administrador']);
} else {
    echo json_encode(['success' => false, 'message' => 'Credenciales de administrador incorrectas.']);
}
    }
} elseif (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente.']);
    exit();
}

$conn->close();
?>