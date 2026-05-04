<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php");
    exit;
}

function str($s){ return trim((string)$s); }
function b($v){ return (int)!!$v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'get_proveedores_categoria' && isset($_POST['id_categoria'])) {
        $id_categoria = (int)$_POST['id_categoria'];
        $sql = "SELECT p.id_proveedor, p.nombre_proveedor
                FROM proveedor_categoria pc
                INNER JOIN proveedores p ON pc.id_proveedor = p.id_proveedor
                WHERE pc.id_categoria = ? AND p.activo = 1
                ORDER BY p.nombre_proveedor";
        $st = $conn->prepare($sql);
        $st->bind_param("i", $id_categoria);
        $st->execute();
        $proveedores = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status'=>'ok', 'proveedores'=>$proveedores]);
        exit;
    }

    if ($_POST['action'] === 'get_productos_catalogo' && isset($_POST['id_proveedor']) && isset($_POST['id_categoria'])) {
        $id_proveedor = (int)$_POST['id_proveedor'];
        $id_categoria = (int)$_POST['id_categoria'];
        
        $sql = "SELECT cp.id_catalogo, cp.nombre_producto, cp.marca, cp.precio_compra
                FROM catalogo_proveedor cp
                WHERE cp.id_proveedor = ? AND cp.id_categoria = ? AND cp.activo = 1
                ORDER BY cp.nombre_producto";
        $st = $conn->prepare($sql);
        $st->bind_param("ii", $id_proveedor, $id_categoria);
        $st->execute();
        $productos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status'=>'ok', 'productos'=>$productos]);
        exit;
    }
    if ($_POST['action'] === 'get_atributos_producto' && isset($_POST['id_catalogo'])) {
        $id_catalogo = (int)$_POST['id_catalogo'];
        
        $sqlProducto = "SELECT cp.*, cp.id_categoria, cp.id_proveedor, c.nombre_categoria, a.nombre_atributo, 
                               GROUP_CONCAT(DISTINCT 
                                   CASE 
                                       WHEN cpa.valor_texto IS NOT NULL THEN cpa.valor_texto
                                       WHEN cpa.valor_numero IS NOT NULL THEN cpa.valor_numero
                                       WHEN cpa.valor_decimal IS NOT NULL THEN cpa.valor_decimal
                                       WHEN cpa.valor_booleano IS NOT NULL THEN cpa.valor_booleano
                                       WHEN cpa.valor_fecha IS NOT NULL THEN cpa.valor_fecha
                                   END
                               ) as valores
                        FROM catalogo_proveedor cp
                        INNER JOIN categorias c ON cp.id_categoria = c.id_categoria
                        LEFT JOIN catalogo_proveedor_atributos cpa ON cp.id_catalogo = cpa.id_catalogo
                        LEFT JOIN atributos a ON cpa.id_atributo = a.id_atributo
                        WHERE cp.id_catalogo = ?
                        GROUP BY a.id_atributo
                        ORDER BY a.nombre_atributo";
        $st = $conn->prepare($sqlProducto);
        $st->bind_param("i", $id_catalogo);
        $st->execute();
        $result = $st->get_result();
        
        $producto = null;
        $atributos = [];
        
        while($row = $result->fetch_assoc()) {
            if (!$producto) {
                $producto = [
                    'id_catalogo' => $row['id_catalogo'],
                    'nombre_producto' => $row['nombre_producto'],
                    'marca' => $row['marca'],
                    'precio_compra' => $row['precio_compra'],
                    'categoria' => $row['nombre_categoria'],
                    'id_categoria' => (int)$row['id_categoria'],
                    'id_proveedor' => (int)$row['id_proveedor']
                ];
            }
            
            if ($row['nombre_atributo'] && $row['valores']) {
                $atributos[] = [
                    'nombre' => $row['nombre_atributo'],
                    'valores' => explode(',', $row['valores'])
                ];
            }
        }
        $st->close();

        // Calcular stock TOTAL sumando ingresos de inventario
        $producto_stock = 0;
        if ($producto) {
            // 1) Stock de ingresos de inventario (nuevos ingresos)
            $sqlIngresos = "SELECT COALESCE(SUM(iid.cantidad), 0) AS stock_ingresos
                            FROM ingreso_inventario_detalle iid
                            WHERE iid.id_proveedor = ? 
                            AND iid.id_categoria = ? 
                            AND iid.nombre_producto = ?";
            $stIng = $conn->prepare($sqlIngresos);
            $stIng->bind_param("iis", $producto['id_proveedor'], $producto['id_categoria'], $producto['nombre_producto']);
            $stIng->execute();
            $resIng = $stIng->get_result();
            if ($rIng = $resIng->fetch_assoc()) {
                $producto_stock += (int)$rIng['stock_ingresos'];
            }
            $stIng->close();

            // 2) Verificar si ya existe como producto y sumar su stock
            $sqlExist = "SELECT p.id_producto, p.stock
                        FROM productos p 
                        WHERE p.nombre_producto = ? AND p.id_categoria = ? 
                        LIMIT 1";
            $stEx = $conn->prepare($sqlExist);
            $stEx->bind_param("si", $producto['nombre_producto'], $producto['id_categoria']);
            $stEx->execute();
            $resEx = $stEx->get_result();
            
            if ($rEx = $resEx->fetch_assoc()) {
                $producto['id_producto'] = (int)$rEx['id_producto'];
                $producto_stock += (int)$rEx['stock'];
            }
            $stEx->close();

            // 3) Sumar compras directas (si existen)
            if (isset($producto['id_producto'])) {
                $sqlCompras = "SELECT COALESCE(SUM(dc.cantidad), 0) AS stock_compras
                              FROM detalle_compras dc 
                              WHERE dc.id_producto = ?";
                $stComp = $conn->prepare($sqlCompras);
                $stComp->bind_param("i", $producto['id_producto']);
                $stComp->execute();
                $resComp = $stComp->get_result();
                if ($rComp = $resComp->fetch_assoc()) {
                    $producto_stock += (int)$rComp['stock_compras'];
                }
                $stComp->close();
            }
        }

        // añadir stock al response
        if ($producto) $producto['stock'] = $producto_stock;

        echo json_encode(['status'=>'ok', 'producto'=>$producto, 'atributos'=>$atributos]);
        exit;
    }
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

    if ($_POST['action'] === 'create') {
        $id_catalogo = (int)($_POST['id_catalogo'] ?? 0);
        $precio = max(0, floatval($_POST['precio_venta'] ?? 0));
        $stock = max(0, intval($_POST['stock'] ?? 0));
        $sku = str($_POST['sku'] ?? '');
        $activo = isset($_POST['activo']) ? b($_POST['activo']) : 1;
        
        $sqlCatalogo = "SELECT cp.nombre_producto, cp.marca, cp.id_categoria, cp.id_proveedor 
                       FROM catalogo_proveedor cp WHERE cp.id_catalogo = ?";
        $stCatalogo = $conn->prepare($sqlCatalogo);
        $stCatalogo->bind_param("i", $id_catalogo);
        $stCatalogo->execute();
        $productoCatalogo = $stCatalogo->get_result()->fetch_assoc();
        
        if (!$productoCatalogo) {
            echo json_encode(['status'=>'error','message'=>'Producto del catálogo no encontrado']);
            exit;
        }
        
        $nombre = $productoCatalogo['nombre_producto'];
        $marca = $productoCatalogo['marca'];
        $id_categoria = $productoCatalogo['id_categoria'];
        $id_proveedor = $productoCatalogo['id_proveedor'];
        
        $rutaDB = null;
        // La carpeta por producto se crea después del INSERT para tener el id_producto
        // imagen principal se guarda temporalmente y se mueve después
        $tmpImagen = null;
        if (!empty($_FILES['imagen_principal']['name'])) {
            $tmpImagen = $_FILES['imagen_principal'];
        }
         
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO productos (nombre_producto, descripcion, precio, stock, sku, id_categoria, activo, imagen_principal)
                    VALUES (?, '', ?, ?, ?, ?, ?, ?)";
            $st = $conn->prepare($sql);
            $st->bind_param("sdisiis", $nombre, $precio, $stock, $sku, $id_categoria, $activo, $rutaDB);
            $st->execute();
            $id_producto = $conn->insert_id;
            
            // Crear carpeta por producto
            $productoDir = "assets/img/productos/$id_producto";
            $targetDir   = dirname(__DIR__) . "/$productoDir";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            // Guardar imagen principal
            if ($tmpImagen && !empty($tmpImagen['name'])) {
                $ext         = pathinfo($tmpImagen['name'], PATHINFO_EXTENSION);
                $nombreArchivo = 'principal_' . time() . '.' . $ext;
                $rutaDestino = $targetDir . '/' . $nombreArchivo;
                if (move_uploaded_file($tmpImagen['tmp_name'], $rutaDestino)) {
                    $rutaDB = "$productoDir/$nombreArchivo";
                    $conn->query("UPDATE productos SET imagen_principal='$rutaDB' WHERE id_producto=$id_producto");
                }
            }

            // Guardar imágenes adicionales
            if (!empty($_FILES['imagenes_adicionales']['name'][0])) {
                $stImg = $conn->prepare("INSERT INTO producto_imagenes (id_producto, ruta, es_principal, orden) VALUES (?, ?, 0, ?)");
                foreach ($_FILES['imagenes_adicionales']['tmp_name'] as $i => $tmp) {
                    if (empty($_FILES['imagenes_adicionales']['name'][$i])) continue;
                    $ext  = pathinfo($_FILES['imagenes_adicionales']['name'][$i], PATHINFO_EXTENSION);
                    $nombre = 'foto_' . time() . '_' . $i . '.' . $ext;
                    $ruta = "$productoDir/$nombre";
                    if (move_uploaded_file($tmp, $targetDir . '/' . $nombre)) {
                        $stImg->bind_param('isi', $id_producto, $ruta, $i);
                        $stImg->execute();
                    }
                }
                $stImg->close();
            }

            $sqlProv = "INSERT INTO producto_proveedor (id_producto, id_proveedor, precio_compra) 
                       VALUES (?, ?, ?)";
            $stProv = $conn->prepare($sqlProv);
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $stProv->bind_param("iid", $id_producto, $id_proveedor, $precio_compra);
            $stProv->execute();

            // --- APLICAR INGRESOS PENDIENTES por id_catalogo (si existen) ---
            if (!empty($id_catalogo)) {
                $sqlPend = "SELECT id_pending, cantidad FROM catalogo_ingresos_pending WHERE id_catalogo = ? ORDER BY fecha ASC";
                $stp = $conn->prepare($sqlPend);
                $stp->bind_param("i", $id_catalogo);
                $stp->execute();
                $resPend = $stp->get_result();
                $pendings = $resPend->fetch_all(MYSQLI_ASSOC);
                $stp->close();

                if (!empty($pendings)) {
                    $insA = $conn->prepare("INSERT INTO ajustes_inventario (id_producto, cantidad, motivo, id_usuario) VALUES (?, ?, ?, ?)");
                    $motivo = "Ingreso pendiente aplicado desde catálogo (id_catalogo ".$id_catalogo.")";
                    $uid = (int)($u['id_usuario'] ?? 0);
                    foreach ($pendings as $pd) {
                        $cant = (int)$pd['cantidad'];
                        if ($cant === 0) continue;
                        $insA->bind_param("iisi", $id_producto, $cant, $motivo, $uid);
                        $insA->execute();
                        // El trigger trg_ajustes_inventario_insert incrementará stock
                        $conn->query("DELETE FROM catalogo_ingresos_pending WHERE id_pending = ".intval($pd['id_pending']));
                    }
                    $insA->close();
                }
            }
            // --- FIN APLICAR PENDIENTES ---

            if (isset($_POST['atributos']) && is_array($_POST['atributos'])) {
                foreach ($_POST['atributos'] as $nombre_atributo => $valor_seleccionado) {
                    $sqlAttr = "SELECT id_atributo FROM atributos WHERE nombre_atributo = ?";
                    $stAttr = $conn->prepare($sqlAttr);
                    $stAttr->bind_param("s", $nombre_atributo);
                    $stAttr->execute();
                    $attrResult = $stAttr->get_result();
                    
                    if ($attrResult->num_rows > 0) {
                        $id_atributo = $attrResult->fetch_assoc()['id_atributo'];
                        
                        $sqlInsertAttr = "INSERT INTO productos_atributos (id_producto, id_atributo, valor_texto) 
                                        VALUES (?, ?, ?)";
                        $stInsertAttr = $conn->prepare($sqlInsertAttr);
                        $stInsertAttr->bind_param("iis", $id_producto, $id_atributo, $valor_seleccionado);
                        $stInsertAttr->execute();
                    }
                }
            }
            
            $conn->commit();
            echo json_encode(['status'=>'ok','message'=>'Producto creado correctamente con selección de atributos']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status'=>'error','message'=>'Error al crear producto: ' . $e->getMessage()]);
        }
        exit;
    }
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

    if ($_POST['action'] === 'update' && isset($_POST['id_producto'])) {
        $id_producto = (int)$_POST['id_producto'];
        $nombre      = str($_POST['nombre'] ?? '');
        $descripcion = str($_POST['descripcion'] ?? '');
        $precio      = max(0, floatval($_POST['precio_venta'] ?? 0));
        // stock intentionally ignored: stock must be managed via compras/ajustes
        $sku         = str($_POST['sku'] ?? '');
        $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
        $activo      = isset($_POST['activo']) ? b($_POST['activo']) : 1;

        if ($nombre === '' || !$id_categoria) {
            echo json_encode(['status'=>'error','message'=>'Nombre y Categoría son obligatorios']);
            exit;
        }

        $sqlImg = "SELECT imagen_principal FROM productos WHERE id_producto=?";
        $stImg = $conn->prepare($sqlImg);
        $stImg->bind_param("i", $id_producto);
        $stImg->execute();
        $resImg = $stImg->get_result();
        $rowImg = $resImg->fetch_assoc();
        $rutaActual = $rowImg['imagen_principal'] ?? null;

        $rutaDB = $rutaActual;

        if (!empty($_POST['eliminar_imagen'])) {
            if ($rutaActual && file_exists(dirname(__DIR__) . "/" . $rutaActual)) {
                unlink(dirname(__DIR__) . "/" . $rutaActual);
            }
            $rutaDB = null;
        }

        if (!empty($_FILES['imagen_principal']['name'])) {
          if ($rutaActual && file_exists(dirname(__DIR__) . "/" . $rutaActual)) {
              unlink(dirname(__DIR__) . "/" . $rutaActual);
          }

          $productoDir = "assets/img/productos/$id_producto";
          $targetDir   = dirname(__DIR__) . "/$productoDir";
          if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

          $ext           = pathinfo($_FILES["imagen_principal"]["name"], PATHINFO_EXTENSION);
          $nombreArchivo = 'principal_' . time() . '.' . $ext;
          $rutaDestino   = $targetDir . '/' . $nombreArchivo;
          if (move_uploaded_file($_FILES["imagen_principal"]["tmp_name"], $rutaDestino)) {
              $rutaDB = "$productoDir/$nombreArchivo";
          }
      }

      // Guardar imágenes adicionales
      if (!empty($_FILES['imagenes_adicionales']['name'][0])) {
          $productoDir = "assets/img/productos/$id_producto";
          $targetDir   = dirname(__DIR__) . "/$productoDir";
          if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

          $stImg = $conn->prepare("INSERT INTO producto_imagenes (id_producto, ruta, es_principal, orden) VALUES (?, ?, 0, ?)");
          foreach ($_FILES['imagenes_adicionales']['tmp_name'] as $i => $tmp) {
              if (empty($_FILES['imagenes_adicionales']['name'][$i])) continue;
              $ext    = pathinfo($_FILES['imagenes_adicionales']['name'][$i], PATHINFO_EXTENSION);
              $nombre = 'foto_' . time() . '_' . $i . '.' . $ext;
              $ruta   = "$productoDir/$nombre";
              if (move_uploaded_file($tmp, $targetDir . '/' . $nombre)) {
                  $stImg->bind_param('isi', $id_producto, $ruta, $i);
                  $stImg->execute();
              }
          }
          $stImg->close();
      }

        $sql = "UPDATE productos 
                SET nombre_producto=?, descripcion=?, precio=?, sku=?, id_categoria=?, activo=?, imagen_principal=?
                WHERE id_producto=?";
        $st = $conn->prepare($sql);
        $st->bind_param("ssdsiisi", $nombre, $descripcion, $precio, $sku, $id_categoria, $activo, $rutaDB, $id_producto);
        $ok = $st->execute();

        echo json_encode([
            'status' => $ok ? 'ok' : 'error',
            'message' => $ok ? 'Producto actualizado correctamente (stock no modificado aquí)' : 'Error al actualizar'
        ]);
        exit;
    }

    if ($_POST['action']==='delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $st = $conn->prepare("DELETE FROM productos WHERE id_producto=?");
        $st->bind_param("i",$id);
        $ok = $st->execute();
        echo json_encode(['status'=>$ok?'ok':'error','message'=>$ok?'Producto eliminado':'No se pudo eliminar']);
        exit;
    }

    if ($_POST['action'] === 'eliminar_imagen_adicional' && isset($_POST['id_imagen'])) {
        $id_imagen = (int)$_POST['id_imagen'];
        $res = $conn->query("SELECT ruta FROM producto_imagenes WHERE id_imagen = $id_imagen");
        if ($row = $res->fetch_assoc()) {
            $ruta = dirname(__DIR__) . '/' . $row['ruta'];
            if (file_exists($ruta)) unlink($ruta);
            $conn->query("DELETE FROM producto_imagenes WHERE id_imagen = $id_imagen");
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Imagen no encontrada']);
        }
        exit;
    }
    
        if ($_POST['action'] === 'get_imagenes_adicionales' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $res = $conn->query("SELECT id_imagen, ruta FROM producto_imagenes WHERE id_producto = $id AND es_principal = 0 ORDER BY orden ASC");
        $imagenes = [];
        if ($res) while ($r = $res->fetch_assoc()) $imagenes[] = $r;
        echo json_encode(['status' => 'ok', 'imagenes' => $imagenes]);
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Acción inválida']);
    exit;
}

$categorias = [];
if($rc = $conn->query("SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria ASC")) {
    while($r = $rc->fetch_assoc()) $categorias[] = $r;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Productos - Nuevo Sistema</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">

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
.table-img{ width:48px; height:48px; object-fit:cover; border-radius:6px; }
.badge-status{ font-size:.85rem; }
.atributo-spinner { border: 2px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #f8f9fa; }
.atributo-spinner h6 { color: #495057; margin-bottom: 10px; }
.paso { display: none; }
.paso.activo { display: block; }
.paso-header { background: #e9ecef; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; }
body.oscuro .atributo-spinner { background: #2c3034; border-color: #495057; }
body.oscuro .paso-header { background: #343a40; }
.modal-dialog-scrollable .modal-body {
  max-height: 70vh;
  overflow-y: auto;
}
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<button class="btn btn-outline-primary d-lg-none toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('show')">
  <i class="fas fa-bars"></i>
</button>

<?php include("../core/sidevar.php"); ?>

<div class="main-content">
<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h2 class="mb-0">Productos - Nuevo Sistema</h2>
      <div class="text-muted">Selecciona productos del catálogo de proveedores</div>
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
            <th>Proveedor</th>
            <th>Precio</th>
            <th>Stock</th>
            <th>Activo</th>
            <th>Atributos</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div>
</div>
<div class="modal fade" id="modalCrear" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formCrear" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Nuevo Producto - <span id="pasoActual">Paso 1: Categoría</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="alertCrear"></div>
          
          <div class="paso activo" id="paso1">
            <div class="paso-header">
              <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Paso 1: Selecciona la categoría</h6>
            </div>
            <div class="mb-3">
              <label class="form-label">Categoría del producto *</label>
              <select name="id_categoria" id="selectCategoria" class="form-select" required>
                <option value="">Selecciona una categoría...</option>
                <?php foreach($categorias as $c): ?>
                  <option value="<?= (int)$c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="text-end">
              <button type="button" class="btn btn-primary" id="btnSiguiente1">Siguiente <i class="fas fa-arrow-right ms-2"></i></button>
            </div>
          </div>

          <div class="paso" id="paso2">
            <div class="paso-header">
              <h6 class="mb-0"><i class="fas fa-truck me-2"></i>Paso 2: Selecciona el proveedor</h6>
              <small id="categoriaSeleccionada" class="text-muted"></small>
            </div>
            <div class="mb-3">
              <label class="form-label">Proveedor *</label>
              <select name="id_proveedor" id="selectProveedor" class="form-select" required>
                <option value="">Cargando proveedores...</option>
              </select>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-secondary" id="btnAtras2"><i class="fas fa-arrow-left me-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary" id="btnSiguiente2">Siguiente <i class="fas fa-arrow-right ms-2"></i></button>
            </div>
          </div>

          <div class="paso" id="paso3">
            <div class="paso-header">
              <h6 class="mb-0"><i class="fas fa-box me-2"></i>Paso 3: Selecciona el producto</h6>
              <small id="proveedorSeleccionado" class="text-muted"></small>
            </div>
            <div class="mb-3">
              <label class="form-label">Producto del catálogo *</label>
              <select name="id_catalogo" id="selectProductoCatalogo" class="form-select" required>
                <option value="">Cargando productos...</option>
              </select>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-secondary" id="btnAtras3"><i class="fas fa-arrow-left me-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary" id="btnSiguiente3">Siguiente <i class="fas fa-arrow-right ms-2"></i></button>
            </div>
          </div>

          <div class="paso" id="paso4">
            <div class="paso-header">
              <h6 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Paso 4: Configura los atributos</h6>
              <small id="productoSeleccionado" class="text-muted"></small>
            </div>
            
            <div class="card mb-4" id="infoProducto" style="display: none;">
              <div class="card-body">
                <h6 id="nombreProducto"></h6>
                <p class="mb-1" id="marcaProducto"></p>
                <p class="mb-0 text-muted" id="precioCompraProducto"></p>
              </div>
            </div>

            <div id="spinnersAtributos">
              <div class="alert alert-info">
                Selecciona un producto para ver sus atributos disponibles.
              </div>
            </div>

            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-secondary" id="btnAtras4"><i class="fas fa-arrow-left me-2"></i> Atrás</button>
              <button type="button" class="btn btn-primary" id="btnSiguiente4">Siguiente <i class="fas fa-arrow-right ms-2"></i></button>
            </div>
          </div>
                    <div class="paso" id="paso5">
            <div class="paso-header">
              <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Paso 5: Información final</h6>
            </div>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Precio de venta *</label>
                <input type="number" step="0.01" name="precio_venta" class="form-control" required value="0">
              </div>

              <!-- Stock mostrado como readonly y enviado en hidden (no editable) -->
              <div class="col-md-6">
                <label class="form-label">Stock</label>
                <input type="text" id="display_stock" class="form-control" disabled value="0">
                <input type="hidden" name="stock" id="form_stock" value="0">
              </div>

              <div class="col-md-6">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" placeholder="Código único del producto">
              </div>
              <div class="col-md-6">
                  <label class="form-label">Imagen principal</label>
                  <input type="file" name="imagen_principal" class="form-control" accept="image/*">
              </div>
              <div class="col-12">
                  <label class="form-label">Fotos adicionales <small class="text-muted">(puedes seleccionar varias)</small></label>
                  <input type="file" name="imagenes_adicionales[]" class="form-control" accept="image/*" multiple>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="activo" checked>
                  <label class="form-check-label">Producto activo</label>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
              <button type="button" class="btn btn-secondary" id="btnAtras5"><i class="fas fa-arrow-left me-2"></i> Atrás</button>
              <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i> Guardar Producto</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar / Ver etc (sin cambios en stock editable en edición) -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formEditar" enctype="multipart/form-data">
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
                <label class="form-label">Imagen principal actual</label>
                <div id="previewImagen" class="mb-2"></div>
                <label class="form-label">Cambiar imagen principal</label>
                <input type="file" name="imagen_principal" class="form-control" accept="image/*">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="eliminar_imagen" value="1" id="edit_eliminar_imagen">
                    <label class="form-check-label" for="edit_eliminar_imagen">Eliminar imagen principal</label>
                </div>
            </div>
            <div class="col-12 mt-3">
                <label class="form-label">Fotos adicionales actuales</label>
                <div id="galeriaImagenes" class="d-flex flex-wrap gap-2 mb-2"></div>
                <label class="form-label">Agregar más fotos</label>
                <input type="file" name="imagenes_adicionales[]" class="form-control" accept="image/*" multiple>
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio venta *</label>
              <input type="number" step="0.01" name="precio_venta" id="edit_precio" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock</label>
              <input type="number" id="edit_stock" class="form-control" disabled>
            </div>
            <div class="col-md-4">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" id="edit_sku" class="form-control">
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
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <textarea name="descripcion" id="edit_desc" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" id="edit_activo">
                <label class="form-check-label">Activo</label>
              </div>
            </div>
          </div>

          <hr class="my-4">
          <div class="row">
            <div class="col-md-6">
              <h6 class="mb-2">Proveedores relacionados</h6>
              <div id="box_proveedores" class="codebox small">(cargando...)</div>
            </div>
            <div class="col-md-6">
              <h6 class="mb-2">Atributos del producto</h6>
              <div id="box_atributos" class="codebox small">(cargando...)</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalVerDetalles" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalles del Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-4 text-center">
            <img id="view_imagen" src="../assets/img/productos/no-image.png" class="img-fluid rounded" 
                 style="max-height: 200px;" onerror="this.src='../assets/img/productos/no-image.png'">
          </div>
          <div class="col-md-8">
            <h4 id="view_nombre"></h4>
            <p id="view_descripcion" class="text-muted"></p>
            <div class="row">
              <div class="col-6">
                <strong>Precio:</strong> <span id="view_precio" class="text-success"></span>
              </div>
              <div class="col-6">
                <strong>Stock:</strong> <span id="view_stock"></span>
              </div>
              <div class="col-6">
                <strong>SKU:</strong> <span id="view_sku" class="text-muted"></span>
              </div>
              <div class="col-6">
                <strong>Estado:</strong> <span id="view_estado" class="badge"></span>
              </div>
            </div>
          </div>
        </div>
        
        <hr>
        
        <div class="row">
          <div class="col-md-6">
            <h6>Atributos</h6>
            <div id="view_atributos_detalle" class="small"></div>
          </div>
          <div class="col-md-6">
            <h6>Proveedores</h6>
            <div id="view_proveedores_detalle" class="small"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
let productoSeleccionado = null;
let atributosDisponibles = [];

const tabla = $('#tablaProductos').DataTable({
  language: {
    search: "Buscar:", 
    lengthMenu: "Mostrar _MENU_ registros", 
    info: "Mostrando _START_ a _END_ de _TOTAL_",
    infoEmpty: "Mostrando 0 a 0 de 0", 
    emptyTable: "Sin datos", 
    zeroRecords: "No se encontraron resultados",
    paginate: { next: "Siguiente", previous: "Anterior" }
  },
  ajax: { 
    url: 'productos.php', 
    type: 'POST', 
    data: {action: 'list'}, 
    dataSrc: 'data' 
  },
  order: [[0, 'desc']],
  columns: [
    { data: 'id_producto' },
    { 
      data: null, 
      render: r => {
        let url = (r.imagen_principal && r.imagen_principal.trim() !== '') 
            ? `../${r.imagen_principal.trim()}` 
            : '../assets/img/productos/no-image.png';
        return `<img src="${url}" class="table-img" loading="lazy" onerror="this.src='../assets/img/productos/no-image.png'">`;
      } 
    },
    { data: 'nombre' },
    { data: null, render: r => r.categoria_nombre || '<span class="text-muted">—</span>' },
    { data: null, render: r => r.proveedores_relacionados || '<span class="text-muted">—</span>' },
    { data: 'precio_venta', render: v => `S/ ${parseFloat(v||0).toFixed(2)}` },
    { data: 'stock' },
    { data: 'activo', render: v => v==1 ? '<span class="badge bg-success badge-status">Activo</span>' : '<span class="badge bg-secondary badge-status">Inactivo</span>' },
    { data: null, render: r => {
        const a = parseInt(r.atributos_count||0);
        return a > 0 ? `<span class="badge bg-info">${a} atributos</span>` : '<span class="text-muted">—</span>';
    }},
    { data: null, orderable: false, render: r => {
        return `
          <div class="text-end">
            <button class="btn btn-sm btn-outline-info me-1 btn-ver" data-id="${r.id_producto}" title="Ver detalles">
              <i class="fa fa-eye"></i>
            </button>
            <button class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${r.id_producto}" title="Editar">
              <i class="fa fa-pen"></i>
            </button>
          </div>`;
    }}
  ]
});

function alerta(where, tipo, msg) {
  const container = document.getElementById(where);
  if (container) {
    container.innerHTML = `
      <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>`;
  }
}

function cambiarPaso(pasoActual, pasoSiguiente) {
  document.querySelectorAll('.paso').forEach(paso => paso.classList.remove('activo'));
  document.getElementById(pasoSiguiente).classList.add('activo');
  
  const titulos = {
    'paso1': 'Paso 1: Categoría',
    'paso2': 'Paso 2: Proveedor', 
    'paso3': 'Paso 3: Producto',
    'paso4': 'Paso 4: Atributos',
    'paso5': 'Paso 5: Información final'
  };
  document.getElementById('pasoActual').textContent = titulos[pasoSiguiente];
}

document.getElementById('btnSiguiente1').addEventListener('click', function() {
  const categoriaId = document.getElementById('selectCategoria').value;
  if (!categoriaId) {
    alerta('alertCrear', 'warning', 'Por favor selecciona una categoría');
    return;
  }
  
  const fd = new FormData();
  fd.append('action', 'get_proveedores_categoria');
  fd.append('id_categoria', categoriaId);
  
  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        const selectProveedor = document.getElementById('selectProveedor');
        selectProveedor.innerHTML = '<option value="">Selecciona un proveedor...</option>';
        
        j.proveedores.forEach(proveedor => {
          const option = document.createElement('option');
          option.value = proveedor.id_proveedor;
          option.textContent = proveedor.nombre_proveedor;
          selectProveedor.appendChild(option);
        });
        
        document.getElementById('categoriaSeleccionada').textContent = 
          'Categoría: ' + document.getElementById('selectCategoria').options[document.getElementById('selectCategoria').selectedIndex].text;
        
        cambiarPaso('paso1', 'paso2');
      } else {
        alerta('alertCrear', 'danger', 'Error al cargar proveedores');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alerta('alertCrear', 'danger', 'Error de conexión');
    });
});

// Paso 2: Selección de proveedor
document.getElementById('btnSiguiente2').addEventListener('click', function() {
  const proveedorId = document.getElementById('selectProveedor').value;
  if (!proveedorId) {
    alerta('alertCrear', 'warning', 'Por favor selecciona un proveedor');
    return;
  }

  const categoriaId = document.getElementById('selectCategoria').value;
  const fd = new FormData();
  fd.append('action', 'get_productos_catalogo');
  fd.append('id_proveedor', proveedorId);
  fd.append('id_categoria', categoriaId);

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        const selectProducto = document.getElementById('selectProductoCatalogo');
        selectProducto.innerHTML = '<option value="">Selecciona un producto...</option>';
        j.productos.forEach(prod => {
          const option = document.createElement('option');
          option.value = prod.id_catalogo;
          option.textContent = prod.nombre_producto + ' (' + prod.marca + ')';
          selectProducto.appendChild(option);
        });
        document.getElementById('proveedorSeleccionado').textContent =
          'Proveedor: ' + document.getElementById('selectProveedor').options[document.getElementById('selectProveedor').selectedIndex].text;
        cambiarPaso('paso2', 'paso3');
      } else {
        alerta('alertCrear', 'danger', 'Error al cargar productos');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alerta('alertCrear', 'danger', 'Error de conexión');
    });
});

document.getElementById('btnAtras2').addEventListener('click', function() {
  cambiarPaso('paso2', 'paso1');
});

// Paso 3: Selección de producto del catálogo
document.getElementById('btnSiguiente3').addEventListener('click', function() {
  const productoId = document.getElementById('selectProductoCatalogo').value;
  if (!productoId) {
    alerta('alertCrear', 'warning', 'Por favor selecciona un producto del catálogo');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'get_atributos_producto');
  fd.append('id_catalogo', productoId);

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        // Mostrar info del producto
        document.getElementById('infoProducto').style.display = 'block';
        document.getElementById('nombreProducto').textContent = j.producto.nombre_producto;
        document.getElementById('marcaProducto').textContent = 'Marca: ' + j.producto.marca;
        document.getElementById('precioCompraProducto').textContent = 'Precio compra: S/ ' + parseFloat(j.producto.precio_compra || 0).toFixed(2);

        // Mostrar stock (readonly) y asignar hidden
        const stockVal = (j.producto && typeof j.producto.stock !== 'undefined') ? parseInt(j.producto.stock, 10) : 0;
        const displayStock = document.getElementById('display_stock');
        const formStock = document.getElementById('form_stock');
        if (displayStock) displayStock.value = stockVal;
        if (formStock) formStock.value = stockVal;

        // Mostrar atributos
        const spinners = document.getElementById('spinnersAtributos');
        spinners.innerHTML = '';
        atributosDisponibles = j.atributos || [];
        if (atributosDisponibles.length === 0) {
          spinners.innerHTML = '<div class="alert alert-info">Este producto no tiene atributos configurables.</div>';
        } else {
          atributosDisponibles.forEach(attr => {
            const valores = attr.valores && Array.isArray(attr.valores) ? attr.valores.map(v => `<option value="${v}">${v}</option>`).join('') : '';
            spinners.innerHTML += `
              <div class="atributo-spinner mb-2">
                <h6>${attr.nombre}</h6>
                <select name="atributos[${attr.nombre}]" class="form-select">${valores}</select>
              </div>
            `;
          });
        }
        document.getElementById('productoSeleccionado').textContent =
          'Producto: ' + j.producto.nombre_producto;
        cambiarPaso('paso3', 'paso4');
      } else {
        alerta('alertCrear', 'danger', 'Error al cargar atributos');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alerta('alertCrear', 'danger', 'Error de conexión');
    });
});

document.getElementById('btnAtras3').addEventListener('click', function() {
  cambiarPaso('paso3', 'paso2');
});

// Paso 4: Configuración de atributos
document.getElementById('btnSiguiente4').addEventListener('click', function() {
  cambiarPaso('paso4', 'paso5');
});

document.getElementById('btnAtras4').addEventListener('click', function() {
  cambiarPaso('paso4', 'paso3');
});

// Paso 5: Información final
document.getElementById('btnAtras5').addEventListener('click', function() {
  cambiarPaso('paso5', 'paso4');
});

// Enviar formulario de creación
document.getElementById('formCrear').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);

  // Agregar atributos seleccionados
  atributosDisponibles.forEach(attr => {
    const select = form.querySelector(`[name="atributos[${attr.nombre}]"]`);
    if (select) {
      fd.append(`atributos[${attr.nombre}]`, select.value);
    }
  });

  fd.append('action', 'create');

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        alerta('alertas', 'success', j.message);
        $('#modalCrear').modal('hide');
        tabla.ajax.reload();
      } else {
        alerta('alertCrear', 'danger', j.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alerta('alertCrear', 'danger', 'Error de conexión');
    });
});

// Ver detalles del producto
$(document).on('click', '.btn-ver', function() {
  const id = $(this).data('id');
  const fd = new FormData();
  fd.append('action', 'get');
  fd.append('id', id);

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        const p = j.data.producto;
        $('#view_nombre').text(p.nombre_producto);
        $('#view_descripcion').text(p.descripcion || '');
        $('#view_precio').text('S/ ' + parseFloat(p.precio).toFixed(2));
        $('#view_stock').text(p.stock);
        $('#view_sku').text(p.sku || '');
        $('#view_estado').removeClass('bg-success bg-secondary').addClass(p.activo == 1 ? 'bg-success' : 'bg-secondary').text(p.activo == 1 ? 'Activo' : 'Inactivo');
        $('#view_imagen').attr('src', p.imagen_principal ? '../' + p.imagen_principal : '../assets/img/productos/no-image.png');

        // Atributos
        let attrs = '';
        (j.data.atributos || []).forEach(a => {
          attrs += `<div><strong>${a.clave}:</strong> ${a.valor}</div>`;
        });
        $('#view_atributos_detalle').html(attrs || '<span class="text-muted">—</span>');

        // Proveedores
        let provs = '';
        (j.data.proveedores || []).forEach(pr => {
          provs += `<div><strong>${pr.nombre}:</strong> S/ ${parseFloat(pr.precio_compra).toFixed(2)}</div>`;
        });
        $('#view_proveedores_detalle').html(provs || '<span class="text-muted">—</span>');

        $('#modalVerDetalles').modal('show');
      } else {
        alerta('alertas', 'danger', j.message);
      }
    });
});

// Editar producto
$(document).on('click', '.btn-editar', function() {
  const id = $(this).data('id');
  const fd = new FormData();
  fd.append('action', 'get');
  fd.append('id', id);

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        const p = j.data.producto;
        $('#edit_id').val(p.id_producto);
        $('#edit_nombre').val(p.nombre_producto);
        $('#edit_precio').val(p.precio);
        $('#edit_stock').val(p.stock);
        $('#edit_sku').val(p.sku);
        $('#edit_categoria').val(p.id_categoria);
        $('#edit_desc').val(p.descripcion);
        $('#edit_activo').prop('checked', p.activo == 1);

        // Imagen actual
        if (p.imagen_principal) {
          $('#previewImagen').html(`<img src="../${p.imagen_principal}" class="img-fluid rounded" style="max-height:100px;">`);
        } else {
          $('#previewImagen').html('<span class="text-muted">Sin imagen</span>');
        }

        // Cargar fotos adicionales
        const fd2 = new FormData();
        fd2.append('action', 'get_imagenes_adicionales');
        fd2.append('id', p.id_producto);

        fetch('productos.php', { method: 'POST', body: fd2 })
            .then(r => r.json())
            .then(j => {
                const galeria = document.getElementById('galeriaImagenes');
                galeria.innerHTML = '';
                if (j.status === 'ok' && j.imagenes.length > 0) {
                    j.imagenes.forEach(img => {
                        galeria.innerHTML += `
                            <div class="position-relative" id="imgDiv_${img.id_imagen}">
                                <img src="../${img.ruta}" style="height:70px;width:70px;object-fit:cover;border-radius:6px;">
                                <button type="button"
                                    class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 px-1 btn-eliminar-foto"
                                    data-id="${img.id_imagen}"
                                    style="font-size:10px;line-height:1.2;">✕</button>
                            </div>`;
                    });
                } else {
                    galeria.innerHTML = '<span class="text-muted small">Sin fotos adicionales</span>';
                }
            });

        // Proveedores
        let provs = '';
        (j.data.proveedores || []).forEach(pr => {
          provs += `<div><strong>${pr.nombre}:</strong> S/ ${parseFloat(pr.precio_compra).toFixed(2)}</div>`;
        });
        $('#box_proveedores').html(provs || '<span class="text-muted">—</span>');

        // Atributos
        let attrs = '';
        (j.data.atributos || []).forEach(a => {
          attrs += `<div><strong>${a.clave}:</strong> ${a.valor}</div>`;
        });
        $('#box_atributos').html(attrs || '<span class="text-muted">—</span>');

        $('#modalEditar').modal('show');
      } else {
        alerta('alertas', 'danger', j.message);
      }
    });
});

// Guardar edición
document.getElementById('formEditar').addEventListener('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'update');

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        alerta('alertas', 'success', j.message);
        $('#modalEditar').modal('hide');
        tabla.ajax.reload();
      } else {
        alerta('alertEditar', 'danger', j.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alerta('alertEditar', 'danger', 'Error de conexión');
    });
});

// Eliminar producto
$(document).on('click', '.btn-eliminar', function() {
  if (!confirm('¿Seguro que deseas eliminar este producto?')) return;
  const id = $(this).data('id');
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);

  fetch('productos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(j => {
      if (j.status === 'ok') {
        alerta('alertas', 'success', j.message);
        tabla.ajax.reload();
      } else {
        alerta('alertas', 'danger', j.message);
      }
    });
});

// Resetear modal al cerrar: limpiar display stock y hidden
$('#modalCrear').on('hidden.bs.modal', function () {
  document.getElementById('formCrear').reset();
  document.getElementById('infoProducto').style.display = 'none';
  document.getElementById('spinnersAtributos').innerHTML = '<div class="alert alert-info">Selecciona un producto para ver sus atributos disponibles.</div>';
  cambiarPaso('paso5', 'paso1');
  document.getElementById('alertCrear').innerHTML = '';

  // reset stock display/hidden
  if (document.getElementById('display_stock')) document.getElementById('display_stock').value = '0';
  if (document.getElementById('form_stock')) document.getElementById('form_stock').value = '0';
});

$(document).on('click', '.btn-eliminar-foto', function() {
    if (!confirm('¿Eliminar esta foto?')) return;
    const id = $(this).data('id');
    const fd = new FormData();
    fd.append('action', 'eliminar_imagen_adicional');
    fd.append('id_imagen', id);

    fetch('productos.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
            if (j.status === 'ok') {
                document.getElementById('imgDiv_' + id).remove();
            } else {
                alert('Error al eliminar la foto');
            }
        });
});
</script>
</body>
</html>