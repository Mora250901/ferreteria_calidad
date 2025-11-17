<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

// --------- Parámetros de búsqueda / filtros / paginación ----------
$q         = trim($_GET['q'] ?? '');
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$orden     = $_GET['orden'] ?? 'reciente';

$pagina    = max(1, (int)($_GET['page'] ?? 1));
$limite    = 12;
$offset    = ($pagina - 1) * $limite;

// Cargar categorías para el filtro CON ICONOS
$cats = [];
$query_cats = "SELECT id_categoria, nombre_categoria, descripcion FROM categorias ORDER BY nombre_categoria";
if ($rs = $conn->query($query_cats)) {
    $cats = $rs->fetch_all(MYSQLI_ASSOC);
}

// Mapeo de iconos para cada categoría
$iconos_categorias = [
    'Tornillos, Tuercas y Pernos' => 'bi-nut',
    'Clavos y Grapas' => 'bi-hammer',
    'Pinturas y Barnices' => 'bi-paint-bucket',
    'Fontanería' => 'bi-droplet',
    'Electricidad' => 'bi-lightning-charge',
    'Herramientas Manuales' => 'bi-tools',
    'Cintas y Adhesivos' => 'bi-tape',
    'Seguridad y Protección' => 'bi-shield-check',
    'Ferretería General / Accesorios' => 'bi-box-seam',
    'Lubricantes y Aceites' => 'bi-moisture',
    'Abrasivos y Lijas' => 'bi-file-earmark',
    'Almacenamiento y Organización' => 'bi-archive'
];

// --------- Armar SQL con filtros (prepared) ----------
$where = " WHERE p.activo = 1 ";
$params = [];
$types  = "";

if ($q !== "") {
    $where .= " AND (p.nombre_producto LIKE ? OR p.descripcion LIKE ? OR p.sku LIKE ?) ";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sss";
}

if ($categoria > 0) {
    $where .= " AND p.id_categoria = ? ";
    $params[] = $categoria;
    $types   .= "i";
}

// Determinar orden
$orderBy = "ORDER BY p.fecha_creacion DESC";
switch($orden) {
    case 'precio_asc': $orderBy = "ORDER BY p.precio ASC"; break;
    case 'precio_desc': $orderBy = "ORDER BY p.precio DESC"; break;
    case 'nombre': $orderBy = "ORDER BY p.nombre_producto ASC"; break;
    case 'reciente': 
    default: $orderBy = "ORDER BY p.fecha_creacion DESC"; break;
}

// Total para paginar
$sqlCount = "SELECT COUNT(*) AS total
             FROM productos p
             INNER JOIN categorias c ON p.id_categoria = c.id_categoria
             $where";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$paginas = max(1, (int)ceil($total / $limite));

// Consulta paginada
$sql = "SELECT p.id_producto, p.nombre_producto, p.descripcion, p.sku,
               p.precio, p.stock, p.imagen_principal,
               c.nombre_categoria AS categoria
        FROM productos p
        INNER JOIN categorias c ON p.id_categoria = c.id_categoria
        $where
        $orderBy
        LIMIT ? OFFSET ?";
$params2 = $params;
$types2  = $types . "ii";
$params2[] = $limite;
$params2[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

// Obtener nombre de categoría seleccionada
$nombreCatSeleccionada = '';
if ($categoria > 0) {
    $catSel = array_values(array_filter($cats, fn($x) => $x['id_categoria'] == $categoria));
    $nombreCatSeleccionada = $catSel[0]['nombre_categoria'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Ferretería - Encuentra todo lo que necesitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #dc3545;
            --primary-blue: #0d6efd;
            --success-color: #198754;
            --warning-color: #ffc107;
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* HERO SECTION CON BANNER */
        .hero-banner {
            background: linear-gradient(135deg, #123bf1ff 0%, #39b1f7ff 100%);
            padding: 80px 0 60px;
            position: relative;
            overflow: hidden;
            margin-bottom: 0;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="rgba(255,255,255,0.05)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            color: white;
            text-align: center;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,.2);
        }

        .hero-subtitle {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        /* BUSCADOR */
        .search-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .search-box {
            background: white;
            border-radius: 50px;
            padding: 8px;
            box-shadow: 0 8px 30px rgba(0,0,0,.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            outline: none;
        }

        .search-box select {
            border: none;
            padding: 12px 15px;
            font-size: 15px;
            background: #f8f9fa;
            border-radius: 25px;
            outline: none;
            min-width: 180px;
        }

        .search-box .btn {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
        }

        /* CATEGORÍAS CIRCULARES */
        .categories-section {
            background: white;
            padding: 50px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .categories-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 40px;
            color: #212529;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .category-item {
            text-align: center;
            text-decoration: none;
            transition: all .3s;
        }

        .category-circle {
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary-red) 0%, #2342f5ff 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(59, 247, 35, 0.3);
            transition: all .3s;
            position: relative;
            overflow: hidden;
        }

        .category-circle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,.2) 0%, transparent 70%);
            transform: scale(0);
            transition: transform .3s;
        }

        .category-item:hover .category-circle::before {
            transform: scale(1);
        }

        .category-circle i {
            font-size: 48px;
            color: white;
            z-index: 1;
        }

        .category-item:hover .category-circle {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 8px 25px rgba(220, 53, 69, .4);
        }

        .category-name {
            font-size: 14px;
            font-weight: 600;
            color: #212529;
            margin: 0;
            line-height: 1.4;
        }

        .category-item.active .category-circle {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #4dabf7 100%);
            box-shadow: 0 8px 25px rgba(13, 110, 253, .4);
            transform: scale(1.1);
        }

        /* SECCIÓN DE PRODUCTOS */
        .products-section {
            padding: 40px 0;
        }

        /* Filtros sticky */
        .filters-bar {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            position: sticky;
            top: 56px;
            z-index: 999;
            margin-bottom: 30px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #e9ecef;
            border-radius: 20px;
            margin: 5px;
            font-size: 14px;
            text-decoration: none;
            color: #495057;
            transition: all .2s;
        }

        .filter-chip:hover {
            background: #dee2e6;
            color: #212529;
        }

        .filter-chip .remove-filter {
            color: var(--primary-red);
            font-weight: bold;
            cursor: pointer;
        }

        /* Cards de productos */
        .product-card {
            border: none;
            border-radius: 16px;
            transition: all .3s;
            background: white;
            overflow: hidden;
            height: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,.15);
        }

        .product-card .card-img-wrapper {
            position: relative;
            overflow: hidden;
            height: 240px;
            background: #f8f9fa;
        }

        .product-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .3s;
        }

        .product-card:hover img {
            transform: scale(1.08);
        }

        .stock-indicator {
            position: absolute;
            bottom: 12px;
            left: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255,255,255,.95);
        }

        .stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success-color);
        }

        .stock-dot.low { background: var(--warning-color); }
        .stock-dot.out { background: var(--primary-red); }

        .product-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--success-color);
        }

        /* Paginación */
        .pagination {
            gap: 5px;
        }

        .page-link {
            border-radius: 8px;
            border: none;
            margin: 0 2px;
            font-weight: 600;
            color: #495057;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,.08);
        }

        .page-item.active .page-link {
            background: var(--primary-red);
            box-shadow: 0 4px 8px rgba(220, 53, 69, .3);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 32px;
            }

            .categories-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }

            .category-circle {
                width: 90px;
                height: 90px;
            }

            .category-circle i {
                font-size: 36px;
            }

            .category-name {
                font-size: 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/chatbot/chatbot.css">
</head>
<body>

<?php include __DIR__ . '/../includes/navgar.php'; ?>

<!-- Hero Banner con Buscador -->
<section class="hero-banner">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Encuentra Todo lo que Necesitas</h1>
            <p class="hero-subtitle">Miles de productos para tu hogar, obra o negocio</p>

            <!-- Buscador -->
            <div class="search-container">
                <form method="get" action="index_home.php">
                    <div class="search-box">
                        <i class="bi bi-search ms-3 text-muted"></i>
                        <input type="text" 
                               name="q" 
                               value="<?= htmlspecialchars($q) ?>"
                               placeholder="Buscar productos, marcas o categorías...">
                        <select name="categoria" class="form-select-sm">
                            <option value="0">Todas las categorías</option>
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= (int)$c['id_categoria'] ?>" 
                                        <?= $categoria === (int)$c['id_categoria'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre_categoria']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-danger" type="submit">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Sección de Categorías Circulares -->
<?php if ($categoria === 0 && $q === ''): ?>
<section class="categories-section">
    <div class="container">
        <h2 class="categories-title">Explora por Categorías</h2>
        <div class="categories-grid">
            <?php foreach ($cats as $cat): 
                $icono = $iconos_categorias[$cat['nombre_categoria']] ?? 'bi-box';
            ?>
                <a href="?categoria=<?= $cat['id_categoria'] ?>" 
                   class="category-item">
                    <div class="category-circle">
                        <i class="bi <?= $icono ?>"></i>
                    </div>
                    <p class="category-name"><?= htmlspecialchars($cat['nombre_categoria']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Sección de Productos -->
<section class="products-section">
    <div class="container">
        <!-- Barra de Filtros -->
        <div class="filters-bar">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center flex-wrap">
                        <span class="text-muted me-3">
                            <strong><?= number_format($total) ?></strong> 
                            producto<?= $total === 1 ? '' : 's' ?>
                        </span>
                        
                        <?php if ($q !== '' || $categoria > 0): ?>
                            <div class="d-inline-flex flex-wrap">
                                <?php if ($q !== ''): ?>
                                    <span class="filter-chip">
                                        <i class="bi bi-search"></i>
                                        "<?= htmlspecialchars($q) ?>"
                                        <a href="?<?= http_build_query(array_diff_key($_GET, ['q' => ''])) ?>" 
                                           class="remove-filter text-decoration-none">×</a>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($categoria > 0): ?>
                                    <span class="filter-chip">
                                        <i class="bi bi-tag"></i>
                                        <?= htmlspecialchars($nombreCatSeleccionada) ?>
                                        <a href="?<?= http_build_query(array_diff_key($_GET, ['categoria' => ''])) ?>" 
                                           class="remove-filter text-decoration-none">×</a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <form method="get" class="d-flex justify-content-md-end align-items-center gap-2">
                        <?php foreach ($_GET as $k => $v): ?>
                            <?php if ($k !== 'orden' && $k !== 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <label class="text-muted small mb-0">Ordenar:</label>
                        <select class="form-select form-select-sm" name="orden" style="width: auto;" 
                                onchange="this.form.submit()">
                            <option value="reciente" <?= $orden === 'reciente' ? 'selected' : '' ?>>Más recientes</option>
                            <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: menor</option>
                            <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor</option>
                            <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre A-Z</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- Grid de Productos -->
        <div class="row g-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $img = $row['imagen_principal'] ? "../" . $row['imagen_principal'] : "assets/img/placeholder.jpg";
                    $stock = (int)$row['stock'];
                    $stock_status = $stock <= 0 ? 'out' : ($stock <= 5 ? 'low' : 'ok');
                    $stock_text = $stock <= 0 ? 'Agotado' : ($stock <= 5 ? "Quedan $stock" : 'Disponible');
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="product-card card h-100">
                            <div class="card-img-wrapper">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($row['nombre_producto']) ?>">
                                
                                <div class="stock-indicator">
                                    <span class="stock-dot <?= $stock_status ?>"></span>
                                    <?= $stock_text ?>
                                </div>
                            </div>
                            
                            <div class="card-body text-center">
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($row['nombre_producto']) ?></h5>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($row['categoria']) ?></p>
                                <p class="product-price mb-0">S/ <?= number_format((float)$row['precio'], 2) ?></p>
                            </div>

                            <div class="card-footer p-3 bg-transparent">
                                <div class="d-flex gap-2">
                                    <a class="btn btn-outline-dark flex-fill btn-sm"
                                       href="detalle_producto.php?id=<?= (int)$row['id_producto'] ?>">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>

                                    <form method="POST" action="carrito.php" class="m-0 flex-fill">
                                        <input type="hidden" name="accion" value="agregar">
                                        <input type="hidden" name="id_producto" value="<?= (int)$row['id_producto'] ?>">
                                        <input type="hidden" name="cantidad" value="1">
                                        <button type="submit" class="btn btn-danger w-100 btn-sm"
                                                <?= $stock <= 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-cart-plus"></i> Agregar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3 class="text-muted">No encontramos productos</h3>
                        <p class="text-muted">Intenta con otros términos de búsqueda o filtros</p>
                        <a href="index_home.php" class="btn btn-danger mt-3">
                            <i class="bi bi-arrow-left"></i> Ver todos los productos
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ($paginas > 1): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina - 1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    $rango = 2;
                    $inicio = max(1, $pagina - $rango);
                    $fin = min($paginas, $pagina + $rango);
                    
                    if ($inicio > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($inicio > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($p = $inicio; $p <= $fin; $p++): ?>
                        <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($fin < $paginas): ?>
                        <?php if ($fin < $paginas - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $paginas])) ?>">
                                <?= $paginas ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagina + 1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Scroll suave después de buscar
if (window.location.search && document.querySelector('.products-section')) {
    setTimeout(() => {
        document.querySelector('.products-section').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }, 100);
}
</script>

<script src="../assets/chatbot/chatbot.js"></script>
</body>
</html>
