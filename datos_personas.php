<?php
// datos_personas.php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear/Actualizar Datos de Persona
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_persona'])) {
        $nombre = $_POST['nombre'];
        $apellido_paterno = $_POST['apellido_paterno'];
        $apellido_materno = $_POST['apellido_materno'];
        $email = $_POST['email'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $telefono = $_POST['telefono'];

        $stmt = $conn->prepare("INSERT INTO datos_personas (nombre, apellido_paterno, apellido_materno, email, fecha_nacimiento, telefono) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento, $telefono);
        if ($stmt->execute()) {
            $message = "<p class='success'>Datos de persona agregados exitosamente.</p>";
        } else {
            if ($conn->errno == 1062) { // Error de duplicado de email
                $message = "<p class='error'>Error al agregar datos de persona: El email ya existe.</p>";
            } else {
                $message = "<p class='error'>Error al agregar datos de persona: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } elseif (isset($_POST['update_persona'])) {
        $datos_personas_id = $_POST['datos_personas_id'];
        $nombre = $_POST['nombre'];
        $apellido_paterno = $_POST['apellido_paterno'];
        $apellido_materno = $_POST['apellido_materno'];
        $email = $_POST['email'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $telefono = $_POST['telefono'];

        $stmt = $conn->prepare("UPDATE datos_personas SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, email = ?, fecha_nacimiento = ?, telefono = ? WHERE datos_personas_id = ?");
        $stmt->bind_param("ssssssi", $nombre, $apellido_paterno, $apellido_materno, $email, $fecha_nacimiento, $telefono, $datos_personas_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Datos de persona actualizados exitosamente.</p>";
        } else {
            if ($conn->errno == 1062) { // Error de duplicado de email
                $message = "<p class='error'>Error al actualizar datos de persona: El email ya existe.</p>";
            } else {
                $message = "<p class='error'>Error al actualizar datos de persona: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
}

// Eliminar Datos de Persona
if (isset($_GET['delete_persona'])) {
    $datos_personas_id = $_GET['delete_persona'];

    // Verificar si esta persona es un cliente
    $check_client_stmt = $conn->prepare("SELECT COUNT(*) FROM clientes WHERE datos_personas_id = ?");
    $check_client_stmt->bind_param("i", $datos_personas_id);
    $check_client_stmt->execute();
    $check_client_result = $check_client_stmt->get_result();
    $is_client = $check_client_result->fetch_row()[0] > 0;
    $check_client_stmt->close();

    if ($is_client) {
        $message = "<p class='error'>No se puede eliminar esta persona porque está asociada a un cliente. Primero debe eliminar al cliente.</p>";
    } else {
        $stmt = $conn->prepare("DELETE FROM datos_personas WHERE datos_personas_id = ?");
        $stmt->bind_param("i", $datos_personas_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Datos de persona eliminados exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al eliminar datos de persona: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// --- Obtener datos para el formulario de edición ---
$edit_persona = null;
if (isset($_GET['edit_persona'])) {
    $datos_personas_id = $_GET['edit_persona'];
    $stmt = $conn->prepare("SELECT * FROM datos_personas WHERE datos_personas_id = ?");
    $stmt->bind_param("i", $datos_personas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_persona = $result->fetch_assoc();
    $stmt->close();
}

// --- Leer Datos de Personas ---
$sql = "SELECT * FROM datos_personas ORDER BY datos_personas_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Datos de Personas</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Datos de Personas</h1>
        <p><a href="index.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_persona ? 'Editar Datos de Persona' : 'Agregar Nueva Persona'; ?></h2>
        <form action="datos_personas.php" method="POST">
            <?php if ($edit_persona): ?>
                <input type="hidden" name="datos_personas_id" value="<?php echo $edit_persona['datos_personas_id']; ?>">
            <?php endif; ?>

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $edit_persona ? htmlspecialchars($edit_persona['nombre']) : ''; ?>" required>

            <label for="apellido_paterno">Apellido Paterno:</label>
            <input type="text" id="apellido_paterno" name="apellido_paterno" value="<?php echo $edit_persona ? htmlspecialchars($edit_persona['apellido_paterno']) : ''; ?>" required>

            <label for="apellido_materno">Apellido Materno:</label>
            <input type="text" id="apellido_materno" name="apellido_materno" value="<?php echo $edit_persona ? htmlspecialchars($edit_persona['apellido_materno']) : ''; ?>">

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo $edit_persona ? htmlspecialchars($edit_persona['email']) : ''; ?>" required>

            <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo $edit_persona ? htmlspecialchars($edit_persona['fecha_nacimiento']) : ''; ?>">

            <label for="telefono">Teléfono:</label>
            <input type="text" id="telefono" name="telefono" value="<?php echo $edit_persona ? htmlspecialchars($edit_persona['telefono']) : ''; ?>">

            <button type="submit" name="<?php echo $edit_persona ? 'update_persona' : 'add_persona'; ?>">
                <?php echo $edit_persona ? 'Actualizar Persona' : 'Agregar Persona'; ?>
            </button>
        </form>

        <h2>Listado de Datos de Personas</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Fecha Nac.</th>
                        <th>Teléfono</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['datos_personas_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_nacimiento']); ?></td>
                            <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                            <td>
                                <a href="datos_personas.php?edit_persona=<?php echo $row['datos_personas_id']; ?>">Editar</a> |
                                <a href="datos_personas.php?delete_persona=<?php echo $row['datos_personas_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar estos datos de persona? Si esta persona es un cliente, no podrás eliminarla desde aquí.');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay datos de personas registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>