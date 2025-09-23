<?php
session_start();
include("../config/conexion.php");

// Consultar productos
$sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion, p.sku, p.precio, p.stock, p.imagen_principal, c.nombre_categoria AS categoria
        FROM productos p
        INNER JOIN categorias c ON p.id_categoria = c.id_categoria
        WHERE p.activo = 1
        ORDER BY p.fecha_creacion DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Ferretería - Inicio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 10px;
            transition: transform .2s ease-in-out, box-shadow .2s ease-in-out;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .card img {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            height: 200px;
            object-fit: cover;
        }
        footer {
            margin-top: 50px;
            background: #212529;
            color: white;
            padding: 20px 0;
        }
    </style>
</head>
<body>

<?php include '../includes/navgar.php'; ?>

<!-- HEADER -->
<header class="bg-primary py-5 text-white text-center">
    <div class="container">
        <h1 class="fw-bold">Bienvenido a Mi Ferretería</h1>
        <p class="lead">Herramientas, maquinaria y servicios al mejor precio</p>
    </div>
</header>

<!-- PRODUCTOS -->
<div class="container my-5">
    <div class="row g-4">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-3">
                    <div class="card h-100">
                        <img src="../<?php echo $row['imagen_principal']; ?>" 
                             alt="<?php echo $row['nombre_producto']; ?>">
                        <div class="card-body text-center">
                            <h5 class="card-title fw-bold"><?php echo $row['nombre_producto']; ?></h5>
                            <p class="text-muted small"><?php echo $row['categoria']; ?></p>
                            <p class="fw-bold text-success">S/ <?php echo number_format($row['precio'], 2); ?></p>
                        </div>
                        <!-- Botón -->
                        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <div class="d-flex justify-content-between">
                                <!-- Ver más -->
                                <a class="btn btn-outline-dark mt-auto me-2" 
                                href="detalle_producto.php?id=<?php echo $row['id_producto']; ?>">
                                Ver más
                                </a>

                                <!-- Añadir al carrito -->
                                <form method="POST" action="carrito.php" class="m-0">
                                    <input type="hidden" name="id_producto" value="<?php echo $row['id_producto']; ?>">
                                    <button type="submit" class="btn btn-primary mt-auto">
                                        Añadir al carrito
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center">No hay productos disponibles.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>