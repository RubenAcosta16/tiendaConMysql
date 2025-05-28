<?php
// direcciones.php
require_once 'db.php';
require_once 'protected_route.php';

$conn = connectDB();

$message = '';

// --- Lógica para C, U, D ---

// Crear/Actualizar Dirección
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_direccion'])) {
        $calle = $_POST['calle'];
        $numero_exterior = $_POST['numero_exterior'];
        $numero_interior = $_POST['numero_interior'];
        $colonia = $_POST['colonia'];
        $ciudad = $_POST['ciudad'];
        $estado = $_POST['estado'];
        $codigo_postal = $_POST['codigo_postal'];
        $pais = $_POST['pais'];

        $stmt = $conn->prepare("INSERT INTO direcciones (calle, numero_exterior, numero_interior, colonia, ciudad, estado, codigo_postal, pais) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $calle, $numero_exterior, $numero_interior, $colonia, $ciudad, $estado, $codigo_postal, $pais);
        if ($stmt->execute()) {
            $message = "<p class='success'>Dirección agregada exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al agregar dirección: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } elseif (isset($_POST['update_direccion'])) {
        $direccion_id = $_POST['direccion_id'];
        $calle = $_POST['calle'];
        $numero_exterior = $_POST['numero_exterior'];
        $numero_interior = $_POST['numero_interior'];
        $colonia = $_POST['colonia'];
        $ciudad = $_POST['ciudad'];
        $estado = $_POST['estado'];
        $codigo_postal = $_POST['codigo_postal'];
        $pais = $_POST['pais'];

        $stmt = $conn->prepare("UPDATE direcciones SET calle = ?, numero_exterior = ?, numero_interior = ?, colonia = ?, ciudad = ?, estado = ?, codigo_postal = ?, pais = ? WHERE direccion_id = ?");
        $stmt->bind_param("sssssssii", $calle, $numero_exterior, $numero_interior, $colonia, $ciudad, $estado, $codigo_postal, $pais, $direccion_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Dirección actualizada exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al actualizar dirección: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// Eliminar Dirección
if (isset($_GET['delete_direccion'])) {
    $direccion_id = $_GET['delete_direccion'];

    // Verificar si la dirección está siendo usada por alguna sucursal
    $check_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM sucursales WHERE direccion_id = ?");
    $check_stmt->bind_param("i", $direccion_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $row = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($row['count'] > 0) {
        $message = "<p class='error'>No se puede eliminar la dirección porque está asociada a una o más sucursales.</p>";
    } else {
        $stmt = $conn->prepare("DELETE FROM direcciones WHERE direccion_id = ?");
        $stmt->bind_param("i", $direccion_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Dirección eliminada exitosamente.</p>";
        } else {
            $message = "<p class='error'>Error al eliminar dirección: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// --- Obtener datos para el formulario de edición ---
$edit_direccion = null;
if (isset($_GET['edit_direccion'])) {
    $direccion_id = $_GET['edit_direccion'];
    $stmt = $conn->prepare("SELECT * FROM direcciones WHERE direccion_id = ?");
    $stmt->bind_param("i", $direccion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_direccion = $result->fetch_assoc();
    $stmt->close();
}

// --- Leer Direcciones ---
$sql = "SELECT * FROM direcciones ORDER BY direccion_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Direcciones</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Administrar Direcciones</h1>
        <p><a href="index.php">Volver al Inicio</a></p>

        <?php echo $message; ?>

        <h2><?php echo $edit_direccion ? 'Editar Dirección' : 'Agregar Nueva Dirección'; ?></h2>
        <form action="direcciones.php" method="POST">
            <?php if ($edit_direccion): ?>
                <input type="hidden" name="direccion_id" value="<?php echo $edit_direccion['direccion_id']; ?>">
            <?php endif; ?>

            <label for="calle">Calle:</label>
            <input type="text" id="calle" name="calle" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['calle']) : ''; ?>" required>

            <label for="numero_exterior">Número Exterior:</label>
            <input type="text" id="numero_exterior" name="numero_exterior" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['numero_exterior']) : ''; ?>">

            <label for="numero_interior">Número Interior:</label>
            <input type="text" id="numero_interior" name="numero_interior" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['numero_interior']) : ''; ?>">

            <label for="colonia">Colonia:</label>
            <input type="text" id="colonia" name="colonia" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['colonia']) : ''; ?>">

            <label for="ciudad">Ciudad:</label>
            <input type="text" id="ciudad" name="ciudad" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['ciudad']) : ''; ?>" required>

            <label for="estado">Estado:</label>
            <input type="text" id="estado" name="estado" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['estado']) : ''; ?>" required>

            <label for="codigo_postal">Código Postal:</label>
            <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['codigo_postal']) : ''; ?>" required>

            <label for="pais">País:</label>
            <input type="text" id="pais" name="pais" value="<?php echo $edit_direccion ? htmlspecialchars($edit_direccion['pais']) : 'México'; ?>">

            <button type="submit" name="<?php echo $edit_direccion ? 'update_direccion' : 'add_direccion'; ?>">
                <?php echo $edit_direccion ? 'Actualizar Dirección' : 'Agregar Dirección'; ?>
            </button>
        </form>

        <h2>Listado de Direcciones</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Calle</th>
                        <th>Núm. Ext.</th>
                        <th>Núm. Int.</th>
                        <th>Colonia</th>
                        <th>Ciudad</th>
                        <th>Estado</th>
                        <th>C.P.</th>
                        <th>País</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['direccion_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['calle']); ?></td>
                            <td><?php echo htmlspecialchars($row['numero_exterior']); ?></td>
                            <td><?php echo htmlspecialchars($row['numero_interior']); ?></td>
                            <td><?php echo htmlspecialchars($row['colonia']); ?></td>
                            <td><?php echo htmlspecialchars($row['ciudad']); ?></td>
                            <td><?php echo htmlspecialchars($row['estado']); ?></td>
                            <td><?php echo htmlspecialchars($row['codigo_postal']); ?></td>
                            <td><?php echo htmlspecialchars($row['pais']); ?></td>
                            <td>
                                <a href="direcciones.php?edit_direccion=<?php echo $row['direccion_id']; ?>">Editar</a> |
                                <a href="direcciones.php?delete_direccion=<?php echo $row['direccion_id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta dirección? ¡Solo se podrá eliminar si no está asociada a ninguna sucursal!');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay direcciones registradas.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>