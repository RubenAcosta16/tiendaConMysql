<?php
session_start();
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_cliente'])) {
    $nombre = $_POST['nombre'];
    $apellido_paterno = $_POST['apellido_paterno'];
    $apellido_materno = $_POST['apellido_materno'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $telefono = $_POST['telefono'];
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) && !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : NULL;

    if (empty($nombre) || empty($apellido_paterno) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Nombre, Apellido Paterno, Email y Contraseña son obligatorios.']);
        $conn->close();
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Formato de email inválido.']);
        $conn->close();
        exit();
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
        $conn->close();
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $conn->begin_transaction();

    try {
        $stmt_check_email = $conn->prepare("SELECT datos_personas_id FROM datos_personas WHERE email = ?");
        if ($stmt_check_email === false) {
            throw new Exception("Error al preparar la verificación de email: " . $conn->error);
        }
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $result_check_email = $stmt_check_email->get_result();
        if ($result_check_email->num_rows > 0) {
            throw new Exception("El email ya está registrado. Por favor, inicia sesión.");
        }
        $stmt_check_email->close();

        $stmt_dp = $conn->prepare("INSERT INTO datos_personas (nombre, apellido_paterno, apellido_materno, email, fecha_nacimiento, telefono) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_dp === false) {
            throw new Exception("Error al preparar inserción en datos_personas: " . $conn->error);
        }
        $stmt_dp->bind_param("ssssss", $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento, $telefono);
        
        if (!$stmt_dp->execute()) {
            throw new Exception("Error al insertar datos personales: " . $stmt_dp->error);
        }
        $datos_personas_id = $conn->insert_id; 
        $stmt_dp->close();

        $stmt_cliente = $conn->prepare("INSERT INTO clientes (datos_personas_id, password) VALUES (?, ?)");
        if ($stmt_cliente === false) {
            throw new Exception("Error al preparar inserción en clientes: " . $conn->error);
        }
        $stmt_cliente->bind_param("is", $datos_personas_id, $hashed_password);

        if (!$stmt_cliente->execute()) {
            throw new Exception("Error al registrar cliente (tabla clientes): " . $stmt_cliente->error);
        }
        $cliente_id = $conn->insert_id; 
        $stmt_cliente->close();

        $conn->commit();

        $_SESSION['user_id'] = $cliente_id;
        $_SESSION['user_name'] = $nombre;
        $_SESSION['user_role'] = 'cliente';
        echo json_encode(['success' => true, 'role' => 'cliente', 'userName' => $nombre]);

    } catch (Exception $e) {
        $conn->rollback(); 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    $conn->close();
}
?>