<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

// Verificar conexión a la base de datos
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Variables de usuario
$usuario_id = isset($_SESSION['autenticado']) ? $_SESSION['usuario_data']['id_usuario'] : null;
$nombre_usuario = isset($_SESSION['autenticado']) ? $_SESSION['usuario_data']['usuario'] : null;

// Validar y sanitizar ID del producto
$id_producto = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_producto <= 0) {
    header("Location: index_home.php");
    exit;
}

// Obtener información del producto
$query_producto = "SELECT p.*, c.nombre_categoria AS nombre_categoria 
                  FROM productos p
                  JOIN categorias c ON p.id_categoria = c.id_categoria
                  WHERE p.id_producto = ? AND p.activo = 1";
$stmt_producto = $conn->prepare($query_producto);
$stmt_producto->bind_param("i", $id_producto);
$stmt_producto->execute();
$producto = $stmt_producto->get_result()->fetch_assoc();

if (!$producto) {
    header("Location: index_home.php");
    exit;
}

// Obtener variaciones CON TIPO
$query_variaciones = "SELECT v.*, 
                      (SELECT COUNT(*) FROM variacion_opciones WHERE id_variacion = v.id_variacion) as total_opciones
                      FROM variaciones v 
                      WHERE v.id_producto = ?
                      ORDER BY v.id_variacion";
$stmt_variaciones = $conn->prepare($query_variaciones);
$stmt_variaciones->bind_param("i", $id_producto);
$stmt_variaciones->execute();
$variaciones = $stmt_variaciones->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener opciones para cada variación
$variaciones_con_opciones = [];
foreach ($variaciones as $variacion) {
    $query_opciones = "SELECT * FROM variacion_opciones WHERE id_variacion = ? ORDER BY id_opcion";
    $stmt_opciones = $conn->prepare($query_opciones);
    $stmt_opciones->bind_param("i", $variacion['id_variacion']);
    $stmt_opciones->execute();
    $opciones = $stmt_opciones->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $variacion['opciones'] = $opciones;
    $variaciones_con_opciones[] = $variacion;
    $stmt_opciones->close();
}

// Obtener atributos específicos del producto según su categoría
$query_atributos = "SELECT 
    a.nombre_atributo,
    a.tipo_atributo,
    pa.valor_texto,
    pa.valor_numero,
    pa.valor_decimal,
    pa.valor_booleano,
    pa.valor_fecha,
    ca.orden
FROM productos_atributos pa
INNER JOIN atributos a ON pa.id_atributo = a.id_atributo
INNER JOIN categorias_atributos ca ON (ca.id_atributo = a.id_atributo AND ca.id_categoria = ?)
WHERE pa.id_producto = ?
ORDER BY ca.orden ASC";

$stmt_atributos = $conn->prepare($query_atributos);
$stmt_atributos->bind_param("ii", $producto['id_categoria'], $id_producto);
$stmt_atributos->execute();
$atributos = $stmt_atributos->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener productos relacionados de la misma categoría
$query_relacionados = "SELECT p.*, c.nombre_categoria 
                      FROM productos p
                      JOIN categorias c ON p.id_categoria = c.id_categoria
                      WHERE p.id_categoria = ? AND p.id_producto != ? AND p.activo = 1
                      ORDER BY RAND()
                      LIMIT 4";
$stmt_relacionados = $conn->prepare($query_relacionados);
$stmt_relacionados->bind_param("ii", $producto['id_categoria'], $id_producto);
$stmt_relacionados->execute();
$productos_relacionados = $stmt_relacionados->get_result()->fetch_all(MYSQLI_ASSOC);

$stock = (int)$producto['stock'];
$stock_status = $stock <= 0 ? 'out' : ($stock <= 5 ? 'low' : 'ok');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title><?= htmlspecialchars($producto['nombre_producto']) ?> - Mi Ferretería</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #dc3545;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Breadcrumb */
        .breadcrumb {
            background: white;
            padding: 15px 0;
            margin-bottom: 0;
        }

        /* Galería de imágenes */
        .product-gallery {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            position: sticky;
            top: 80px;
        }

        .main-image-container {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
            margin-bottom: 20px;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            cursor: zoom-in;
            transition: transform 0.3s;
        }

        .main-image:hover {
            transform: scale(1.05);
        }

        /* Información del producto */
        .product-info {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
        }

        .product-title {
            font-size: 28px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .product-sku {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .rating-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .stars {
            color: #ffc107;
            font-size: 18px;
        }

        .price-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 2px solid #ffebee;
        }

        .current-price {
            font-size: 36px;
            font-weight: 700;
            color: var(--danger-color);
            margin-bottom: 5px;
        }

        /* Stock indicator */
        .stock-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .stock-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success-color);
        }

        .stock-dot.low { background: var(--warning-color); }
        .stock-dot.out { background: var(--danger-color); }

        .stock-text {
            font-weight: 600;
            font-size: 15px;
        }

        .stock-text.low { color: var(--warning-color); }
        .stock-text.out { color: var(--danger-color); }

        /* Variaciones */
        .variation-section {
            margin-bottom: 25px;
        }

        .variation-label {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 12px;
            color: #212529;
        }

        /* TARJETAS DE COLORES */
        .color-card {
            display: block;
            cursor: pointer;
            transition: all .3s;
        }

        .color-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .color-card-inner {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: all .3s;
            height: 100%;
        }

        .color-card:hover .color-card-inner {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
            transform: translateY(-4px);
        }

        .color-card input[type="radio"]:checked + .color-card-inner {
            border-color: var(--primary-color);
            border-width: 3px;
            box-shadow: 0 0 0 4px rgba(220, 53, 69, .15);
        }

        .color-sample {
            position: relative;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #e9ecef;
        }

        .color-sample[style*="#FFF"],
        .color-sample[style*="#fff"],
        .color-sample[style*="white"] {
            border: 1px solid #dee2e6 !important;
        }

        .check-mark {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            color: white;
            opacity: 0;
            transition: all .3s;
            text-shadow: 0 2px 4px rgba(0,0,0,.3);
        }

        .color-card input[type="radio"]:checked ~ .color-card-inner .check-mark {
            opacity: 1;
            transform: scale(1.1);
        }

        .color-info {
            padding: 12px;
            text-align: center;
        }

        .color-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #212529;
        }

        .color-code {
            display: block;
            font-size: 11px;
            color: #6c757d;
            font-family: 'Courier New', monospace;
            margin-bottom: 8px;
        }

        /* Selector de texto para otras variaciones */
        .text-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .text-option {
            position: relative;
        }

        .text-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .text-option-label {
            display: block;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all .2s;
            font-weight: 500;
            min-width: 80px;
            text-align: center;
        }

        .text-option:hover .text-option-label {
            border-color: var(--primary-color);
            background: #fff5f5;
        }

        .text-option input[type="radio"]:checked + .text-option-label {
            border-color: var(--primary-color);
            background: #ffe5e5;
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Cantidad */
        .quantity-section {
            margin-bottom: 25px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            width: fit-content;
        }

        .quantity-btn {
            width: 45px;
            height: 45px;
            border: none;
            background: #f8f9fa;
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }

        .quantity-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .quantity-input {
            width: 80px;
            height: 45px;
            border: none;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            background: white;
        }

        .quantity-input:focus {
            outline: none;
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .btn-add-cart {
            flex: 1;
            padding: 16px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            transition: all .3s;
        }

        .btn-add-cart:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, .3);
        }

        .btn-favorite {
            width: 55px;
            height: 55px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            background: white;
            font-size: 22px;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-favorite:hover {
            border-color: var(--danger-color);
            color: var(--danger-color);
            background: #fff5f5;
        }

        /* Características */
        .features-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #212529;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 15px 25px;
            transition: all .2s;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background: transparent;
        }

        .tab-content {
            padding: 30px 0;
        }

        /* Grid de especificaciones */
        .specifications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .spec-item {
            display: flex;
            align-items: start;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
        }

        .spec-icon {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 18px;
            flex-shrink: 0;
        }

        .spec-content {
            flex: 1;
        }

        .spec-label {
            display: block;
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 4px;
        }

        .spec-value {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }

        /* Feature items */
        .feature-item {
            display: flex;
            align-items: start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: #fff5f5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Productos relacionados */
        .related-product-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all .3s;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,.15);
        }

        .related-product-card img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }

        /* Badges informativos */
        .info-badges {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .info-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .info-badge i {
            color: var(--primary-color);
        }

        /* Indicador de error en variaciones */
        .variation-required {
            color: var(--danger-color);
            font-size: 12px;
            margin-top: 8px;
            display: none;
        }

        .variation-section.error .variation-required {
            display: block;
        }

        .variation-section.error .variation-label {
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .product-gallery {
                position: relative;
                top: 0;
            }

            .product-title {
                font-size: 24px;
            }

            .current-price {
                font-size: 28px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-favorite {
                width: 100%;
            }

            .specifications-grid {
                grid-template-columns: 1fr;
            }

            .color-sample {
                height: 100px;
            }
        }
    </style>
</head>
<body>
    
<?php include '../core/navgar.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="breadcrumb-section">
    <div class="container">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index_home.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index_home.php?categoria=<?= $producto['id_categoria'] ?>">
                <?= htmlspecialchars($producto['nombre_categoria']) ?>
            </a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($producto['nombre_producto']) ?></li>
        </ol>
    </div>
</nav>

<!-- Product section -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Galería de Imágenes -->
            <div class="col-lg-5">
                <div class="product-gallery">
                    <div class="main-image-container">
                        <?php
                        $imagen_principal = !empty($producto['imagen_principal']) 
                            ? "../" . $producto['imagen_principal'] 
                            : "assets/img/placeholder.jpg";
                        ?>
                        <img id="mainImage" src="<?= htmlspecialchars($imagen_principal) ?>" 
                             alt="<?= htmlspecialchars($producto['nombre_producto']) ?>" 
                             class="main-image">
                    </div>
                </div>
            </div>

            <!-- Información del Producto -->
            <div class="col-lg-7">
                <div class="product-info">
                    <!-- Categoría -->
                    <div class="mb-3">
                        <span class="badge bg-danger"><?= htmlspecialchars($producto['nombre_categoria']) ?></span>
                    </div>

                    <!-- Título -->
                    <h1 class="product-title"><?= htmlspecialchars($producto['nombre_producto']) ?></h1>

                    <!-- SKU -->
                    <?php if (!empty($producto['sku'])): ?>
                    <div class="product-sku">
                        <i class="bi bi-upc-scan"></i> SKU: <?= htmlspecialchars($producto['sku']) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Rating -->
                    <div class="rating-section">
                        <div class="stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-half"></i>
                        </div>
                        <span class="text-muted">(4.5) • 128 valoraciones</span>
                    </div>

                    <!-- Precio -->
                    <div class="price-section">
                        <div class="current-price">
                            S/ <?= number_format($producto['precio'], 2) ?>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-truck"></i> Envío gratis en compras mayores a S/ 100
                        </small>
                    </div>

                    <!-- Estado de Stock -->
                    <div class="stock-info">
                        <span class="stock-dot <?= $stock_status ?>"></span>
                        <span class="stock-text <?= $stock_status ?>">
                            <?php 
                            if ($stock <= 0) {
                                echo "Producto agotado";
                            } elseif ($stock <= 5) {
                                echo "¡Últimas $stock unidades disponibles!";
                            } else {
                                echo "Disponible en stock ($stock unidades)";
                            }
                            ?>
                        </span>
                    </div>

                    <!-- Badges Informativos -->
                    <div class="info-badges">
                        <div class="info-badge">
                            <i class="bi bi-shield-check"></i>
                            Compra protegida
                        </div>
                        <div class="info-badge">
                            <i class="bi bi-arrow-return-left"></i>
                            Devolución gratis
                        </div>
                        <div class="info-badge">
                            <i class="bi bi-award"></i>
                            Garantía incluida
                        </div>
                    </div>

                    <form action="carrito.php" method="post" id="formCarrito">
                        <input type="hidden" name="accion" value="agregar">
                        <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                        
                        <!-- VARIACIONES DINÁMICAS -->
                        <?php foreach ($variaciones_con_opciones as $variacion): ?>
                            <div class="variation-section" data-variacion-id="<?= $variacion['id_variacion'] ?>">
                                <div class="variation-label">
                                    <i class="bi bi-<?= $variacion['tipo_variacion'] === 'color' ? 'palette' : 'list-ul' ?>"></i> 
                                    <?= htmlspecialchars($variacion['nombre_variacion']) ?>:
                                    <span class="text-danger">*</span>
                                </div>

                                <?php if ($variacion['tipo_variacion'] === 'color'): ?>
                                    <!-- SELECTOR DE COLORES CON TARJETAS -->
                                    <div class="row g-3 mt-2">
                                        <?php foreach ($variacion['opciones'] as $opcion): ?>
                                            <div class="col-6 col-md-4 col-lg-3">
                                                <label class="color-card">
                                                    <input type="radio" 
                                                           name="variacion_<?= $variacion['id_variacion'] ?>" 
                                                           value="<?= $opcion['id_opcion'] ?>"
                                                           data-color="<?= htmlspecialchars($opcion['codigo_color']) ?>"
                                                           data-name="<?= htmlspecialchars($opcion['valor_opcion']) ?>"
                                                           required>
                                                    
                                                    <div class="color-card-inner">
                                                        <div class="color-sample" 
                                                             style="background-color: <?= htmlspecialchars($opcion['codigo_color']) ?>;">
                                                            <span class="check-mark">
                                                                <i class="bi bi-check-circle-fill"></i>
                                                            </span>
                                                        </div>
                                                        
                                                        <div class="color-info">
                                                            <h6 class="color-name"><?= htmlspecialchars($opcion['valor_opcion']) ?></h6>
                                                            <small class="color-code"><?= htmlspecialchars($opcion['codigo_color']) ?></small>
                                                            
                                                            <?php if ($opcion['stock'] > 0): ?>
                                                                <span class="badge bg-success mt-1">
                                                                    <i class="bi bi-check2"></i> Disponible
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger mt-1">
                                                                    <i class="bi bi-x"></i> Agotado
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                <?php else: ?>
                                    <!-- SELECTOR DE TEXTO/TALLA/MEDIDA -->
                                    <div class="text-selector">
                                        <?php foreach ($variacion['opciones'] as $opcion): ?>
                                            <label class="text-option">
                                                <input type="radio" 
                                                       name="variacion_<?= $variacion['id_variacion'] ?>" 
                                                       value="<?= $opcion['id_opcion'] ?>"
                                                       required>
                                                <span class="text-option-label">
                                                    <?= htmlspecialchars($opcion['valor_opcion']) ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="variation-required">
                                    <i class="bi bi-exclamation-circle"></i> 
                                    Por favor selecciona <?= strtolower($variacion['nombre_variacion']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Cantidad -->
                        <div class="quantity-section">
                            <div class="variation-label">
                                <i class="bi bi-cart3"></i> Cantidad:
                            </div>
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity()">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="number" 
                                       class="quantity-input" 
                                       id="cantidad" 
                                       name="cantidad" 
                                       value="1" 
                                       min="1" 
                                       max="<?= $stock ?>" 
                                       readonly>
                                <button type="button" class="quantity-btn" onclick="increaseQuantity()">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block">Máximo <?= $stock ?> unidades</small>
                        </div>
                        
                        <!-- Botones de Acción -->
                        <div class="action-buttons">
                            <button class="btn btn-danger btn-add-cart" 
                                    type="submit"
                                    <?= $stock <= 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-cart-plus me-2"></i>
                                <?= $stock <= 0 ? 'Producto agotado' : 'Agregar al carrito' ?>
                            </button>
                            <button type="button" class="btn-favorite" title="Agregar a favoritos">
                                <i class="bi bi-heart"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Descripción Corta -->
                    <div class="mt-4">
                        <p class="text-muted">
                            <?= nl2br(htmlspecialchars($producto['descripcion'])) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs de Información Detallada -->
        <div class="features-section mt-5">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#descripcion">
                        <i class="bi bi-file-text me-2"></i>Descripción
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#especificaciones">
                        <i class="bi bi-list-check me-2"></i>Especificaciones
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#envio">
                        <i class="bi bi-truck me-2"></i>Envío y devoluciones
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Tab Descripción -->
                <div class="tab-pane fade show active" id="descripcion">
                    <h5 class="mb-3">Descripción del producto</h5>
                    <p><?= nl2br(htmlspecialchars($producto['descripcion'])) ?></p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <strong>Calidad garantizada</strong>
                                    <p class="mb-0 text-muted small">Productos certificados y de alta calidad</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-award"></i>
                                </div>
                                <div>
                                    <strong>Garantía incluida</strong>
                                    <p class="mb-0 text-muted small">12 meses de garantía del fabricante</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Especificaciones DINÁMICAS -->
                <div class="tab-pane fade" id="especificaciones">
                    <h5 class="mb-3">Especificaciones técnicas</h5>
                    
                    <?php if (!empty($atributos)): ?>
                    <div class="specifications-grid">
                        <?php foreach ($atributos as $attr): 
                            // Determinar el valor según el tipo
                            $valor = '';
                            switch($attr['tipo_atributo']) {
                                case 'texto':
                                    $valor = $attr['valor_texto'];
                                    break;
                                case 'numero':
                                    $valor = number_format($attr['valor_numero']);
                                    break;
                                case 'decimal':
                                    $valor = number_format($attr['valor_decimal'], 2);
                                    break;
                                case 'booleano':
                                    $valor = $attr['valor_booleano'] ? 'Sí' : 'No';
                                    break;
                                case 'fecha':
                                    $valor = date('d/m/Y', strtotime($attr['valor_fecha']));
                                    break;
                            }
                        ?>
                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="spec-content">
                                <span class="spec-label"><?= htmlspecialchars($attr['nombre_atributo']) ?></span>
                                <span class="spec-value"><?= htmlspecialchars($valor) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No hay especificaciones técnicas disponibles para este producto.</p>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <!-- Información general del producto -->
                    <h6 class="mb-3">Información general</h6>
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td class="fw-bold" style="width: 30%;">
                                    <i class="bi bi-tag me-2 text-danger"></i>Categoría
                                </td>
                                <td><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">
                                    <i class="bi bi-upc-scan me-2 text-danger"></i>SKU
                                </td>
                                <td><?= htmlspecialchars($producto['sku']) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">
                                    <i class="bi bi-box-seam me-2 text-danger"></i>Stock disponible
                                </td>
                                <td><?= $stock ?> unidades</td>
                            </tr>
                            <?php if (!empty($variaciones_con_opciones)): ?>
                            <tr>
                                <td class="fw-bold">
                                    <i class="bi bi-palette me-2 text-danger"></i>Variaciones
                                </td>
                                <td><?= count($variaciones_con_opciones) ?> opciones disponibles</td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="fw-bold">
                                    <i class="bi bi-calendar3 me-2 text-danger"></i>Fecha de registro
                                </td>
                                <td><?= date('d/m/Y', strtotime($producto['fecha_creacion'])) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tab Envío y Devoluciones -->
                <div class="tab-pane fade" id="envio">
                    <h5 class="mb-3">Información de envío y devoluciones</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-truck"></i>
                                </div>
                                <div>
                                    <strong>Envío gratis</strong>
                                    <p class="mb-0 text-muted small">En compras mayores a S/ 100.00 en Lima Metropolitana</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <strong>Tiempo de entrega</strong>
                                    <p class="mb-0 text-muted small">24-48 horas en Lima Metropolitana<br>3-5 días en provincias</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <div>
                                    <strong>Cobertura</strong>
                                    <p class="mb-0 text-muted small">Entregas a nivel nacional</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-arrow-return-left"></i>
                                </div>
                                <div>
                                    <strong>Devoluciones gratuitas</strong>
                                    <p class="mb-0 text-muted small">30 días para devoluciones sin costo adicional</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <strong>Compra protegida</strong>
                                    <p class="mb-0 text-muted small">Protección total en tu compra, garantía de satisfacción</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-credit-card"></i>
                                </div>
                                <div>
                                    <strong>Métodos de pago</strong>
                                    <p class="mb-0 text-muted small">Tarjeta, Yape, Plin, Transferencia y Efectivo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos Relacionados -->
        <?php if (!empty($productos_relacionados)): ?>
        <div class="mt-5">
            <h3 class="section-title mb-4">
                <i class="bi bi-grid me-2"></i>Productos relacionados
            </h3>
            <div class="row g-4">
                <?php foreach ($productos_relacionados as $relacionado): 
                    $img_rel = !empty($relacionado['imagen_principal']) 
                        ? "../" . $relacionado['imagen_principal'] 
                        : "assets/img/placeholder.jpg";
                ?>
                <div class="col-lg-3 col-md-6">
                    <a href="detalle_producto.php?id=<?= $relacionado['id_producto'] ?>" 
                       class="related-product-card">
                        <img src="<?= htmlspecialchars($img_rel) ?>" 
                             alt="<?= htmlspecialchars($relacionado['nombre_producto']) ?>">
                        <div class="p-3">
                            <h6 class="fw-bold mb-2"><?= htmlspecialchars($relacionado['nombre_producto']) ?></h6>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-tag"></i> <?= htmlspecialchars($relacionado['nombre_categoria']) ?>
                            </p>
                            <p class="text-danger fw-bold mb-0">S/ <?= number_format($relacionado['precio'], 2) ?></p>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include('../core/footer.php'); ?>

<!-- Cerrar conexiones -->
<?php
$stmt_producto->close();
$stmt_variaciones->close();
$stmt_atributos->close();
$stmt_relacionados->close();
$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Control de cantidad
const maxStock = <?= $stock ?>;

function increaseQuantity() {
    const input = document.getElementById('cantidad');
    let value = parseInt(input.value);
    if (value < maxStock) {
        input.value = value + 1;
    }
}

function decreaseQuantity() {
    const input = document.getElementById('cantidad');
    let value = parseInt(input.value);
    if (value > 1) {
        input.value = value - 1;
    }
}

// Manejo de selección de variaciones
document.querySelectorAll('.variation-section input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remover error si existe
        this.closest('.variation-section').classList.remove('error');
    });
});

// Validación del formulario
document.getElementById('formCarrito').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validar todas las variaciones
    document.querySelectorAll('.variation-section').forEach(section => {
        const radios = section.querySelectorAll('input[type="radio"]');
        const checked = Array.from(radios).some(radio => radio.checked);
        
        if (!checked) {
            section.classList.add('error');
            isValid = false;
        } else {
            section.classList.remove('error');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        // Scroll al primer error
        const firstError = document.querySelector('.variation-section.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Mostrar alerta
        showToast('Por favor selecciona todas las opciones requeridas', 'warning');
        return false;
    }
    
    // Confirmación
    const cantidad = document.getElementById('cantidad').value;
    if (!confirm(`¿Agregar ${cantidad} unidad(es) al carrito?`)) {
        e.preventDefault();
        return false;
    }
});

// Botón de favoritos
document.querySelector('.btn-favorite')?.addEventListener('click', function() {
    this.classList.toggle('active');
    const icon = this.querySelector('i');
    
    if (this.classList.contains('active')) {
        icon.classList.remove('bi-heart');
        icon.classList.add('bi-heart-fill');
        this.style.color = '#dc3545';
        this.style.borderColor = '#dc3545';
        this.style.background = '#fff5f5';
        
        showToast('Producto agregado a favoritos', 'success');
    } else {
        icon.classList.remove('bi-heart-fill');
        icon.classList.add('bi-heart');
        this.style.color = '';
        this.style.borderColor = '';
        this.style.background = '';
        
        showToast('Producto removido de favoritos', 'info');
    }
});

// Función para mostrar notificaciones toast
function showToast(message, type = 'success') {
    const bgClass = {
        'success': 'alert-success',
        'warning': 'alert-warning',
        'info': 'alert-info',
        'danger': 'alert-danger'
    }[type] || 'alert-success';
    
    const icon = {
        'success': 'bi-check-circle-fill',
        'warning': 'bi-exclamation-triangle-fill',
        'info': 'bi-info-circle-fill',
        'danger': 'bi-x-circle-fill'
    }[type] || 'bi-check-circle-fill';
    
    const toastContainer = document.createElement('div');
    toastContainer.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    `;
    
    toastContainer.innerHTML = `
        <div class="alert ${bgClass} alert-dismissible fade show" role="alert" style="min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,.15);">
            <i class="bi ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(toastContainer);
    
    setTimeout(() => {
        toastContainer.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toastContainer.remove(), 300);
    }, 3000);
}

// Animaciones CSS para toast
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Zoom en imagen
const mainImage = document.getElementById('mainImage');
mainImage?.addEventListener('click', function() {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: zoom-out;
        animation: fadeIn 0.2s;
    `;
    
    const img = document.createElement('img');
    img.src = this.src;
    img.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';
    
    modal.appendChild(img);
    document.body.appendChild(modal);
    
    modal.addEventListener('click', () => modal.remove());
});

// Scroll suave para tabs
document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function() {
        setTimeout(() => {
            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    });
});
</script>
<link rel="stylesheet" href="../assets/chatbot/chatbot.css">
<script src="../assets/chatbot/chatbot.js"></script>
</body>
</html>
