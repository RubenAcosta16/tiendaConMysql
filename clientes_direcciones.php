<?php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';
$cliente_id = null;

if (isset($_GET['cliente_id'])) {
    $cliente_id = (int)$_GET['cliente_id'];

    $stmt_cliente_info = $conn->prepare("
        SELECT c.cliente_id, dp.nombre, dp.apellido_paterno, dp.apellido_materno
        FROM clientes c
        JOIN datos_personas dp ON c.datos_personas_id = dp.datos_personas_id
        WHERE c.cliente_id = ?
    ");
    $stmt_cliente_info->bind_param("i", $cliente_id);
    $stmt_cliente_info->execute();
    $cliente_info_result = $stmt_cliente_info->get_result();
    $cliente_info = $cliente_info_result->fetch_assoc();
    $stmt_cliente_info->close();

    if (!$cliente_info) {
        $message = "<p class='error'>Cliente no encontrado.</p>";
        $cliente_id = null; 
    }
} else {
    $message = "<p class='error'>ID de cliente no proporcionado.</p>";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cliente_direccion'])) {
    $cliente_id_form = $_POST['cliente_id'];
    $direccion_id = $_POST['direccion_id'];
    $es_principal = isset($_POST['es_principal']) ? 1 : 0;

    if ($es_principal) {
        $stmt_unset_principal = $conn->prepare("UPDATE clientes_direcciones SET es_principal = 0 WHERE cliente_id = ? AND es_principal = 1");
        $stmt_unset_principal->bind_param("i", $cliente_id_form);
        $stmt_unset_principal->execute();
        $stmt_unset_principal->close();
    }

    $stmt = $conn->prepare("INSERT INTO clientes_direcciones (cliente_id, direccion_id, es_principal) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE es_principal = ?");
    $stmt->bind_param("iiii", $cliente_id_form, $direccion_id, $es_principal, $es_principal);
    if ($stmt->execute()) {
        $message = "<p class='success'>Dirección asociada/actualizada exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al asociar dirección: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

if (isset($_GET['delete_cliente_direccion'])) {
    $direccion_id_to_delete = $_GET['delete_cliente_direccion'];
    $cliente_id_for_delete = $_GET['cliente_id']; 

    $stmt = $conn->prepare("DELETE FROM clientes_direcciones WHERE cliente_id = ? AND direccion_id = ?");
    $stmt->bind_param("ii", $cliente_id_for_delete, $direccion_id_to_delete);
    if ($stmt->execute()) {
        $message = "<p class='success'>Dirección desvinculada exitosamente.</p>";
    } else {
        $message = "<p class='error'>Error al desvincular dirección: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

if (isset($_GET['set_principal'])) {
    $direccion_id_to_set_principal = $_GET['set_principal'];
    $cliente_id_for_set_principal = $_GET['cliente_id'];

    $stmt_unset = $conn->prepare("UPDATE clientes_direcciones SET es_principal = 0 WHERE cliente_id = ?");
    $stmt_unset->bind_param("i", $cliente_id_for_set_principal);
    $stmt_unset->execute();
    $stmt_unset->close();

    $stmt_set = $conn->prepare("UPDATE clientes_direcciones SET es_principal = 1 WHERE cliente_id = ? AND direccion_id = ?");
    $stmt_set->bind_param("ii", $cliente_id_for_set_principal, $direccion_id_to_set_principal);
    if ($stmt_set->execute()) {
        $message = "<p class='success'>Dirección marcada como principal.</p>";
    } else {
        $message = "<p class='error'>Error al marcar como principal: " . $stmt_set->error . "</p>";
    }
    $stmt_set->close();
}


$direcciones_disponibles = [];
if ($cliente_id) {
    $sql_available_dirs = "SELECT d.direccion_id, d.calle, d.numero_exterior, d.colonia
                           FROM direcciones d
                           WHERE d.direccion_id NOT IN (SELECT cd.direccion_id FROM clientes_direcciones cd WHERE cd.cliente_id = ?)";
    $stmt_available_dirs = $conn->prepare($sql_available_dirs);
    $stmt_available_dirs->bind_param("i", $cliente_id);
    $stmt_available_dirs->execute();
    $result_available_dirs = $stmt_available_dirs->get_result();
    while ($row = $result_available_dirs->fetch_assoc()) {
        $direcciones_disponibles[] = $row;
    }
    $stmt_available_dirs->close();
}


$direcciones_asociadas = [];
if ($cliente_id) {
    $sql_associated_dirs = "SELECT
                                d.direccion_id,
                                d.calle,
                                d.numero_exterior,
                                d.numero_interior,
                                d.colonia,
                                d.ciudad,
                                d.estado,
                                d.codigo_postal,
                                d.pais,
                                cd.es_principal
                            FROM clientes_direcciones cd
                            JOIN direcciones d ON cd.direccion_id = d.direccion_id
                            WHERE cd.cliente_id = ?
                            ORDER BY cd.es_principal DESC, d.calle";
    $stmt_associated_dirs = $conn->prepare($sql_associated_dirs);
    $stmt_associated_dirs->bind_param("i", $cliente_id);
    $stmt_associated_dirs->execute();
    $result_associated_dirs = $stmt_associated_dirs->get_result();
    while ($row = $result_associated_dirs->fetch_assoc()) {
        $direcciones_asociadas[] = $row;
    }
    $stmt_associated_dirs->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Direcciones del Cliente</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Direcciones del Cliente</h1>
        <p><a href="clientes.php">Volver a Clientes</a></p>

        <?php echo $message; ?>

        <?php if ($cliente_info): ?>
            <h2>Cliente: <?php echo htmlspecialchars($cliente_info['nombre'] . ' ' . $cliente_info['apellido_paterno'] . ' ' . $cliente_info['apellido_materno']); ?> (ID: <?php echo $cliente_id; ?>)</h2>

            <h3>Asociar Nueva Dirección</h3>
            <?php if (!empty($direcciones_disponibles)): ?>
                <form action="clientes_direcciones.php?cliente_id=<?php echo $cliente_id; ?>" method="POST">
                    <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">
                    <label for="direccion_id">Seleccionar Dirección:</label>
                    <select id="direccion_id" name="direccion_id" required>
                        <option value="">-- Seleccione una dirección --</option>
                        <?php foreach ($direcciones_disponibles as $dir): ?>
                            <option value="<?php echo $dir['direccion_id']; ?>">
                                <?php echo htmlspecialchars($dir['calle'] . ' ' . $dir['numero_exterior'] . ', ' . $dir['colonia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>
                        <input type="checkbox" name="es_principal" value="1"> Marcar como principal
                    </label>
                    <button type="submit" name="add_cliente_direccion">Asociar Dirección</button>
                </form>
            <?php else: ?>
                <p>No hay direcciones disponibles para asociar o ya están todas asociadas a este cliente.</p>
                <p><a href="direcciones.php">Crear una nueva dirección</a></p>
            <?php endif; ?>

            <h3>Direcciones Asociadas</h3>
            <?php if (!empty($direcciones_asociadas)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dirección Completa</th>
                            <th>Principal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($direcciones_asociadas as $dir_assoc): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($dir_assoc['calle'] . ' ' . $dir_assoc['numero_exterior'] .
                                        ($dir_assoc['numero_interior'] ? ' Int. ' . $dir_assoc['numero_interior'] : '') .
                                        ', ' . $dir_assoc['colonia'] . ', ' . $dir_assoc['ciudad'] . ', ' . $dir_assoc['estado'] .
                                        ' C.P. ' . $dir_assoc['codigo_postal'] . ', ' . $dir_assoc['pais']); ?>
                                </td>
                                <td><?php echo $dir_assoc['es_principal'] ? 'Sí' : 'No'; ?></td>
                                <td>
                                    <?php if (!$dir_assoc['es_principal']): ?>
                                        <a href="clientes_direcciones.php?cliente_id=<?php echo $cliente_id; ?>&set_principal=<?php echo $dir_assoc['direccion_id']; ?>">Marcar Principal</a> |
                                    <?php endif; ?>
                                    <a href="clientes_direcciones.php?cliente_id=<?php echo $cliente_id; ?>&delete_cliente_direccion=<?php echo $dir_assoc['direccion_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres desvincular esta dirección del cliente?');">Desvincular</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Este cliente no tiene direcciones asociadas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>