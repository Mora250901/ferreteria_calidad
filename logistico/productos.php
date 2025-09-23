<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: login.php");
    exit;
}

function str($s){ return trim((string)$s); }
function b($v){ return (int)!!$v; }

/* FUNCIÓN: Obtener atributos por categoría */
function obtenerAtributosPorCategoria($conn, $id_categoria) {
    $atributos = [];
    $sql = "SELECT a.id_atributo, a.nombre_atributo, a.tipo_atributo, ca.obligatorio
            FROM categorias_atributos ca
            INNER JOIN atributos a ON a.id_atributo = ca.id_atributo
            WHERE ca.id_categoria = ?
            ORDER BY ca.orden ASC";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id_categoria);
    $st->execute();
    $result = $st->get_result();
    while($row = $result->fetch_assoc()) {
        $atributos[] = $row;
    }
    return $atributos;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    /* OBTENER atributos por categoría */
    if ($_POST['action'] === 'get_atributos_categoria' && isset($_POST['id_categoria'])) {
        $id_categoria = (int)$_POST['id_categoria'];
        $atributos = obtenerAtributosPorCategoria($conn, $id_categoria);
        echo json_encode(['status'=>'ok', 'atributos'=>$atributos]);
        exit;
    }

    /* LISTAR productos */
    if ($_POST['action'] === 'list') {
        $sql = "SELECT p.id_producto, p.nombre_producto AS nombre, p.descripcion, p.sku, p.precio AS precio_venta, 
                       p.stock, 5 AS stock_minimo, p.id_categoria, NULL AS id_proveedor_principal, 
                       p.imagen_principal, p.fecha_creacion, p.activo,
                       c.nombre_categoria AS categoria_nombre,
                       NULL AS proveedor_principal_nombre,
                       (SELECT GROUP_CONCAT(DISTINCT pv.nombre_proveedor ORDER BY pv.nombre_proveedor SEPARATOR ', ')
                        FROM producto_proveedor pp
                        INNER JOIN proveedores pv ON pv.id_proveedor = pp.id_proveedor
                        WHERE pp.id_producto = p.id_producto) AS proveedores_relacionados,
                       (SELECT COUNT(*) FROM productos_atributos pa WHERE pa.id_producto = p.id_producto) AS atributos_count,
                       (SELECT COUNT(*) FROM variaciones v WHERE v.id_producto = p.id_producto) AS variaciones_count
                FROM productos p
                LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                ORDER BY p.id_producto DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) while($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['status'=>'ok','data'=>$rows]);
        exit;
    }

    /* OBTENER un producto */
    if ($_POST['action'] === 'get' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "SELECT p.*, c.nombre_categoria AS categoria_nombre, NULL AS proveedor_principal_nombre
                FROM productos p
                LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.id_producto = ?";
        $st = $conn->prepare($sql);
        $st->bind_param("i", $id);
        $st->execute();
        $prod = $st->get_result()->fetch_assoc();
        if (!$prod) { echo json_encode(['status'=>'error','message'=>'No encontrado']); exit; }

        // Proveedores relacionados
        $prov = [];
        $sqlProv = "SELECT pp.id_relacion, pv.id_proveedor, pv.nombre_proveedor AS nombre, pv.telefono, pv.email,
                           pp.precio_compra, pp.tiempo_entrega, pp.codigo_proveedor
                    FROM producto_proveedor pp
                    INNER JOIN proveedores pv ON pv.id_proveedor = pp.id_proveedor
                    WHERE pp.id_producto = ?";
        $st2 = $conn->prepare($sqlProv);
        $st2->bind_param("i", $id);
        $st2->execute();
        $r2 = $st2->get_result();
        while($x = $r2->fetch_assoc()) $prov[] = $x;

        // Atributos - usando productos_atributos
        $atrib = [];
        $sqlA = "SELECT pa.id_atributo, a.nombre_atributo AS clave, 
                        COALESCE(pa.valor_texto, pa.valor_numero, pa.valor_decimal, pa.valor_booleano, pa.valor_fecha) AS valor
                 FROM productos_atributos pa
                 INNER JOIN atributos a ON a.id_atributo = pa.id_atributo
                 WHERE pa.id_producto = ?
                 ORDER BY pa.id_atributo ASC";
        $st3 = $conn->prepare($sqlA);
        $st3->bind_param("i", $id);
        $st3->execute();
        $r3 = $st3->get_result();
        while($x = $r3->fetch_assoc()) $atrib[] = $x;

        // Variaciones + opciones
        $vari = [];
        $sqlV = "SELECT id_variacion, nombre_variacion AS nombre, NULL AS sku, NULL AS precio_extra, NULL AS stock, NULL AS imagen
                 FROM variaciones
                 WHERE id_producto = ?
                 ORDER BY id_variacion ASC";
        $st4 = $conn->prepare($sqlV);
        $st4->bind_param("i", $id);
        $st4->execute();
        $r4 = $st4->get_result();
        $variaciones_ids = [];
        while($x = $r4->fetch_assoc()){
            $x['opciones'] = [];
            $vari[] = $x;
            $variaciones_ids[] = (int)$x['id_variacion'];
        }

        if (!empty($variaciones_ids)) {
            $in = implode(',', array_fill(0, count($variaciones_ids), '?'));
            $types = str_repeat('i', count($variaciones_ids));
            $sqlVO = "SELECT id_opcion, id_variacion, valor_opcion AS valor
                      FROM variacion_opciones
                      WHERE id_variacion IN ($in)
                      ORDER BY id_opcion ASC";
            $st5 = $conn->prepare($sqlVO);
            $st5->bind_param($types, ...$variaciones_ids);
            $st5->execute();
            $r5 = $st5->get_result();
            $byVar = [];
            while($o = $r5->fetch_assoc()){
                $vid = (int)$o['id_variacion'];
                if (!isset($byVar[$vid])) $byVar[$vid] = [];
                $byVar[$vid][] = $o;
            }
            foreach($vari as &$v){
                $vid = (int)$v['id_variacion'];
                if (isset($byVar[$vid])) $v['opciones'] = $byVar[$vid];
            }
            unset($v);
        }

        echo json_encode([
            'status'=>'ok',
            'data'=>[
                'producto'=>$prod,
                'proveedores'=>$prov,
                'atributos'=>$atrib,
                'variaciones'=>$vari
            ]
        ]);
        exit;
    }

    /* CREAR producto */
    if ($_POST['action'] === 'create') {
        $nombre   = str($_POST['nombre'] ?? '');
        $descripcion = str($_POST['descripcion'] ?? '');
        $precio = max(0, floatval($_POST['precio_venta'] ?? 0));
        $stock    = max(0, intval($_POST['stock'] ?? 0));
        $sku = str($_POST['sku'] ?? '');
        $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
        $activo = isset($_POST['activo']) ? b($_POST['activo']) : 1;

        if ($nombre === '' || !$id_categoria) {
            echo json_encode(['status'=>'error','message'=>'Nombre y Categoría son obligatorios']);
            exit;
        }

        // Manejar imagen subida
        $rutaDB = null;
        if (!empty($_FILES['imagen_principal']['name'])) {
            $nombreArchivo = uniqid() . "." . pathinfo($_FILES["imagen_principal"]["name"], PATHINFO_EXTENSION);
            $categoriaDir  = "assets/img/productos";   // ruta relativa para BD
            $targetDir     = dirname(__DIR__). "/$categoriaDir";                // ruta absoluta en servidor

            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $rutaDestino = $targetDir . "/" . $nombreArchivo;
            if (move_uploaded_file($_FILES["imagen_principal"]["tmp_name"], $rutaDestino)) {
                $rutaDB = "$categoriaDir/$nombreArchivo";
            }
        }

        // Insert con imagen
        $sql = "INSERT INTO productos (nombre_producto, descripcion, precio, stock, sku, id_categoria, activo, imagen_principal)
                VALUES (?,?,?,?,?,?,?,?)";
        $st = $conn->prepare($sql);
        $st->bind_param("ssdisiis", $nombre, $descripcion, $precio, $stock, $sku, $id_categoria, $activo, $rutaDB);
        $ok = $st->execute();
        
        $id_producto = $conn->insert_id;
        $message = 'Producto creado correctamente';
        
        // Guardar atributos si existen
        if ($ok && isset($_POST['atributos']) && is_array($_POST['atributos'])) {
            foreach ($_POST['atributos'] as $id_atributo => $valor) {
                if (!empty($valor)) {
                    // Determinar el tipo de valor basado en el tipo de atributo
                    $sqlTipo = "SELECT tipo_atributo FROM atributos WHERE id_atributo = ?";
                    $stTipo = $conn->prepare($sqlTipo);
                    $stTipo->bind_param("i", $id_atributo);
                    $stTipo->execute();
                    $tipoResult = $stTipo->get_result();
                    if ($tipoResult->num_rows > 0) {
                        $tipo = $tipoResult->fetch_assoc()['tipo_atributo'];
                        
                        $campoValor = "valor_" . $tipo;
                        $sqlAtributo = "INSERT INTO productos_atributos (id_producto, id_atributo, $campoValor) 
                                       VALUES (?, ?, ?)";
                        $stAtributo = $conn->prepare($sqlAtributo);
                        $stAtributo->bind_param("iis", $id_producto, $id_atributo, $valor);
                        $stAtributo->execute();
                    }
                }
            }
            $message .= ' con atributos';
        }
        
        echo json_encode(['status'=>$ok?'ok':'error','message'=>$ok?$message:'Error al crear el producto']);
        exit;
    }

    /* ACTUALIZAR producto */
    if ($_POST['action'] === 'update' && isset($_POST['id_producto'])) {
        $id_producto = (int)$_POST['id_producto'];
        $nombre      = str($_POST['nombre'] ?? '');
        $descripcion = str($_POST['descripcion'] ?? '');
        $precio      = max(0, floatval($_POST['precio_venta'] ?? 0));
        $stock       = max(0, intval($_POST['stock'] ?? 0));
        $sku         = str($_POST['sku'] ?? '');
        $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
        $activo      = isset($_POST['activo']) ? b($_POST['activo']) : 1;

        if ($nombre === '' || !$id_categoria) {
            echo json_encode(['status'=>'error','message'=>'Nombre y Categoría son obligatorios']);
            exit;
        }

        // Obtener ruta actual de la imagen
        $sqlImg = "SELECT imagen_principal FROM productos WHERE id_producto=?";
        $stImg = $conn->prepare($sqlImg);
        $stImg->bind_param("i", $id_producto);
        $stImg->execute();
        $resImg = $stImg->get_result();
        $rowImg = $resImg->fetch_assoc();
        $rutaActual = $rowImg['imagen_principal'] ?? null;

        $rutaDB = $rutaActual;

        // Si el usuario marcó eliminar imagen
        if (!empty($_POST['eliminar_imagen'])) {
            if ($rutaActual && file_exists(dirname(__DIR__) . "/" . $rutaActual)) {
                unlink(dirname(__DIR__) . "/" . $rutaActual);
            }
            $rutaDB = null;
        }

        // Si subió una nueva imagen
        if (!empty($_FILES['imagen_principal']['name'])) {
            // Borrar imagen anterior si existe
            if ($rutaActual && file_exists(dirname(__DIR__) . "/" . $rutaActual)) {
                unlink(dirname(__DIR__) . "/" . $rutaActual);
            }

            $nombreArchivo = uniqid() . "." . pathinfo($_FILES["imagen_principal"]["name"], PATHINFO_EXTENSION);
            $categoriaDir  = "assets/img/productos";   // ruta relativa para BD
            $targetDir     = dirname(__DIR__) . "/$categoriaDir";       // ruta absoluta en servidor

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $rutaDestino = $targetDir . "/" . $nombreArchivo;
            if (move_uploaded_file($_FILES["imagen_principal"]["tmp_name"], $rutaDestino)) {
                $rutaDB = "$categoriaDir/$nombreArchivo"; // lo que guardarás en la BD
            }
        }

        // Actualizar datos del producto
        $sql = "UPDATE productos 
                SET nombre_producto=?, descripcion=?, precio=?, stock=?, sku=?, id_categoria=?, activo=?, imagen_principal=?
                WHERE id_producto=?";
        $st = $conn->prepare($sql);
        $st->bind_param("ssdisiisi", $nombre, $descripcion, $precio, $stock, $sku, $id_categoria, $activo, $rutaDB, $id_producto);
        $ok = $st->execute();

        echo json_encode([
            'status' => $ok ? 'ok' : 'error',
            'message' => $ok ? 'Producto actualizado correctamente' : 'Error al actualizar'
        ]);
        exit;
    }

    /* ELIMINAR producto */
    if ($_POST['action']==='delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $st = $conn->prepare("DELETE FROM productos WHERE id_producto=?");
        $st->bind_param("i",$id);
        $ok = $st->execute();
        echo json_encode(['status'=>$ok?'ok':'error','message'=>$ok?'Producto eliminado':'No se pudo eliminar']);
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Acción inválida']);
    exit;
}

/* selects para categorías/proveedores */
$categorias=[]; $proveedores=[];
if($rc=$conn->query("SELECT id_categoria,nombre_categoria FROM categorias ORDER BY nombre_categoria ASC"))
    while($r=$rc->fetch_assoc()) $categorias[]=$r;
if($rp=$conn->query("SELECT id_proveedor,nombre_proveedor FROM proveedores WHERE activo=1 ORDER BY nombre_proveedor ASC"))
    while($r=$rp->fetch_assoc()) $proveedores[]=$r;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Productos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
body.claro { background:#f8f9fa; color:#212529; }
body.oscuro { background:#212529; color:#f8f9fa; }
.sidebar{ width:250px; position:fixed; top:0; bottom:0; left:0; padding-top:60px; z-index:1000; transition:.3s; }
.sidebar.claro{ background:#fff; border-right:1px solid #dee2e6; }
.sidebar.oscuro{ background:#343a40; border-right:1px solid #495057; }
.sidebar a{ display:block; padding:12px 20px; text-decoration:none; font-weight:500; }
.sidebar.claro a{ color:#333; } .sidebar.oscuro a{ color:#f8f9fa; }
.sidebar a:hover,.sidebar a.active{ background:rgba(13,110,253,.1); color:#0d6efd; }
.main-content{ margin-left:250px; padding:20px; }
@media (max-width:991.98px){ .sidebar{ transform:translateX(-100%);} .sidebar.show{ transform:translateX(0);} .main-content{ margin-left:0;} }
.toggle-btn{ position:fixed; top:10px; left:10px; z-index:1100;}
.card{ border-radius:12px; }
body.oscuro .card{ background:#2c3034; color:#f8f9fa; }
.table-img{ width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid rgba(0,0,0,.08);}
.badge-status{ font-size:.85rem; }
.codebox{ white-space:pre-wrap; background:rgba(0,0,0,.05); padding:12px; border-radius:8px; }
body.oscuro .codebox{ background:#1f2327; }
#modalCrear .modal-body {max-height: 70vh; overflow-y: auto;}
.modal-dialog-scrollable .modal-body {
  max-height: calc(100vh - 200px); /* ajusta el 200px según tu header/footer */
  overflow-y: auto;
}

</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<button class="btn btn-outline-primary d-lg-none toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('show')">
  <i class="fas fa-bars"></i>
</button>

<div class="sidebar <?= htmlspecialchars($tema_usuario) ?>" id="sidebar">
  <h5 class="px-3 mb-3 text-muted">Administración</h5>
  <a href="logistico_dashboard.php"><i class="fas fa-chart-line me-2"></i>Dashboard</a>
  <a href="perfil.php"><i class="fas fa-user me-2"></i>Perfil</a>
  <a href="configuraciones.php"><i class="fas fa-cog me-2"></i>Configuraciones</a>
  <a class="active" href="productos.php"><i class="fas fa-box me-2"></i>Productos</a>
  <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a>
</div>

<div class="main-content">
<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h2 class="mb-0">Productos</h2>
      <div class="text-muted">Gestiona tu catálogo</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="fa fa-plus me-2"></i>Nuevo producto
    </button>
  </div>

  <div id="alertas"></div>

  <div class="card p-3">
    <div class="table-responsive">
      <table id="tablaProductos" class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Imagen</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Proveedor principal</th>
            <th>Precio</th>
            <th>Stock</th>
            <th>Activo</th>
            <th>A/V</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody><!-- AJAX --></tbody>
      </table>
    </div>
  </div>

</div>
</div>

<!-- Modal CREAR -->
<div class="modal fade" id="modalCrear" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formCrear">
        <div class="modal-header">
          <h5 class="modal-title">Nuevo producto - <span id="pasoActual">Paso 1: Categoría</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="alertCrear"></div>
          
          <!-- Paso 1: Selección de categoría -->
          <div id="paso1">
            <div class="mb-4">
              <h6 class="mb-3">Selecciona la categoría del producto</h6>
              <select name="id_categoria" id="selectCategoria" class="form-select" required>
                <option value="">Selecciona una categoría...</option>
                <?php foreach($categorias as $c): ?>
                  <option value="<?= (int)$c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Los campos requeridos variarán según la categoría seleccionada.</div>
            </div>
            <div class="text-center">
              <button type="button" class="btn btn-primary" id="btnSiguiente">Siguiente <i class="fas fa-arrow-right ms-2"></i></button>
            </div>
          </div>

          <!-- Paso 2: Datos del producto y atributos -->
          <div id="paso2" style="display: none;">
            <div class="d-flex align-items-center mb-3">
              <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnAtras">
                <i class="fas fa-arrow-left"></i> Atrás
              </button>
              <span class="text-muted" id="categoriaSeleccionada"></span>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" placeholder="Código único">
              </div>
              <div class="col-md-4">
                <label class="form-label">Precio</label>
                <input type="number" step="0.01" name="precio_venta" class="form-control" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label">Stock</label>
                <input type="number" name="stock" class="form-control" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label">Activo</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" name="activo" checked>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">Imagen del producto</label>
                <input type="file" name="imagen_principal" class="form-control" accept="image/*">
              </div>
            </div>

            <!-- Sección de atributos dinámicos -->
            <div class="mt-4" id="seccionAtributos">
              <h6 class="mb-3">Atributos específicos</h6>
              <div id="camposAtributos">
                <div class="alert alert-info">
                  Selecciona una categoría primero para ver los atributos requeridos.
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer" id="footerPaso2" style="display: none;">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar producto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal EDITAR -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formEditar">
        <input type="hidden" name="id_producto" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title">Editar producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="alertEditar"></div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Imagen actual</label>
              <div id="previewImagen">
                <img src="<?= htmlspecialchars($producto['imagen_principal'] ?? 'assets/productos/no-image.png') ?>" 
                    alt="Imagen del producto" class="img-thumbnail mb-2" style="max-width: 150px;">
              </div>

              <label class="form-label">Cambiar imagen</label>
              <input type="file" name="imagen_nueva" class="form-control" accept="image/*">

              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="borrar_imagen" value="1">
                <label class="form-check-label">Eliminar imagen actual</label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio venta</label>
              <input type="number" step="0.01" name="precio_venta" id="edit_precio" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock</label>
              <input type="number" name="stock" id="edit_stock" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock mínimo</label>
              <input type="number" name="stock_minimo" id="edit_stock_min" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Categoría *</label>
              <select name="id_categoria" id="edit_categoria" class="form-select" required>
                <option value="">Selecciona...</option>
                <?php foreach($categorias as $c): ?>
                  <option value="<?= (int)$c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Proveedor principal</label>
              <select name="id_proveedor_principal" id="edit_proveedor" class="form-select">
                <option value="">(Ninguno)</option>
                <?php foreach($proveedores as $p): ?>
                  <option value="<?= (int)$p['id_proveedor'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <textarea name="descripcion" id="edit_desc" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="activo" id="edit_activo">
                <label class="form-check-label" for="edit_activo">Activo</label>
              </div>
            </div>
          </div>

          <hr class="my-4">
          <div>
            <h6 class="mb-2">Proveedores relacionados (solo lectura)</h6>
            <div id="box_proveedores" class="codebox small">(sin datos)</div>
            <div class="form-text">Se administran desde la tabla <code>producto_proveedor</code>.</div>
          </div>

          <hr class="my-4">
          <div class="row">
            <div class="col-md-6">
              <h6 class="mb-2">Atributos</h6>
              <div id="box_atributos" class="codebox small">(sin datos)</div>
            </div>
            <div class="col-md-6">
              <h6 class="mb-2">Variaciones</h6>
              <div id="box_variaciones" class="codebox small">(sin datos)</div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button class="btn btn-primary" type="submit">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal VER Atributos/Variaciones -->
<div class="modal fade" id="modalVerAV" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Atributos y Variaciones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <h6 class="mb-2">Atributos</h6>
        <div id="view_atributos" class="codebox small">(sin datos)</div>
        <h6 class="mt-3 mb-2">Variaciones</h6>
        <div id="view_variaciones" class="codebox small">(sin datos)</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variables globales
let atributosCategoria = [];
let categoriaActual = null;

const tabla = $('#tablaProductos').DataTable({
  language:{
    search:"Buscar:", lengthMenu:"Mostrar _MENU_ registros", info:"Mostrando _START_ a _END_ de _TOTAL_",
    infoEmpty:"Mostrando 0 a 0 de 0", emptyTable:"Sin datos", zeroRecords:"No se encontraron resultados",
    paginate:{ next:"Siguiente", previous:"Anterior" }
  },
  ajax:{ url:'productos.php', type:'POST', data:{action:'list'}, dataSrc:'data' },
  order:[[0,'desc']],
  columns:[
    { data:'id_producto' },
    { 
    data: null, 
    render: r => {
        let url = (r.imagen_principal && r.imagen_principal.trim() !== '') 
            ? `../${r.imagen_principal.trim()}` 
            : '../assets/img/productos/no-image.png';
        return `<img src="${url}" class="table-img" loading="lazy" onerror="this.src='../assets/img/productos/no-image.png'">`;
    } 
    },
    { data:'nombre' },
    { data:null, render:r=> r.categoria_nombre || '<span class="text-muted">—</span>' },
    { data:null, render:r=> r.proveedor_principal_nombre || '<span class="text-muted">—</span>' },
    { data:'precio_venta', render: v=> `S/ ${parseFloat(v||0).toFixed(2)}` },
    { data:'stock' },
    { data:'activo', render: v=> v==1 ? '<span class="badge bg-success badge-status">Activo</span>' : '<span class="badge bg-secondary badge-status">Inactivo</span>' },
    { data:null, orderable:false, render:r=>{
        const a = parseInt(r.atributos_count||0), v = parseInt(r.variaciones_count||0);
        return (a+v)>0 ? `<button class="btn btn-sm btn-outline-info btn-ver-av" data-id="${r.id_producto}">Ver (${a}/${v})</button>` : '<span class="text-muted">—</span>';
    }},
    { data:null, orderable:false, render:r=>{
        return `
          <div class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${r.id_producto}"><i class="fa fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${r.id_producto}"><i class="fa fa-trash"></i></button>
          </div>`;
    }}
  ]
});

function alerta(where, tipo, msg){
  document.getElementById(where).innerHTML =
    `<div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

// Manejar selección de categoría
$('#selectCategoria').change(function() {
    const idCategoria = $(this).val();
    if (idCategoria) {
        cargarAtributosCategoria(idCategoria);
    }
});

// Función para cargar atributos de la categoría
function cargarAtributosCategoria(idCategoria) {
    const fd = new FormData();
    fd.append('action', 'get_atributos_categoria');
    fd.append('id_categoria', idCategoria);
    
    fetch('productos.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
            if (j.status === 'ok') {
                atributosCategoria = j.atributos;
                mostrarCamposAtributos(j.atributos);
            }
        })
        .catch(() => console.error('Error al cargar atributos'));
}

// Mostrar campos de atributos
function mostrarCamposAtributos(atributos) {
    const container = $('#camposAtributos');
    container.empty();
    
    if (atributos.length === 0) {
        container.html('<div class="alert alert-info">Esta categoría no tiene atributos específicos.</div>');
        return;
    }
    
    atributos.forEach((atributo, index) => {
        const campoId = `atributo_${atributo.id_atributo}`;
        const requerido = atributo.obligatorio ? 'required' : '';
        
        let inputHtml = '';
        switch(atributo.tipo_atributo) {
            case 'numero':
            case 'decimal':
                inputHtml = `<input type="number" step="${atributo.tipo_atributo === 'decimal' ? '0.01' : '1'}" 
                              class="form-control" id="${campoId}" name="atributos[${atributo.id_atributo}]" ${requerido}>`;
                break;
            case 'booleano':
                inputHtml = `<select class="form-select" id="${campoId}" name="atributos[${atributo.id_atributo}]" ${requerido}>
                                <option value="">Seleccionar...</option>
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                             </select>`;
                break;
            case 'fecha':
                inputHtml = `<input type="date" class="form-control" id="${campoId}" 
                              name="atributos[${atributo.id_atributo}]" ${requerido}>`;
                break;
            default: // texto
                inputHtml = `<input type="text" class="form-control" id="${campoId}" 
                              name="atributos[${atributo.id_atributo}]" ${requerido}>`;
        }
        
        const campoHtml = `
            <div class="mb-3">
                <label for="${campoId}" class="form-label">${atributo.nombre_atributo} 
                    ${atributo.obligatorio ? '<span class="text-danger">*</span>' : ''}
                </label>
                ${inputHtml}
                <div class="form-text">Tipo: ${atributo.tipo_atributo}</div>
            </div>
        `;
        
        container.append(campoHtml);
    });
}

// Navegación entre pasos
$('#btnSiguiente').click(function() {
    const categoriaId = $('#selectCategoria').val();
    if (!categoriaId) {
        alert('Por favor selecciona una categoría primero');
        return;
    }
    
    categoriaActual = $('#selectCategoria option:selected').text();
    $('#categoriaSeleccionada').text('Categoría: ' + categoriaActual);
    
    // Mostrar paso 2, ocultar paso 1
    $('#paso1').hide();
    $('#paso2').show();
    $('#footerPaso2').show();
    $('#pasoActual').text('Paso 2: Datos del producto');
});

$('#btnAtras').click(function() {
    // Mostrar paso 1, ocultar paso 2
    $('#paso2').hide();
    $('#footerPaso2').hide();
    $('#paso1').show();
    $('#pasoActual').text('Paso 1: Categoría');
});

// Resetear modal cuando se cierra
$('#modalCrear').on('hidden.bs.modal', function () {
    $('#paso2').hide();
    $('#footerPaso2').hide();
    $('#paso1').show();
    $('#pasoActual').text('Paso 1: Categoría');
    $('#selectCategoria').val('');
    $('#camposAtributos').html('<div class="alert alert-info">Selecciona una categoría primero para ver los atributos requeridos.</div>');
    document.getElementById('formCrear').reset();
});

/* CREAR con sistema de dos pasos */
document.getElementById('formCrear').addEventListener('submit', function(e){
  e.preventDefault();
  
  // Validar atributos obligatorios
  const atributosObligatorios = atributosCategoria.filter(a => a.obligatorio);
  let errores = [];
  
  atributosObligatorios.forEach(atributo => {
    const campo = document.getElementById(`atributo_${atributo.id_atributo}`);
    if (campo && !campo.value.trim()) {
      errores.push(`${atributo.nombre_atributo} es obligatorio`);
    }
  });
  
  if (errores.length > 0) {
    alerta('alertCrear', 'danger', 'Errores: ' + errores.join(', '));
    return;
  }
  
  const fd = new FormData(this);
  fd.append('action','create');
  
  // Agregar todos los atributos al FormData
  $('[name^="atributos["]').each(function() {
    const name = $(this).attr('name');
    const value = $(this).val();
    fd.append(name, value);
  });
  
  // Normalizar checkbox activo
  if(!fd.has('activo')) fd.set('activo','0');

  fetch('productos.php',{ method:'POST', body:fd })
   .then(r=>r.json())
   .then(j=>{
     alerta('alertas', j.status==='ok'?'success':'danger', j.message||'');
     if(j.status==='ok'){
       this.reset();
       const modal = bootstrap.Modal.getInstance(document.getElementById('modalCrear'));
       modal.hide();
       tabla.ajax.reload(null,false);
       
       // Resetear el modal
       $('#paso2').hide();
       $('#footerPaso2').hide();
       $('#paso1').show();
       $('#pasoActual').text('Paso 1: Categoría');
       $('#selectCategoria').val('');
       $('#camposAtributos').html('<div class="alert alert-info">Selecciona una categoría primero para ver los atributos requeridos.</div>');
     }else{
       alerta('alertCrear','danger', j.message||'Error');
     }
   })
   .catch(()=> alerta('alertCrear','danger','Error de red'));
});

/* CARGAR datos para EDITAR */
$(document).on('click','.btn-editar', function(){
  const id = this.dataset.id;
  const fd = new FormData();
  fd.append('action','get');
  fd.append('id', id);
  fetch('productos.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      if(j.status==='ok'){
        const d = j.data.producto;
        $('#edit_id').val(d.id_producto);
        $('#edit_nombre').val(d.nombre_producto || d.nombre);
        $('#edit_precio').val(d.precio);
        $('#edit_stock').val(d.stock);
        $('#edit_sku').val(d.sku || '');
        $('#edit_categoria').val(d.id_categoria);
        $('#edit_desc').val(d.descripcion||'');
        $('#edit_activo').prop('checked', d.activo==1);

        // Proveedores relacionados (solo lectura)
        const prov = j.data.proveedores;
        if(prov.length){
          const lines = prov.map(p=> `• ${p.nombre} (código: ${p.codigo_proveedor||'—'}, compra: S/ ${parseFloat(p.precio_compra||0).toFixed(2)}, entrega: ${p.tiempo_entrega||0} día(s))`).join('\n');
          $('#box_proveedores').text(lines);
        }else{
          $('#box_proveedores').text('(sin datos)');
        }

        // Atributos
        const atr = j.data.atributos;
        if(atr.length){
          const lines = atr.map(a=> `• ${a.clave}: ${a.valor}`).join('\n');
          $('#box_atributos').text(lines);
        }else{
          $('#box_atributos').text('(sin datos)');
        }

        // Variaciones
        const va = j.data.variaciones;
        if(va.length){
          const lines = va.map(v=>{
            const opts = (v.opciones||[]).map(o=> `${o.valor}`).join(', ');
            return `• ${v.nombre}: ${opts||'sin opciones'}`;
          }).join('\n');
          $('#box_variaciones').text(lines);
        }else{
          $('#box_variaciones').text('(sin datos)');
        }

        new bootstrap.Modal(document.getElementById('modalEditar')).show();
      }
    });
});

/* GUARDAR edición */
document.getElementById('formEditar').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action','update');
  if(!fd.has('activo')) fd.set('activo','0');

  fetch('productos.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      alerta('alertas', j.status==='ok'?'success':'danger', j.message||'');
      if(j.status==='ok'){
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditar'));
        modal.hide();
        tabla.ajax.reload(null,false);
      }else{
        alerta('alertEditar','danger', j.message||'Error');
      }
    })
    .catch(()=> alerta('alertEditar','danger','Error de red'));
});

/* ELIMINAR */
$(document).on('click','.btn-eliminar', function(){
  const id = this.dataset.id;
  if(!confirm('¿Eliminar el producto #'+id+'?')) return;
  const fd = new FormData();
  fd.append('action','delete');
  fd.append('id', id);
  fetch('productos.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      alerta('alertas', j.status==='ok'?'success':'danger', j.message||'Error');
      if(j.status==='ok') tabla.ajax.reload(null,false);
    })
    .catch(()=> alerta('alertas','danger','Error de red'));
});

/* VER A/V desde la tabla */
$(document).on('click','.btn-ver-av', function(){
  const id = this.dataset.id;
  const fd = new FormData();
  fd.append('action','get');
  fd.append('id', id);
  fetch('productos.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      if(j.status==='ok'){
        const atr = j.data.atributos;
        const va  = j.data.variaciones;

        const atrTxt = atr.length
          ? atr.map(a=> `• ${a.clave}: ${a.valor}`).join('\n')
          : '(sin datos)';
        const vaTxt = va.length
          ? va.map(v=>{
              const opts = (v.opciones||[]).map(o=> `${o.valor}`).join(', ');
              return `• ${v.nombre}: ${opts||'sin opciones'}`;
            }).join('\n')
          : '(sin datos)';

        $('#view_atributos').text(atrTxt);
        $('#view_variaciones').text(vaTxt);
        new bootstrap.Modal(document.getElementById('modalVerAV')).show();
      }
    });
});
</script>
</body>
</html>