<?php
require_once 'protected_route.php'; // ¡PROTEGER ESTA PÁGINA!
require_once 'db.php';

$conn = connectDB();

$message = '';
$productos_disponibles = [];
$cliente_id = $_SESSION['user_id'];

// Obtener productos disponibles
$productos_result = $conn->query("SELECT producto_id, nombre, descripcion, precio, stock FROM productos WHERE stock > 0 ORDER BY nombre");
if ($productos_result->num_rows > 0) {
    while ($row = $productos_result->fetch_assoc()) {
        $productos_disponibles[] = $row;
    }
}

// Procesar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['realizar_compra'])) {
    $productos_seleccionados_json_array = [];
    $has_products = false;

    if (isset($_POST['productos_id']) && is_array($_POST['productos_id'])) {
        foreach ($_POST['productos_id'] as $index => $producto_id) {
            $cantidad = isset($_POST['cantidades'][$index]) ? (int)$_POST['cantidades'][$index] : 0;
            if ($producto_id > 0 && $cantidad > 0) {
                $productos_seleccionados_json_array[] = ['producto_id' => $producto_id, 'cantidad' => $cantidad];
                $has_products = true;
            }
        }
    }

    if (!$has_products) {
        $message = "<p class='error'>Debe seleccionar al menos un producto para realizar la compra.</p>";
    } else {
        $productos_json = json_encode($productos_seleccionados_json_array);

        // Para un cliente, empleado y sucursal pueden ser NULL, la dirección de envío sería la principal del cliente o se pediría.
        // Para simplificar, asumimos NULL para empleado/sucursal y NULL para dirección de envío (se podría pedir al cliente que seleccione una)
        $empleado_id = NULL;
        $sucursal_id = NULL;
        $direccion_envio_id = NULL; // Esto debería ser seleccionado por el cliente o su dirección principal

        // Aquí podrías obtener la dirección principal del cliente si la tuvieras asociada
        // $stmt_dir = $conn->prepare("SELECT direccion_id FROM direcciones WHERE datos_personas_id = (SELECT datos_personas_id FROM clientes WHERE cliente_id = ?) LIMIT 1");
        // $stmt_dir->bind_param("i", $cliente_id);
        // $stmt_dir->execute();
        // $res_dir = $stmt_dir->get_result();
        // if ($row_dir = $res_dir->fetch_assoc()) {
        //     $direccion_envio_id = $row_dir['direccion_id'];
        // }
        // $stmt_dir->close();

        try {
            // Asumiendo que sp_registrar_nueva_orden espera cliente_id, empleado_id, sucursal_id, direccion_envio_id, productos_json
            // y que los NULLs son aceptables si no se usan
            $stmt = $conn->prepare("CALL sp_registrar_nueva_orden(?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $cliente_id, $empleado_id, $sucursal_id, $direccion_envio_id, $productos_json);
            $stmt->execute();
            $message = "<p class='success'>¡Compra realizada con éxito! Su orden ha sido registrada.</p>";
            // Limpiar los resultados del procedimiento almacenado para futuras consultas
            while($conn->more_results() && $conn->next_result()){}
            // Recargar productos para reflejar el stock actualizado
            $productos_disponibles = [];
            $productos_result = $conn->query("SELECT producto_id, nombre, descripcion, precio, stock FROM productos WHERE stock > 0 ORDER BY nombre");
            if ($productos_result->num_rows > 0) {
                while ($row = $productos_result->fetch_assoc()) {
                    $productos_disponibles[] = $row;
                }
            }

        } catch (mysqli_sql_exception $e) {
            $message = "<p class='error'>Error al realizar la compra: " . $e->getMessage() . "</p>";
        } finally {
            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                 $stmt->close();
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Cliente - Realizar Compra</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        function addProductToCartRow() {
            const container = document.getElementById('products-cart-container');
            const newRow = document.createElement('div');
            newRow.classList.add('product-input-row');
            newRow.innerHTML = `
                <select name="productos_id[]" required onchange="updateProductPriceAndStock(this)">
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($productos_disponibles as $prod): ?>
                        <option value="<?php echo $prod['producto_id']; ?>" data-price="<?php echo $prod['precio']; ?>" data-stock="<?php echo $prod['stock']; ?>">
                            <?php echo htmlspecialchars($prod['nombre']) . ' ($' . number_format($prod['precio'], 2) . ') [Stock: ' . $prod['stock'] . ']'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="cantidades[]" placeholder="Cantidad" min="1" value="1" required oninput="checkProductStock(this)">
                <span class="product-price">Precio: $0.00</span>
                <span class="product-stock-info">Stock: --</span>
                <button type="button" onclick="removeProductToCartRow(this)">Eliminar</button>
            `;
            container.appendChild(newRow);
        }

        function removeProductToCartRow(button) {
            button.parentNode.remove();
        }

        function updateProductPriceAndStock(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const priceSpan = selectElement.nextElementSibling.nextElementSibling;
            const stockSpan = selectElement.nextElementSibling.nextElementSibling.nextElementSibling;

            if (selectedOption.value) {
                const price = parseFloat(selectedOption.getAttribute('data-price'));
                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                priceSpan.textContent = `Precio: $${price.toFixed(2)}`;
                stockSpan.textContent = `Stock: ${stock}`;
                // Set max for quantity input
                const quantityInput = selectElement.nextElementSibling;
                quantityInput.max = stock;
                if (parseInt(quantityInput.value) > stock) {
                    quantityInput.value = stock; // Adjust if current value exceeds new stock
                }
            } else {
                priceSpan.textContent = 'Precio: $0.00';
                stockSpan.textContent = 'Stock: --';
            }
        }

        function checkProductStock(inputElement) {
            const quantity = parseInt(inputElement.value);
            const selectElement = inputElement.previousElementSibling;
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const stock = parseInt(selectedOption.getAttribute('data-stock'));

            if (quantity > stock) {
                alert('La cantidad excede el stock disponible (' + stock + ').');
                inputElement.value = stock; // Ajusta la cantidad al stock máximo
            }
        }

        // Initialize prices and stocks for any existing rows (shouldn't be any on first load, but good practice)
        document.addEventListener('DOMContentLoaded', function() {
            const productSelects = document.querySelectorAll('#products-cart-container select[name="productos_id[]"]');
            productSelects.forEach(select => {
                updateProductPriceAndStock(select);
            });
        });
    </script>
    <style>
        /* Reutilizamos los estilos de product-input-row de ordenes_compra.php */
        .product-input-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px; /* Espacio entre elementos */
        }

        .product-input-row select,
        .product-input-row input[type="number"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .product-input-row select {
            flex-grow: 1; /* Permite que el select ocupe el espacio disponible */
            min-width: 200px;
        }

        .product-input-row input[type="number"] {
            width: 80px; /* Ancho fijo para la cantidad */
            text-align: center;
        }

        .product-input-row button {
            background-color: #dc3545; /* Rojo para eliminar */
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .product-input-row button:hover {
            background-color: #c82333;
        }

        .product-price, .product-stock-info {
            white-space: nowrap; /* Evita que el texto se rompa en varias líneas */
            min-width: 90px; /* Ancho mínimo para mantener el formato */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Cliente)</h1>
        <p>¡Aquí puedes explorar nuestros productos y realizar tus compras!</p>
        <button onclick="logout()">Cerrar Sesión</button>
        <hr>

        <?php echo $message; ?>

        <h2>Realizar Nueva Compra</h2>
        <form action="dashboard_cliente.php" method="POST">
            <h3>Productos a Comprar</h3>
            <div id="products-cart-container">
                <div class="product-input-row">
                    <select name="productos_id[]" required onchange="updateProductPriceAndStock(this)">
                        <option value="">Selecciona un producto</option>
                        <?php foreach ($productos_disponibles as $prod): ?>
                            <option value="<?php echo $prod['producto_id']; ?>" data-price="<?php echo $prod['precio']; ?>" data-stock="<?php echo $prod['stock']; ?>">
                                <?php echo htmlspecialchars($prod['nombre']) . ' ($' . number_format($prod['precio'], 2) . ') [Stock: ' . $prod['stock'] . ']'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cantidades[]" placeholder="Cantidad" min="1" value="1" required oninput="checkProductStock(this)">
                    <span class="product-price">Precio: $0.00</span>
                    <span class="product-stock-info">Stock: --</span>
                    <button type="button" onclick="removeProductToCartRow(this)">Eliminar</button>
                </div>
            </div>
            <button type="button" onclick="addProductToCartRow()">Agregar Otro Producto al Carrito</button>
            <hr>
            <button type="submit" name="realizar_compra">Finalizar Compra</button>
        </form>

        <h2>Mis Órdenes Recientes</h2>
        <p>Implementar aquí la visualización de las órdenes de compra del cliente.</p>
        <a href="ordenes_compra.php">Ver todas las órdenes (requiere acceso de administrador para ver todas)</a>

    </div>
    <script src="js/auth.js"></script>
</body>
</html>