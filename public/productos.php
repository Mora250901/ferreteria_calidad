<?php
session_start();
include("../config/conexion.php");

// Verificar si viene una categoría en la URL
$categoria_id = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;

// Consulta de productos
if ($categoria_id > 0) {
    $sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion, p.sku, p.precio, p.stock, p.imagen_principal, c.nombre_categoria 
            FROM productos p
            INNER JOIN categorias c ON p.id_categoria = c.id_categoria
            WHERE p.id_categoria = ? AND p.activo = 1
            ORDER BY p.fecha_creacion DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $categoria_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Mostrar todos los productos si no hay categoría
    $sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion, p.precio_venta, p.imagen_principal, c.nombre_categoria 
            FROM productos p
            INNER JOIN categorias c ON p.id_categoria = c.id_categoria
            WHERE p.activo = 1
            ORDER BY p.fecha_creacion DESC";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos - Mi Ferretería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/navgar.php"); ?> <!-- tu nav dinámico -->

<div class="container mt-5">
    <h2 class="mb-4 text-center">
        <?php 
        if ($categoria_id > 0) {
            // Mostrar nombre de categoría
            $sqlCat = "SELECT nombre_categoria FROM categorias WHERE id_categoria = ?";
            $stmtCat = $conn->prepare($sqlCat);
            $stmtCat->bind_param("i", $categoria_id);
            $stmtCat->execute();
            $resCat = $stmtCat->get_result()->fetch_assoc();
            echo htmlspecialchars($resCat['nombre_categoria']);
        } else {
            echo "Todos los productos";
        }
        ?>
    </h2>

    <div class="row">
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) { ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <img src="../<?php echo $row['imagen_principal']; ?>" 
                             class="card-img-top" 
                             alt="<?php echo $row['nombre_producto']; ?>">

                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo $row['nombre_producto']; ?></h5>
                            <p class="text-muted"><?php echo $row['nombre_categoria']; ?></p>
                            <p class="fw-bold">S/ <?php echo number_format($row['precio'], 2); ?></p>
                        </div>
                        <div class="card-footer text-center">
                            <a href="detalle_producto.php?id=<?php echo $row['id_producto']; ?>" class="btn btn-outline-primary btn-sm">Ver más</a>
                            <form method="POST" action="carrito.php" class="d-inline">
                                <input type="hidden" name="id_producto" value="<?php echo $row['id_producto']; ?>">
                                <input type="hidden" name="cantidad" value="1">
                                <button type="submit" class="btn btn-success btn-sm">Añadir al carrito</button>
                            </form>
                        </div>
                    </div>
                </div>
        <?php }
        } else {
            echo "<p class='text-center'>No hay productos en esta categoría.</p>";
        }
        ?>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>