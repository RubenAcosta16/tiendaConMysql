<?php
// protected_route.php
session_start();

// Determinar el rol requerido para la página actual
$current_page = basename($_SERVER['PHP_SELF']);
$required_role = null;

// Páginas que solo el admin puede ver
$admin_pages = [
    'dashboard_admin.php',
    'categorias.php',
    'productos.php',
    'proveedores.php',
    'productos_categorias.php',
    'datos_personas.php', // El admin también puede gestionar datos de personas
    'sucursales.php',
    'puesto.php',
    'empleados.php',
    'direcciones.php',
    'clientes.php', // Clientes puede ser gestionado por admin
    'reseñas.php',
    'ordenes_compra.php',
    'pagos.php'
];

// Páginas que solo el cliente puede ver
$cliente_pages = [
    'dashboard_cliente.php'
];

// Asignar el rol requerido
if (in_array($current_page, $admin_pages)) {
    $required_role = 'admin';
} elseif (in_array($current_page, $cliente_pages)) {
    $required_role = 'cliente';
} else {
    // Si la página no está en ninguna lista, pero quieres que esté protegida para cualquier usuario logueado
    if (isset($_SESSION['user_id'])) {
        $required_role = 'any_logged_in'; // Un indicador para cualquier rol logueado
    }
}

// Verificar si hay una sesión activa
if (!isset($_SESSION['user_id'])) {
    // No hay sesión, redirigir al index
    header("Location: index.php");
    exit();
}

// Verificar el rol si la página lo requiere
if ($required_role && $required_role !== 'any_logged_in') {
    if ($_SESSION['user_role'] !== $required_role) {
        // Rol incorrecto, redirigir a su dashboard o al index
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

// Si la página es dashboard_cliente.php, intenta obtener el nombre real del cliente
// Esto es útil para mostrar el nombre del usuario logueado
if ($current_page === 'dashboard_cliente.php' && $_SESSION['user_role'] === 'cliente') {
    require_once 'db.php';
    $conn = connectDB();
    // Unir con datos_personas para obtener el nombre
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
// Si la página es dashboard_admin.php y es admin, el nombre ya está hardcodeado en auth.php

?>