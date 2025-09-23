<?php
session_start();
require_once("../config/conexion.php");

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
    header("Location: index.php");
    exit;
}

// Obtener información del producto (CONSULTA PREPARADA)
$query_producto = "SELECT p.*, c.nombre_categoria AS nombre_categoria 
                  FROM productos p
                  JOIN categorias c ON p.id_categoria = c.id_categoria
                  WHERE p.id_producto = ? AND p.activo = 1";
$stmt_producto = $conn->prepare($query_producto);
$stmt_producto->bind_param("i", $id_producto);
$stmt_producto->execute();
$producto = $stmt_producto->get_result()->fetch_assoc();

if (!$producto) {
    header("Location: index.php");
    exit;
}

// Obtener variaciones (CONSULTA PREPARADA)
$query_variaciones = "SELECT * FROM variaciones WHERE id_producto = ?";
$stmt_variaciones = $conn->prepare($query_variaciones);
$stmt_variaciones->bind_param("i", $id_producto);
$stmt_variaciones->execute();
$variaciones = $stmt_variaciones->get_result()->fetch_all(MYSQLI_ASSOC);

/* Obtener atributos (CONSULTA PREPARADA)
$query_atributos = "SELECT * FROM atributos_producto WHERE id_producto = ? ORDER BY orden";
$stmt_atributos = $conn->prepare($query_atributos);
$stmt_atributos->bind_param("i", $id_producto);
$stmt_atributos->execute();
$atributos = $stmt_atributos->get_result()->fetch_all(MYSQLI_ASSOC);*/
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - Mi Tienda</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="../assets/css/styles.css" rel="stylesheet" />
</head>
<body>
    
<?php include '../includes/navgar.php'; ?>

    <!-- Product section -->
    <section class="py-5">
        <div class="container px-4 px-lg-5 my-5">
            <div class="row gx-4 gx-lg-5 align-items-center">
                <div class="col-md-6">
                    <!-- Carrusel de imágenes del producto -->
                    <div id="carouselProducto" class="carousel slide mb-5" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php
                            $primera = true;

                            if (!empty($producto['imagen_principal'])) {
                                // Ruta pública (para el navegador)
                                $rutaPublica = "../" . $producto['imagen_principal'];

                                // Ruta física (para PHP file_exists)
                                $rutaFisica = __DIR__ . "/../" . $producto['imagen_principal'];

                                if (file_exists($rutaFisica)) {
                                    echo '<div class="carousel-item active">
                                            <img id="imagenPrincipal" src="'.$rutaPublica.'" class="d-block w-100" style="max-height:500px; object-fit:contain;">
                                        </div>';
                                    $primera = false;
                                } else {
                                    echo "<!-- No existe en servidor: $rutaFisica -->";
                                }
                            }

                            // Imágenes de variaciones
                            foreach ($variaciones as $variacion) {
                                if (!empty($variacion['imagen'])) {
                                    $rutaPublicaVar = "../img/variaciones/" . $variacion['imagen'];
                                    $rutaFisicaVar = __DIR__ . "/../img/variaciones/" . $variacion['imagen'];

                                    if (file_exists($rutaFisicaVar)) {
                                        echo '<div class="carousel-item '.($primera ? 'active' : '').'">
                                                <img src="'.$rutaPublicaVar.'" class="d-block w-100" style="max-height:500px; object-fit:contain;">
                                            </div>';
                                        $primera = false;
                                    }
                                }
                            }
                            ?>
                        </div>

                        <!-- Controles del carrusel -->
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselProducto" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselProducto" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h1 class="display-5 fw-bolder"><?php echo htmlspecialchars($producto['nombre_producto']); ?></h1>
                    <div class="fs-5 mb-5">
                        <span class="text-danger fw-bold">S/ <?php echo number_format($producto['precio'], 2); ?></span>
                    </div>
                    
                    <div class="mb-4">
                        <p class="lead"><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                    </div>
                    
                    <?php if(!empty($atributos)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Especificaciones técnicas</h5>
                            <ul class="list-unstyled">
                                <?php foreach($atributos as $atributo): ?>
                                <li class="mb-2">
                                    <strong><?php echo htmlspecialchars($atributo['clave']); ?>:</strong> 
                                    <span><?php echo htmlspecialchars($atributo['valor']); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form action="carrito.php" method="post" class="mt-4">
                        <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                        
                        <?php if(!empty($variaciones)): ?>
                        <div class="mb-3">
                            <label for="variacion" class="form-label fw-bold">Opciones disponibles:</label>
                            <select class="form-select" id="variacion" name="id_variacion" required>
                                <?php 
                                foreach($variaciones as $variacion): 
                                    $stmt_opciones = $conn->prepare("SELECT valor FROM variacion_opciones WHERE id_variacion = ?");
                                    $stmt_opciones->bind_param("i", $variacion['id_variacion']);
                                    $stmt_opciones->execute();
                                    $opciones = $stmt_opciones->get_result()->fetch_all(MYSQLI_ASSOC);
                                    $texto_opciones = array_column($opciones, 'valor');
                                ?>
                                <option value="<?php echo $variacion['id_variacion']; ?>" 
                                        data-imagen="img/variaciones/<?php echo $variacion['imagen']; ?>">
                                    <?php echo htmlspecialchars(implode(" / ", $texto_opciones)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label for="cantidad" class="col-form-label fw-bold">Cantidad:</label>
                            </div>
                            <div class="col-auto">
                                <input class="form-control" type="number" id="cantidad" name="cantidad" 
                                       value="1" min="1" max="10" style="width: 80px;" required>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-danger flex-shrink-0 py-2 px-4" type="submit">
                                    <i class="bi-cart-fill me-1"></i>
                                    Añadir al carrito
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include('../includes/footer.php'); ?>
    
    <!-- Cerrar conexiones -->
    <?php
    $stmt_producto->close();
    $stmt_variaciones->close();
    $conn->close();
    ?>

    <script>
    document.getElementById('variacion').addEventListener('change', function() {
        var imagen = this.options[this.selectedIndex].getAttribute('data-imagen');
        if(imagen) {
            document.getElementById('imagenPrincipal').src = imagen;
        }
    });
    </script>
</body>
</html>