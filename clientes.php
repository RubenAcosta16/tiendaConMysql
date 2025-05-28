<?php
// clientes.php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear Cliente (usando el procedimiento almacenado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cliente'])) {
    $nombre = $_POST['nombre'];
    $apellido_paterno = $_POST['apellido_paterno'];
    $apellido_materno = $_POST['apellido_materno'];
    $email = $_POST['email'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $telefono = $_POST['telefono'];

    // Llama al procedimiento almacenado
    // Nota: MySQLi no tiene un método directo para variables de salida.
    // Usamos una consulta para obtener el valor después de la llamada.
    $stmt = $conn->prepare("CALL sp_crear_cliente(?, ?, ?, ?, ?, ?, @new_client_id)");
    $stmt->bind_param("ssssss", $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento, $telefono);

    if ($stmt->execute()) {
        // Obtener el ID del cliente recién creado
        $result = $conn->query("SELECT @new_client_id as cliente_id");
        $row = $result->fetch_assoc();
        $new_client_id = $row['cliente_id'];
        $message = "<p class='success'>Cliente agregado exitosamente. ID de Cliente: " . $new_client_id . "</p>";
    } else {
        // Manejo de error para email duplicado u otros del SP
        $error_message = $conn->error;
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'for key \'email\'') !== false) {
            $message = "<p class='error'>Error al agregar cliente: El email ya está registrado.</p>";
        } else {
            $message = "<p class='error'>Error al agregar cliente: " . $error_message . "</p>";
        }
    }
    $stmt->close();
    // Limpiar resultados de la llamada al SP si es necesario para futuras consultas
    while ($conn->more_results() && $conn->next_result()) {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

// Actualizar Cliente y Datos de Persona
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cliente'])) {
    $cliente_id = $_POST['cliente_id'];
    $datos_personas_id = $_POST['datos_personas_id'];
    $nombre = $_POST['nombre'];
    $apellido_paterno = $_POST['apellido_paterno'];
    $apellido_materno = $_POST['apellido_materno'];
    $email = $_POST['email'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $telefono = $_POST['telefono'];

    // Primero, actualizar datos_personas
    $stmt_persona = $conn->prepare("UPDATE datos_personas SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, email = ?, fecha_nacimiento = ?, telefono = ? WHERE datos_personas_id = ?");
    $stmt_persona->bind_param("ssssssi", $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento, $telefono, $datos_personas_id);

    if ($stmt_persona->execute()) {
        $message = "<p class='success'>Cliente y datos de persona actualizados exitosamente.</p>";
    } else {
        if ($conn->errno == 1062) { // Error de duplicado de email
            $message = "<p class='error'>Error al actualizar cliente: El email ya existe para otra persona.</p>";
        } else {
            $message = "<p class='error'>Error al actualizar cliente: " . $stmt_persona->error . "</p>";
        }
    }
    $stmt_persona->close();
}

// Eliminar Cliente
if (isset($_GET['delete_cliente'])) {
    $cliente_id = $_GET['delete_cliente'];

    // Obtener el datos_personas_id asociado para eliminar la persona también (debido a ON DELETE CASCADE)
    $stmt_get_persona_id = $conn->prepare("SELECT datos_personas_id FROM clientes WHERE cliente_id = ?");
    $stmt_get_persona_id->bind_param("i", $cliente_id);
    $stmt_get_persona_id->execute();
    $result_get_persona_id = $stmt_get_persona_id->get_result();
    $row_persona_id = $result_get_persona_id->fetch_assoc();
    $datos_personas_id_to_delete = $row_persona_id['datos_personas_id'];
    $stmt_get_persona_id->close();

    // Eliminar el cliente (ON DELETE CASCADE en la FK de clientes_direcciones y datos_personas_id en clientes
    // se encargará de eliminar las entradas relacionadas en clientes_direcciones y datos_personas)
    $stmt = $conn->prepare("DELETE FROM clientes WHERE cliente_id = ?");
    $stmt->bind_param("i", $cliente_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Cliente y sus datos asociados eliminados exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar cliente: " . $stmt->error . "</p>";
    }
    $stmt->close();
}


// --- Obtener datos para el formulario de edición ---
$edit_cliente = null;
if (isset($_GET['edit_cliente'])) {
    $cliente_id = $_GET['edit_cliente'];
    $stmt = $conn->prepare("
        SELECT c.cliente_id, dp.datos_personas_id, dp.nombre, dp.apellido_paterno, dp.apellido_materno, dp.email, dp.fecha_nacimiento, dp.telefono
        FROM clientes c
        JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
        WHERE c.cliente_id = ?
    ");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_cliente = $result->fetch_assoc();
    $stmt->close();
}

// --- Leer Clientes (con datos de persona y direcciones asociadas) ---
$sql = "SELECT
            c.cliente_id,
            dp.nombre,
            dp.apellido_paterno,
            dp.apellido_materno,
            dp.email,
            dp.fecha_nacimiento,
            dp.telefono,
            GROUP_CONCAT(DISTINCT CONCAT(d.calle, ' ', d.numero_exterior, IF(d.numero_interior IS NOT NULL, CONCAT(' Int. ', d.numero_interior), ''), ', ', d.colonia, IF(cd.es_principal, ' (Principal)', '')) SEPARATOR '<br>') AS direcciones_asociadas
        FROM clientes c
        JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
        LEFT JOIN clientes_direcciones cd ON c.cliente_id = cd.cliente_id
        LEFT JOIN direcciones d ON cd.direccion_id = d.direccion_id
        GROUP BY c.cliente_id
        ORDER BY c.cliente_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Clientes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Clientes</h1>
        <p><a href="index.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_cliente ? 'Editar Cliente' : 'Agregar Nuevo Cliente'; ?></h2>
        <form action="clientes.php" method="POST">
            <?php if ($edit_cliente): ?>
                <input type="hidden" name="cliente_id" value="<?php echo $edit_cliente['cliente_id']; ?>">
                <input type="hidden" name="datos_personas_id" value="<?php echo $edit_cliente['datos_personas_id']; ?>">
            <?php endif; ?>

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $edit_cliente ? htmlspecialchars($edit_cliente['nombre']) : ''; ?>" required>

            <label for="apellido_paterno">Apellido Paterno:</label>
            <input type="text" id="apellido_paterno" name="apellido_paterno" value="<?php echo $edit_cliente ? htmlspecialchars($edit_cliente['apellido_paterno']) : ''; ?>" required>

            <label for="apellido_materno">Apellido Materno:</label>
            <input type="text" id="apellido_materno" name="apellido_materno" value="<?php echo $edit_cliente ? htmlspecialchars($edit_cliente['apellido_materno']) : ''; ?>">

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo $edit_cliente ? htmlspecialchars($edit_cliente['email']) : ''; ?>" required>

            <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo $edit_cliente ? htmlspecialchars($edit_cliente['fecha_nacimiento']) : ''; ?>">

            <label for="telefono">Teléfono:</label>
            <input type="text" id="telefono" name="telefono" value="<?php echo $edit_cliente ? htmlspecialchars($edit_cliente['telefono']) : ''; ?>">

            <button type="submit" name="<?php echo $edit_cliente ? 'update_cliente' : 'add_cliente'; ?>">
                <?php echo $edit_cliente ? 'Actualizar Cliente' : 'Agregar Cliente'; ?>
            </button>
        </form>

        <h2>Listado de Clientes</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Cliente</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Direcciones Asociadas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['cliente_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                            <td><?php echo $row['direcciones_asociadas'] ? $row['direcciones_asociadas'] : 'Ninguna'; ?>
                                <br><a href="clientes_direcciones.php?cliente_id=<?php echo $row['cliente_id']; ?>">Administrar Direcciones</a>
                            </td>
                            <td>
                                <a href="clientes.php?edit_cliente=<?php echo $row['cliente_id']; ?>">Editar</a> |
                                <a href="clientes.php?delete_cliente=<?php echo $row['cliente_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este cliente? Esto también eliminará sus datos de persona y las relaciones de dirección.');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay clientes registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>