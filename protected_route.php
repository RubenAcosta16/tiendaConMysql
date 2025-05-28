<?php
session_start();

$current_page = basename($_SERVER['PHP_SELF']);
$required_role = null;

$admin_pages = [
    'dashboard_admin.php',
    'categorias.php',
    'productos.php',
    'proveedores.php',
    'productos_categorias.php',
    'datos_personas.php', 
    'sucursales.php',
    'puesto.php',
    'empleados.php',
    'direcciones.php',
    'clientes.php', 
    'reseñas.php',
    'ordenes_compra.php',
    'pagos.php'
];

$cliente_pages = [
    'dashboard_cliente.php'
];

if (in_array($current_page, $admin_pages)) {
    $required_role = 'admin';
} elseif (in_array($current_page, $cliente_pages)) {
    $required_role = 'cliente';
} else {
    if (isset($_SESSION['user_id'])) {
        $required_role = 'any_logged_in'; 
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($required_role && $required_role !== 'any_logged_in') {
    if ($_SESSION['user_role'] !== $required_role) {
 
        if ($_SESSION['user_role'] === 'admin') {
            header("Location: dashboard_admin.php");
        } elseif ($_SESSION['user_role'] === 'cliente') {
            header("Location: dashboard_cliente.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}


if ($current_page === 'dashboard_cliente.php' && $_SESSION['user_role'] === 'cliente') {
    require_once 'db.php';
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT dp.nombre 
        FROM clientes c
        JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
        WHERE c.cliente_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente_data = $result->fetch_assoc();
    if ($cliente_data) {
        $_SESSION['user_name'] = $cliente_data['nombre'];
    }
    $stmt->close();
    $conn->close();
}

?>