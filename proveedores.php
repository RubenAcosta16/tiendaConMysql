<?php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_proveedor'])) {
        $nombre_empresa = $_POST['nombre_empresa'];
        $rfc = $_POST['rfc'];
        $nombre_contacto = $_POST['nombre_contacto'];
        $email_contacto = $_POST['email_contacto'];
        $telefono_contacto = $_POST['telefono_contacto'];
        $direccion_id = $_POST['direccion_id'];

        $stmt = $conn->prepare("INSERT INTO proveedores (nombre_empresa, rfc, nombre_contacto, email_contacto, telefono_contacto, direccion_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $nombre_empresa, $rfc, $nombre_contacto, $email_contacto, $telefono_contacto, $direccion_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Proveedor agregado exitosamente.</p>";
            header("Location: proveedores.php");
            exit();
        } else {
            if ($conn->errno == 1062) { 
                $message = "<p class='error'>Error al agregar proveedor: El RFC ya existe. Por favor, ingrese uno diferente.</p>";
            } else {
                $message = "<p class='error'>Error al agregar proveedor: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } elseif (isset($_POST['update_proveedor'])) {
        $proveedor_id = $_POST['proveedor_id'];
        $nombre_empresa = $_POST['nombre_empresa'];
        $rfc = $_POST['rfc'];
        $nombre_contacto = $_POST['nombre_contacto'];
        $email_contacto = $_POST['email_contacto'];
        $telefono_contacto = $_POST['telefono_contacto'];
        $direccion_id = $_POST['direccion_id'];

        $stmt = $conn->prepare("UPDATE proveedores SET nombre_empresa = ?, rfc = ?, nombre_contacto = ?, email_contacto = ?, telefono_contacto = ?, direccion_id = ? WHERE proveedor_id = ?");
        $stmt->bind_param("sssssii", $nombre_empresa, $rfc, $nombre_contacto, $email_contacto, $telefono_contacto, $direccion_id, $proveedor_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Proveedor actualizado exitosamente.</p>";
        } else {
            if ($conn->errno == 1062) { 
                $message = "<p class='error'>Error al actualizar proveedor: El RFC ya existe. Por favor, ingrese uno diferente.</p>";
            } else {
                $message = "<p class='error'>Error al actualizar proveedor: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_proveedor'])) {
    $proveedor_id = $_GET['delete_proveedor'];
    $stmt = $conn->prepare("DELETE FROM proveedores WHERE proveedor_id = ?");
    $stmt->bind_param("i", $proveedor_id);
    if ($stmt->execute()) {
        $message = "<p class='success'>Proveedor eliminado exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al eliminar proveedor: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

$edit_proveedor = null;
if (isset($_GET['edit_proveedor'])) {
    $proveedor_id = $_GET['edit_proveedor'];
    $stmt = $conn->prepare("SELECT * FROM proveedores WHERE proveedor_id = ?");
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_proveedor = $result->fetch_assoc();
    $stmt->close();
}

$direcciones_result = $conn->query("SELECT direccion_id, calle, numero_exterior, colonia FROM direcciones");
$direcciones = [];
if ($direcciones_result->num_rows > 0) {
    while ($row = $direcciones_result->fetch_assoc()) {
        $direcciones[] = $row;
    }
}

$sql = "SELECT p.proveedor_id, p.nombre_empresa, p.rfc, p.nombre_contacto, p.email_contacto, p.telefono_contacto,
               p.direccion_id,  
               d.calle, d.numero_exterior, d.numero_interior, d.colonia, d.ciudad, d.estado, d.codigo_postal, d.pais
        FROM proveedores p
        LEFT JOIN direcciones d ON p.direccion_id = d.direccion_id
        ORDER BY p.proveedor_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Proveedores</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Proveedores</h1>
        <p><a href="dashboard_admin.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_proveedor ? 'Editar Proveedor' : 'Agregar Nuevo Proveedor'; ?></h2>
        <form action="proveedores.php" method="POST">
            <?php if ($edit_proveedor): ?>
                <input type="hidden" name="proveedor_id" value="<?php echo $edit_proveedor['proveedor_id']; ?>">
            <?php endif; ?>

            <label for="nombre_empresa">Nombre de la Empresa:</label>
            <input type="text" id="nombre_empresa" name="nombre_empresa" value="<?php echo $edit_proveedor ? htmlspecialchars($edit_proveedor['nombre_empresa']) : ''; ?>" required>

            <label for="rfc">RFC:</label>
            <input type="text" id="rfc" name="rfc" value="<?php echo $edit_proveedor ? htmlspecialchars($edit_proveedor['rfc']) : ''; ?>" maxlength="13" required>

            <label for="nombre_contacto">Nombre de Contacto:</label>
            <input type="text" id="nombre_contacto" name="nombre_contacto" value="<?php echo $edit_proveedor ? htmlspecialchars($edit_proveedor['nombre_contacto']) : ''; ?>">

            <label for="email_contacto">Email de Contacto:</label>
            <input type="text" id="email_contacto" name="email_contacto" value="<?php echo $edit_proveedor ? htmlspecialchars($edit_proveedor['email_contacto']) : ''; ?>">

            <label for="telefono_contacto">Teléfono de Contacto:</label>
            <input type="text" id="telefono_contacto" name="telefono_contacto" value="<?php echo $edit_proveedor ? htmlspecialchars($edit_proveedor['telefono_contacto']) : ''; ?>">

            <label for="direccion_id">Dirección:</label>
            <select id="direccion_id" name="direccion_id">
                <option value="">Ninguna Dirección (Opcional)</option>
                <?php foreach ($direcciones as $direccion): ?>
                    <option value="<?php echo $direccion['direccion_id']; ?>"
                        <?php echo ($edit_proveedor && $edit_proveedor['direccion_id'] == $direccion['direccion_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($direccion['calle'] . ' ' . $direccion['numero_exterior'] . ', ' . $direccion['colonia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="<?php echo $edit_proveedor ? 'update_proveedor' : 'add_proveedor'; ?>">
                <?php echo $edit_proveedor ? 'Actualizar Proveedor' : 'Agregar Proveedor'; ?>
            </button>
        </form>

        <h2>Listado de Proveedores</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>RFC</th>
                        <th>Contacto</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['proveedor_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_empresa']); ?></td>
                            <td><?php echo htmlspecialchars($row['rfc']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_contacto']); ?></td>
                            <td><?php echo htmlspecialchars($row['email_contacto']); ?></td>
                            <td><?php echo htmlspecialchars($row['telefono_contacto']); ?></td>
                            <td>
                                <?php
                                if ($row['direccion_id']) { 
                                    echo htmlspecialchars($row['calle'] . ' ' . $row['numero_exterior'] .
                                        ($row['numero_interior'] ? ' Int. ' . $row['numero_interior'] : '') .
                                        ', ' . $row['colonia'] . ', ' . $row['ciudad'] . ', ' . $row['estado'] .
                                        ' C.P. ' . $row['codigo_postal'] . ', ' . $row['pais']);
                                } else {
                                    echo "N/A"; 
                                }
                                ?>
                            </td>
                            <td>
                                <a href="proveedores.php?edit_proveedor=<?php echo $row['proveedor_id']; ?>">Editar</a> |
                                <a href="proveedores.php?delete_proveedor=<?php echo $row['proveedor_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este proveedor?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay proveedores registrados.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>