<?php
session_start();
require_once("../config/conexion.php");

// ---------------------
// 1. AGREGAR PRODUCTO
// ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_producto'])) {
    $id_producto = intval($_POST['id_producto']);
    $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;
    $id_variacion = isset($_POST['id_variacion']) ? intval($_POST['id_variacion']) : null;

    if ($id_producto > 0 && $cantidad > 0) {
        $sql = "SELECT id_producto, nombre_producto, precio, imagen_principal 
                FROM productos 
                WHERE id_producto = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($producto) {
            $item_id = $id_producto . ($id_variacion ? '-' . $id_variacion : '');
            
            if (isset($_SESSION['carrito'][$item_id])) {
                $_SESSION['carrito'][$item_id]['cantidad'] += $cantidad;
            } else {
                $_SESSION['carrito'][$item_id] = [
                    'id_producto' => $producto['id_producto'],
                    'nombre_producto' => $producto['nombre_producto'],
                    'precio' => $producto['precio'],
                    'cantidad' => $cantidad,
                    'imagen' => $producto['imagen_principal'],
                    'id_variacion' => $id_variacion
                ];
            }
        }
    }
}

// ---------------------
// 2. ELIMINAR PRODUCTO
// ---------------------
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    if (isset($_SESSION['carrito'][$id])) {
        unset($_SESSION['carrito'][$id]);
    }
}

// ---------------------
// 3. MOSTRAR CARRITO
// ---------------------
$total = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito de compras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container my-5">
    <h2 class="mb-4">🛒 Carrito de compras</h2>

    <?php if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])): ?>
        <div class="alert alert-info">Tu carrito está vacío.</div>
        <a href="index_home.php" class="btn btn-primary">Seguir comprando</a>
    <?php else: ?>
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($_SESSION['carrito'] as $id => $item): 
                $subtotal = $item['precio'] * $item['cantidad'];
                $total += $subtotal;
            ?>
                <tr>
                    <td><img src="../<?php echo htmlspecialchars($item['imagen']); ?>" width="80"></td>
                    <td><?php echo htmlspecialchars($item['nombre_producto']); ?></td>
                    <td>S/ <?php echo number_format($item['precio'], 2); ?></td>
                    <td><?php echo $item['cantidad']; ?></td>
                    <td>S/ <?php echo number_format($subtotal, 2); ?></td>
                    <td><a href="carrito.php?eliminar=<?php echo urlencode($id); ?>" class="btn btn-danger btn-sm">Eliminar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h4 class="text-end">Total: <span class="text-danger fw-bold">S/ <?php echo number_format($total, 2); ?></span></h4>

        <div class="d-flex justify-content-between mt-4">
            <a href="index_home.php" class="btn btn-outline-primary">Seguir comprando</a>
            <a href="checkout.php" class="btn btn-success">Finalizar compra</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
