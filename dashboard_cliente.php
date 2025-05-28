<?php
require_once 'protected_route.php'; 
require_once 'db.php';

$conn = connectDB();

$message = '';
$productos_disponibles = [];
$cliente_id = $_SESSION['user_id'];

$productos_result = $conn->query("SELECT producto_id, nombre, descripcion, precio, stock FROM productos WHERE stock > 0 ORDER BY nombre");
if ($productos_result->num_rows > 0) {
    while ($row = $productos_result->fetch_assoc()) {
        $productos_disponibles[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['realizar_compra'])) {
    $productos_seleccionados_json_array = [];
    $has_products = false;
    $total_compra = 0;
    $cantidad_pagada = isset($_POST['cantidad_pago']) ? (float)$_POST['cantidad_pago'] : 0;

    if (isset($_POST['productos_id']) && is_array($_POST['productos_id'])) {
        foreach ($_POST['productos_id'] as $index => $producto_id) {
            $cantidad = isset($_POST['cantidades'][$index]) ? (int)$_POST['cantidades'][$index] : 0;
            if ($producto_id > 0 && $cantidad > 0) {
                $stmt_prod = $conn->prepare("SELECT precio, stock FROM productos WHERE producto_id = ?");
                $stmt_prod->bind_param("i", $producto_id);
                $stmt_prod->execute();
                $res_prod = $stmt_prod->get_result();
                if ($row_prod = $res_prod->fetch_assoc()) {
                    if ($cantidad <= $row_prod['stock']) {
                        $productos_seleccionados_json_array[] = ['producto_id' => $producto_id, 'cantidad' => $cantidad];
                        $total_compra += $row_prod['precio'] * $cantidad;
                        $has_products = true;
                    } else {
                        $message = "<p class='error'>La cantidad solicitada para un producto excede el stock. No se procesó la compra.</p>";
                        $has_products = false; 
                        break;
                    }
                }
                $stmt_prod->close();
            }
        }
    }

    if (!$has_products && empty($message)) {
        $message = "<p class='error'>Debe seleccionar al menos un producto para realizar la compra.</p>";
    } elseif ($has_products && $cantidad_pagada < $total_compra) {
        $message = "<p class='error'>La cantidad pagada ($" . number_format($cantidad_pagada, 2) . ") es menor que el total de la compra ($" . number_format($total_compra, 2) . "). No se puede procesar.</p>";
    } elseif ($has_products) {
        $productos_json = json_encode($productos_seleccionados_json_array);

        $empleado_id = NULL;
        $sucursal_id = NULL;
        $direccion_envio_id = NULL;

        try {
            $stmt = $conn->prepare("CALL sp_registrar_nueva_orden(?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $cliente_id, $empleado_id, $sucursal_id, $direccion_envio_id, $productos_json);
            $stmt->execute();
            $cambio = $cantidad_pagada - $total_compra;
            $message = "<p class='success'>¡Compra realizada con éxito! Su orden ha sido registrada. Total: $" . number_format($total_compra, 2) . ". Pagado: $" . number_format($cantidad_pagada, 2) . ". Cambio: $" . number_format($cambio, 2) . ".</p>";

            while ($conn->more_results() && $conn->next_result());

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
    <style>
        .product-input-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px; 
            flex-wrap: wrap; 
        }

        .product-input-row select,
        .product-input-row input[type="number"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .product-input-row select {
            flex-grow: 1; 
            min-width: 200px;
        }

        .product-input-row input[type="number"] {
            width: 80px; 
            text-align: center;
        }

        .product-input-row button {
            background-color: #dc3545; 
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

        .product-price, .product-stock-info, .product-subtotal {
            white-space: nowrap;
            min-width: 120px; 
            font-size: 0.9em;
            padding: 8px;
            background-color: #f0f0f0;
            border-radius: 4px;
        }

        #total-container {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 5px;
            font-size: 1.2em;
            font-weight: bold;
        }
        #total-container div {
            margin-bottom: 10px;
        }
         #total-container input[type="number"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            width: 120px;
         }
         #payment-error {
            color: red;
            font-weight: bold;
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
        <form id="purchase-form" action="dashboard_cliente.php" method="POST" onsubmit="return validatePurchase()">
            <h3>Productos a Comprar</h3>
            <div id="products-cart-container">
                <div class="product-input-row">
                    <select name="productos_id[]" required onchange="updateRowDetails(this)">
                        <option value="" data-price="0" data-stock="0">Selecciona un producto</option>
                        <?php foreach ($productos_disponibles as $prod): ?>
                            <option value="<?php echo $prod['producto_id']; ?>" data-price="<?php echo $prod['precio']; ?>" data-stock="<?php echo $prod['stock']; ?>">
                                <?php echo htmlspecialchars($prod['nombre']) . ' ($' . number_format($prod['precio'], 2) . ') [Stock: ' . $prod['stock'] . ']'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cantidades[]" placeholder="Cantidad" min="1" value="1" required oninput="updateRowDetails(this)">
                    <span class="product-price">Precio: $0.00</span>
                    <span class="product-subtotal">Subtotal: $0.00</span>
                    <span class="product-stock-info">Stock: --</span>
                    <button type="button" onclick="removeProductToCartRow(this)">Eliminar</button>
                </div>
            </div>
            <button type="button" onclick="addProductToCartRow()">Agregar Otro Producto al Carrito</button>
            <hr>

            <div id="total-container">
                 <div>Total a Pagar: <span id="grand-total">$0.00</span></div>
                 <div>
                    <label for="cantidad_pago">Cantidad a Pagar: $</label>
                    <input type="number" id="cantidad_pago" name="cantidad_pago" min="0" step="0.01" required oninput="updateChange()">
                 </div>
                 <div>Cambio: <span id="change-due">$0.00</span></div>
                 <div id="payment-error"></div>
            </div>

            <button type="submit" name="realizar_compra" id="submit-button">Finalizar Compra</button>
        </form>

        <h2>Mis Órdenes Recientes</h2>
        <p>Implementar aquí la visualización de las órdenes de compra del cliente.</p>
        <a href="ordenes_compra.php">Ver todas las órdenes (requiere acceso de administrador para ver todas)</a>

    </div>
    <script src="js/auth.js"></script>
    <script>
        const productosDisponibles = <?php echo json_encode($productos_disponibles); ?>;

        function getProductHtmlOptions() {
            let options = '<option value="" data-price="0" data-stock="0">Selecciona un producto</option>';
            productosDisponibles.forEach(prod => {
                options += `<option value="${prod.producto_id}" data-price="${prod.precio}" data-stock="${prod.stock}">
                                ${prod.nombre} ($${parseFloat(prod.precio).toFixed(2)}) [Stock: ${prod.stock}]
                           </option>`;
            });
            return options;
        }

        function addProductToCartRow() {
            const container = document.getElementById('products-cart-container');
            const newRow = document.createElement('div');
            newRow.classList.add('product-input-row');
            newRow.innerHTML = `
                <select name="productos_id[]" required onchange="updateRowDetails(this)">
                    ${getProductHtmlOptions()}
                </select>
                <input type="number" name="cantidades[]" placeholder="Cantidad" min="1" value="1" required oninput="updateRowDetails(this)">
                <span class="product-price">Precio: $0.00</span>
                <span class="product-subtotal">Subtotal: $0.00</span>
                <span class="product-stock-info">Stock: --</span>
                <button type="button" onclick="removeProductToCartRow(this)">Eliminar</button>
            `;
            container.appendChild(newRow);
            updateGrandTotal(); 
        }

        function removeProductToCartRow(button) {
            const container = document.getElementById('products-cart-container');
            if (container.children.length > 1) {
                button.parentNode.remove();
                updateGrandTotal(); 
            } else {
                alert("Debe haber al menos un producto.");
            }
        }

        function updateRowDetails(element) {
            const row = element.closest('.product-input-row');
            const selectElement = row.querySelector('select');
            const quantityInput = row.querySelector('input[type="number"]');
            const priceSpan = row.querySelector('.product-price');
            const subtotalSpan = row.querySelector('.product-subtotal');
            const stockSpan = row.querySelector('.product-stock-info');

            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            let quantity = parseInt(quantityInput.value) || 0;

            if (quantity > stock && stock > 0) {
                alert('La cantidad excede el stock disponible (' + stock + ').');
                quantityInput.value = stock;
                quantity = stock;
            }
             if (quantity < 1 && selectElement.value !== "") {
                quantityInput.value = 1;
                quantity = 1;
             }
             quantityInput.max = stock;


            const subtotal = price * quantity;

            priceSpan.textContent = `Precio: $${price.toFixed(2)}`;
            stockSpan.textContent = `Stock: ${stock > 0 ? stock : '--'}`;
            subtotalSpan.textContent = `Subtotal: $${subtotal.toFixed(2)}`;
            subtotalSpan.dataset.value = subtotal;

            updateGrandTotal();
        }

        function updateGrandTotal() {
            const subtotalSpans = document.querySelectorAll('.product-subtotal');
            let grandTotal = 0;
            subtotalSpans.forEach(span => {
                grandTotal += parseFloat(span.dataset.value) || 0;
            });

            document.getElementById('grand-total').textContent = `$${grandTotal.toFixed(2)}`;
            document.getElementById('grand-total').dataset.value = grandTotal;

            updateChange(); 
        }

        function updateChange() {
            const grandTotal = parseFloat(document.getElementById('grand-total').dataset.value) || 0;
            const paymentInput = document.getElementById('cantidad_pago');
            const paymentAmount = parseFloat(paymentInput.value) || 0;
            const changeSpan = document.getElementById('change-due');
            const paymentError = document.getElementById('payment-error');
            const submitButton = document.getElementById('submit-button');

            let change = 0;
            let errorMsg = '';
            let canSubmit = true;

            if (grandTotal <= 0) {
                 errorMsg = 'Agregue productos para continuar.';
                 canSubmit = false;
                 changeSpan.textContent = '$0.00';
            } else if (paymentAmount < grandTotal) {
                errorMsg = 'El pago debe ser mayor o igual al total.';
                canSubmit = false;
                changeSpan.textContent = '$0.00';
            } else {
                change = paymentAmount - grandTotal;
                changeSpan.textContent = `$${change.toFixed(2)}`;
                errorMsg = '';
                canSubmit = true;
            }

            paymentError.textContent = errorMsg;
            submitButton.disabled = !canSubmit;
        }

        function validatePurchase() {
            updateGrandTotal(); 
            const grandTotal = parseFloat(document.getElementById('grand-total').dataset.value) || 0;
            const paymentAmount = parseFloat(document.getElementById('cantidad_pago').value) || 0;

            if (grandTotal <= 0) {
                alert("Debe agregar al menos un producto válido.");
                return false; 
            }

            if (paymentAmount < grandTotal) {
                alert("La cantidad pagada es insuficiente.");
                return false; 
            }

             const rows = document.querySelectorAll('.product-input-row');
             for (const row of rows) {
                const selectElement = row.querySelector('select');
                const quantityInput = row.querySelector('input[type="number"]');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                const quantity = parseInt(quantityInput.value) || 0;

                if (selectElement.value && quantity > stock) {
                    alert(`El stock para ${selectedOption.text.split('(')[0].trim()} ha cambiado y no es suficiente. Por favor, revise su pedido.`);
                     updateRowDetails(quantityInput); 
                    return false;
                }
             }


            return true; 
        }

        document.addEventListener('DOMContentLoaded', function() {
            const productSelects = document.querySelectorAll('#products-cart-container select[name="productos_id[]"]');
            productSelects.forEach(select => {
                updateRowDetails(select); 
            });
             updateGrandTotal(); 
        });
    </script>
</body>
</html>