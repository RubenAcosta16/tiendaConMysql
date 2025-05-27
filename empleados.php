<?php
// empleados.php
require_once 'db.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear Empleado (usando el procedimiento almacenado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_empleado'])) {
    $nombre = $_POST['nombre'];
    $apellido_paterno = $_POST['apellido_paterno'];
    $apellido_materno = $_POST['apellido_materno'];
    $email = $_POST['email'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $telefono = $_POST['telefono'];
    $sucursal_id = $_POST['sucursal_id'];
    $puesto_id = $_POST['puesto_id'];
    $fecha_contratacion = $_POST['fecha_contratacion'];

    // Llama al procedimiento almacenado
    $stmt = $conn->prepare("CALL sp_crear_empleado(?, ?, ?, ?, ?, ?, ?, ?, ?, @new_empleado_id)");
    $stmt->bind_param("ssssssiis",
        $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento,
        $telefono, $sucursal_id, $puesto_id, $fecha_contratacion
    );

    if ($stmt->execute()) {
        // Obtener el ID del empleado recién creado
        $result = $conn->query("SELECT @new_empleado_id as empleado_id");
        $row = $result->fetch_assoc();
        $new_empleado_id = $row['empleado_id'];
        $message = "<p class='success'>Empleado agregado exitosamente. ID de Empleado: " . $new_empleado_id . "</p>";
    } else {
        $error_message = $conn->error;
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'for key \'email\'') !== false) {
            $message = "<p class='error'>Error al agregar empleado: El email ya está registrado para otra persona.</p>";
        } elseif (strpos($error_message, 'La sucursal especificada no existe.') !== false || strpos($error_message, 'El puesto especificado no existe.') !== false) {
             $message = "<p class='error'>Error: " . $error_message . "</p>";
        } else {
            $message = "<p class='error'>Error al agregar empleado: " . $error_message . "</p>";
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

// Actualizar Empleado y Datos de Persona
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_empleado'])) {
    $empleado_id = $_POST['empleado_id'];
    $datos_personas_id = $_POST['datos_personas_id'];
    $sucursal_id = $_POST['sucursal_id'];
    $puesto_id = $_POST['puesto_id'];
    $fecha_contratacion = $_POST['fecha_contratacion'];

    $nombre = $_POST['nombre'];
    $apellido_paterno = $_POST['apellido_paterno'];
    $apellido_materno = $_POST['apellido_materno'];
    $email = $_POST['email'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $telefono = $_POST['telefono'];

    // Iniciar una transacción para asegurar la consistencia
    $conn->begin_transaction();
    try {
        // 1. Actualizar datos_personas
        $stmt_persona = $conn->prepare("UPDATE datos_personas SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, email = ?, fecha_nacimiento = ?, telefono = ? WHERE datos_personas_id = ?");
        $stmt_persona->bind_param("ssssssi", $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento, $telefono, $datos_personas_id);
        if (!$stmt_persona->execute()) {
            throw new Exception("Error al actualizar datos personales: " . $stmt_persona->error);
        }
        $stmt_persona->close();

        // 2. Actualizar datos del empleado
        $stmt_empleado = $conn->prepare("UPDATE empleados SET sucursal_id = ?, puesto_id = ?, fecha_contratacion = ? WHERE empleado_id = ?");
        $stmt_empleado->bind_param("iisi", $sucursal_id, $puesto_id, $fecha_contratacion, $empleado_id);
        if (!$stmt_empleado->execute()) {
            throw new Exception("Error al actualizar datos del empleado: " . $stmt_empleado->error);
        }
        $stmt_empleado->close();

        $conn->commit();
        $message = "<p class='success'>Empleado y datos personales actualizados exitosamente.</p>";

    } catch (Exception $e) {
        $conn->rollback();
        $message = "<p class='error'>" . $e->getMessage() . "</p>";
        if ($conn->errno == 1062) { // Error de duplicado de email
            $message = "<p class='error'>Error al actualizar: El email ya existe para otra persona.</p>";
        }
    }
}

// Eliminar Empleado
if (isset($_GET['delete_empleado'])) {
    $empleado_id = $_GET['delete_empleado'];

    // Al eliminar el empleado, debido a ON DELETE CASCADE, también se eliminarán
    // los datos de la persona asociada en 'datos_personas'.
    $stmt = $conn->prepare("DELETE FROM empleados WHERE empleado_id = ?");
    $stmt->bind_param("i", $empleado_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Empleado y sus datos personales eliminados exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar empleado: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// --- Obtener datos para el formulario de edición ---
$edit_empleado = null;
if (isset($_GET['edit_empleado'])) {
    $empleado_id = $_GET['edit_empleado'];
    $stmt = $conn->prepare("
        SELECT e.empleado_id, e.sucursal_id, e.puesto_id, e.fecha_contratacion,
               dp.datos_personas_id, dp.nombre, dp.apellido_paterno, dp.apellido_materno,
               dp.email, dp.fecha_nacimiento, dp.telefono
        FROM empleados e
        JOIN datos_personas dp ON e.datos_personas_id = dp.datos_personas_id
        WHERE e.empleado_id = ?
    ");
    $stmt->bind_param("i", $empleado_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_empleado = $result->fetch_assoc();
    $stmt->close();
}

// --- Obtener sucursales para el select ---
$sucursales_result = $conn->query("SELECT sucursal_id, nombre FROM sucursales ORDER BY nombre");
$sucursales = [];
if ($sucursales_result->num_rows > 0) {
    while ($row = $sucursales_result->fetch_assoc()) {
        $sucursales[] = $row;
    }
}

// --- Obtener puestos para el select ---
$puestos_result = $conn->query("SELECT puesto_id, nombre_puesto FROM puesto ORDER BY nombre_puesto");
$puestos = [];
if ($puestos_result->num_rows > 0) {
    while ($row = $puestos_result->fetch_assoc()) {
        $puestos[] = $row;
    }
}

// --- Leer Empleados (con datos de persona, sucursal y puesto) ---
$sql = "SELECT
            e.empleado_id, e.fecha_contratacion,
            dp.nombre, dp.apellido_paterno, dp.apellido_materno, dp.email, dp.telefono,
            s.nombre AS nombre_sucursal,
            p.nombre_puesto AS nombre_puesto
        FROM empleados e
        JOIN datos_personas dp ON e.datos_personas_id = dp.datos_personas_id
        JOIN sucursales s ON e.sucursal_id = s.sucursal_id
        JOIN puesto p ON e.puesto_id = p.puesto_id
        ORDER BY e.empleado_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Empleados</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Empleados</h1>
        <p><a href="index.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_empleado ? 'Editar Empleado' : 'Agregar Nuevo Empleado'; ?></h2>
        <form action="empleados.php" method="POST">
            <?php if ($edit_empleado): ?>
                <input type="hidden" name="empleado_id" value="<?php echo $edit_empleado['empleado_id']; ?>">
                <input type="hidden" name="datos_personas_id" value="<?php echo $edit_empleado['datos_personas_id']; ?>">
            <?php endif; ?>

            <fieldset>
                <legend>Datos Personales</legend>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['nombre']) : ''; ?>" required>

                <label for="apellido_paterno">Apellido Paterno:</label>
                <input type="text" id="apellido_paterno" name="apellido_paterno" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['apellido_paterno']) : ''; ?>" required>

                <label for="apellido_materno">Apellido Materno:</label>
                <input type="text" id="apellido_materno" name="apellido_materno" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['apellido_materno']) : ''; ?>">

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['email']) : ''; ?>" required>

                <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['fecha_nacimiento']) : ''; ?>">

                <label for="telefono">Teléfono:</label>
                <input type="text" id="telefono" name="telefono" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['telefono']) : ''; ?>">
            </fieldset>

            <fieldset>
                <legend>Datos Laborales</legend>
                <label for="sucursal_id">Sucursal:</label>
                <select id="sucursal_id" name="sucursal_id" required>
                    <option value="">Selecciona una sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo $sucursal['sucursal_id']; ?>"
                            <?php echo ($edit_empleado && $edit_empleado['sucursal_id'] == $sucursal['sucursal_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="puesto_id">Puesto:</label>
                <select id="puesto_id" name="puesto_id" required>
                    <option value="">Selecciona un puesto</option>
                    <?php foreach ($puestos as $puesto): ?>
                        <option value="<?php echo $puesto['puesto_id']; ?>"
                            <?php echo ($edit_empleado && $edit_empleado['puesto_id'] == $puesto['puesto_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($puesto['nombre_puesto']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="fecha_contratacion">Fecha de Contratación:</label>
                <input type="date" id="fecha_contratacion" name="fecha_contratacion" value="<?php echo $edit_empleado ? htmlspecialchars($edit_empleado['fecha_contratacion']) : date('Y-m-d'); ?>" required>
            </fieldset>

            <button type="submit" name="<?php echo $edit_empleado ? 'update_empleado' : 'add_empleado'; ?>">
                <?php echo $edit_empleado ? 'Actualizar Empleado' : 'Agregar Empleado'; ?>
            </button>
        </form>

        <h2>Listado de Empleados</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Sucursal</th>
                        <th>Puesto</th>
                        <th>Fecha Contratación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['empleado_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_sucursal']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_puesto']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_contratacion']); ?></td>
                            <td>
                                <a href="empleados.php?edit_empleado=<?php echo $row['empleado_id']; ?>">Editar</a> |
                                <a href="empleados.php?delete_empleado=<?php echo $row['empleado_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este empleado? Esto también eliminará sus datos personales.');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay empleados registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>