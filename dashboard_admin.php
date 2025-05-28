<?php
require_once 'protected_route.php'; // ¡PROTEGER ESTA PÁGINA!
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Administrador</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Administrador)</h1>
        <p>Desde aquí puedes gestionar todos los aspectos del negocio.</p>
        <button onclick="logout()">Cerrar Sesión</button>

        <nav>
            <h2>Módulos de Administración</h2>
            <ul>
                <li><a href="sucursales.php">Administrar Sucursales</a></li>
                <li><a href="puesto.php">Administrar Puestos</a></li>
                <li><a href="empleados.php">Administrar Empleados</a></li>
                <li><a href="datos_personas.php">Administrar Datos de Personas</a></li>
                <li><a href="direcciones.php">Administrar Direcciones</a></li>
            </ul>
            <hr>
            <ul>
                <li><a href="categorias.php">Administrar Categorías</a></li>
                <li><a href="proveedores.php">Administrar Proveedores</a></li>
                <li><a href="productos.php">Administrar Productos</a></li>
            </ul>
            <hr>
            <ul>
                <li><a href="clientes.php">Administrar Clientes</a></li>
                <li><a href="reseñas.php">Administrar Reseñas</a></li>
            </ul>
            <hr>
            <ul>
                <li><a href="ordenes_compra.php">Administrar Órdenes de Compra</a></li>
                <li><a href="pagos.php">Administrar Pagos</a></li>
            </ul>
        </nav>
    </div>
    <script src="./js/auth.js"></script>
</body>
</html>