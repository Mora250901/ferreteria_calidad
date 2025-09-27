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

    /* LISTAR proveedores */
    if ($_POST['action'] === 'list') {
        $sql = "SELECT p.id_proveedor, p.nombre_proveedor AS nombre, p.telefono, p.email, 
                       p.direccion, p.ruc, p.activo, p.fecha_registro,
                       (SELECT COUNT(*) FROM producto_proveedor pp WHERE pp.id_proveedor = p.id_proveedor) AS productos_count
                FROM proveedores p
                ORDER BY p.id_proveedor DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) while($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['status'=>'ok','data'=>$rows]);
        exit;
    }

    /* LISTAR CATEGORIAS (para checkboxes) */
    if ($_POST['action'] === 'listCategorias') {
        $sql = "SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria ASC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) while($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['status'=>'ok','data'=>$rows]);
        exit;
    }

    /* OBTENER un proveedor (incluye categorias asignadas) */
    if ($_POST['action'] === 'get' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "SELECT p.* FROM proveedores p WHERE p.id_proveedor = ?";
        $st = $conn->prepare($sql);
        $st->bind_param("i", $id);
        $st->execute();
        $prov = $st->get_result()->fetch_assoc();
        if (!$prov) { echo json_encode(['status'=>'error','message'=>'No encontrado']); exit; }

        // Productos relacionados
        $productos = [];
        $sqlProd = "SELECT pp.id_relacion, pr.id_producto, pr.nombre_producto AS nombre, pr.sku,
                           pp.precio_compra, pp.tiempo_entrega, pp.codigo_proveedor
                    FROM producto_proveedor pp
                    INNER JOIN productos pr ON pr.id_producto = pp.id_producto
                    WHERE pp.id_proveedor = ?";
        $st2 = $conn->prepare($sqlProd);
        $st2->bind_param("i", $id);
        $st2->execute();
        $r2 = $st2->get_result();
        while($x = $r2->fetch_assoc()) $productos[] = $x;

        // Categorías asignadas al proveedor
        $cats = [];
        $sqlCats = "SELECT id_categoria FROM proveedor_categoria WHERE id_proveedor = ?";
        $st3 = $conn->prepare($sqlCats);
        $st3->bind_param("i", $id);
        $st3->execute();
        $r3 = $st3->get_result();
        while ($c = $r3->fetch_assoc()) $cats[] = (int)$c['id_categoria'];

        // incluir categorias en el objeto proveedor
        $prov['categorias'] = $cats;

        echo json_encode([
            'status'=>'ok',
            'data'=>[
                'proveedor'=>$prov,
                'productos'=>$productos
            ]
        ]);
        exit;
    }

    /* CREAR proveedor */
    if ($_POST['action'] === 'create') {
        $nombre   = str($_POST['nombre'] ?? '');
        $telefono = str($_POST['telefono'] ?? '');
        $email    = str($_POST['email'] ?? '');
        $direccion = str($_POST['direccion'] ?? '');
        $ruc      = str($_POST['ruc'] ?? '');
        $activo   = isset($_POST['activo']) ? b($_POST['activo']) : 1;
        $categorias = $_POST['categorias'] ?? []; // array de ids de categoria

        if ($nombre === '') {
            echo json_encode(['status'=>'error','message'=>'El nombre es obligatorio']);
            exit;
        }

        // Validar RUC único si se proporciona
        if (!empty($ruc)) {
            $sqlCheck = "SELECT id_proveedor FROM proveedores WHERE ruc = ?";
            $stCheck = $conn->prepare($sqlCheck);
            $stCheck->bind_param("s", $ruc);
            $stCheck->execute();
            if ($stCheck->get_result()->num_rows > 0) {
                echo json_encode(['status'=>'error','message'=>'El RUC ya está registrado']);
                exit;
            }
        }

        // Validar email único si se proporciona
        if (!empty($email)) {
            $sqlCheck = "SELECT id_proveedor FROM proveedores WHERE email = ?";
            $stCheck = $conn->prepare($sqlCheck);
            $stCheck->bind_param("s", $email);
            $stCheck->execute();
            if ($stCheck->get_result()->num_rows > 0) {
                echo json_encode(['status'=>'error','message'=>'El email ya está registrado']);
                exit;
            }
        }

        $sql = "INSERT INTO proveedores (nombre_proveedor, telefono, email, direccion, ruc, activo)
                VALUES (?, ?, ?, ?, ?, ?)";
        $st = $conn->prepare($sql);
        $st->bind_param("sssssi", $nombre, $telefono, $email, $direccion, $ruc, $activo);
        $ok = $st->execute();

        if ($ok) {
            $newId = $conn->insert_id;
            // insertar categorias seleccionadas (si hay)
            if (!empty($categorias) && is_array($categorias)) {
                $ins = $conn->prepare("INSERT INTO proveedor_categoria (id_proveedor, id_categoria) VALUES (?, ?)");
                foreach ($categorias as $catId) {
                    $cid = (int)$catId;
                    if ($cid <= 0) continue;
                    $ins->bind_param("ii", $newId, $cid);
                    @$ins->execute();
                }
            }
        }

        echo json_encode([
            'status'=>$ok?'ok':'error',
            'message'=>$ok?'Proveedor creado correctamente':'Error al crear el proveedor'
        ]);
        exit;
    }

    /* ACTUALIZAR proveedor */
    if ($_POST['action'] === 'update' && isset($_POST['id_proveedor'])) {
        $id_proveedor = (int)$_POST['id_proveedor'];
        $nombre       = str($_POST['nombre'] ?? '');
        $telefono     = str($_POST['telefono'] ?? '');
        $email        = str($_POST['email'] ?? '');
        $direccion    = str($_POST['direccion'] ?? '');
        $ruc          = str($_POST['ruc'] ?? '');
        $activo       = isset($_POST['activo']) ? b($_POST['activo']) : 1;
        $categorias   = $_POST['categorias'] ?? [];

        if ($nombre === '') {
            echo json_encode(['status'=>'error','message'=>'El nombre es obligatorio']);
            exit;
        }

        // Validar RUC único si se proporciona (excluyendo el actual)
        if (!empty($ruc)) {
            $sqlCheck = "SELECT id_proveedor FROM proveedores WHERE ruc = ? AND id_proveedor != ?";
            $stCheck = $conn->prepare($sqlCheck);
            $stCheck->bind_param("si", $ruc, $id_proveedor);
            $stCheck->execute();
            if ($stCheck->get_result()->num_rows > 0) {
                echo json_encode(['status'=>'error','message'=>'El RUC ya está registrado en otro proveedor']);
                exit;
            }
        }

        // Validar email único si se proporciona (excluyendo el actual)
        if (!empty($email)) {
            $sqlCheck = "SELECT id_proveedor FROM proveedores WHERE email = ? AND id_proveedor != ?";
            $stCheck = $conn->prepare($sqlCheck);
            $stCheck->bind_param("si", $email, $id_proveedor);
            $stCheck->execute();
            if ($stCheck->get_result()->num_rows > 0) {
                echo json_encode(['status'=>'error','message'=>'El email ya está registrado en otro proveedor']);
                exit;
            }
        }

        $sql = "UPDATE proveedores 
                SET nombre_proveedor=?, telefono=?, email=?, direccion=?, ruc=?, activo=?
                WHERE id_proveedor=?";
        $st = $conn->prepare($sql);
        $st->bind_param("sssssii", $nombre, $telefono, $email, $direccion, $ruc, $activo, $id_proveedor);
        $ok = $st->execute();

        if ($ok) {
            // Reemplazar categorias: borrar las antiguas e insertar las nuevas
            $del = $conn->prepare("DELETE FROM proveedor_categoria WHERE id_proveedor = ?");
            $del->bind_param("i", $id_proveedor);
            $del->execute();

            if (!empty($categorias) && is_array($categorias)) {
                $ins = $conn->prepare("INSERT INTO proveedor_categoria (id_proveedor, id_categoria) VALUES (?, ?)");
                foreach ($categorias as $catId) {
                    $cid = (int)$catId;
                    if ($cid <= 0) continue;
                    $ins->bind_param("ii", $id_proveedor, $cid);
                    @$ins->execute();
                }
            }
        }

        echo json_encode([
            'status' => $ok ? 'ok' : 'error',
            'message' => $ok ? 'Proveedor actualizado correctamente' : 'Error al actualizar'
        ]);
        exit;
    }

    /* ELIMINAR proveedor */
    if ($_POST['action']==='delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        // Verificar si tiene productos relacionados
        $sqlCheck = "SELECT COUNT(*) as total FROM producto_proveedor WHERE id_proveedor = ?";
        $stCheck = $conn->prepare($sqlCheck);
        $stCheck->bind_param("i", $id);
        $stCheck->execute();
        $result = $stCheck->get_result()->fetch_assoc();
        
        if ($result['total'] > 0) {
            echo json_encode(['status'=>'error','message'=>'No se puede eliminar: el proveedor tiene productos relacionados']);
            exit;
        }

        $st = $conn->prepare("DELETE FROM proveedores WHERE id_proveedor=?");
        $st->bind_param("i",$id);
        $ok = $st->execute();
        echo json_encode(['status'=>$ok?'ok':'error','message'=>$ok?'Proveedor eliminado':'No se pudo eliminar']);
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Acción inválida']);
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Proveedores</title>
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
.badge-status{ font-size:.85rem; }
.codebox{ white-space:pre-wrap; background:rgba(0,0,0,.05); padding:12px; border-radius:8px; }
body.oscuro .codebox{ background:#1f2327; }
.modal-dialog-scrollable .modal-body {
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<button class="btn btn-outline-primary d-lg-none toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('show')">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<?php include("../includes/sidevar.php"); ?>

<div class="main-content">
<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h2 class="mb-0">Proveedores</h2>
      <div class="text-muted">Gestiona tus proveedores</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
      <i class="fa fa-plus me-2"></i>Nuevo proveedor
    </button>
  </div>

  <div id="alertas"></div>

  <div class="card p-3">
    <div class="table-responsive">
      <table id="tablaProveedores" class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>RUC</th>
            <th>Productos</th>
            <th>Activo</th>
            <th>Registro</th>
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
          <h5 class="modal-title">Nuevo proveedor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="alertCrear"></div>
          
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">RUC</label>
              <input type="text" name="ruc" class="form-control" placeholder="Número de RUC">
            </div>
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input type="text" name="telefono" class="form-control" placeholder="Número de contacto">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com">
            </div>
            <div class="col-12">
              <label class="form-label">Dirección</label>
              <textarea name="direccion" class="form-control" rows="3" placeholder="Dirección completa"></textarea>
            </div>

            <!-- NUEVO: selector de categorias (checkboxes) -->
            <div class="col-12">
              <label class="form-label">Categorías que distribuye</label>
              <div id="crear_categorias_container" class="border p-2" style="max-height:200px; overflow-y:auto;">
                <!-- Checkboxes cargados por JS -->
                <div class="text-muted small">Cargando categorías...</div>
              </div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" checked>
                <label class="form-check-label">Activo</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar proveedor</button>
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
        <input type="hidden" name="id_proveedor" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title">Editar proveedor</h5>
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
              <label class="form-label">RUC</label>
              <input type="text" name="ruc" id="edit_ruc" class="form-control" placeholder="Número de RUC">
            </div>
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input type="text" name="telefono" id="edit_telefono" class="form-control" placeholder="Número de contacto">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="edit_email" class="form-control" placeholder="correo@ejemplo.com">
            </div>
            <div class="col-12">
              <label class="form-label">Dirección</label>
              <textarea name="direccion" id="edit_direccion" class="form-control" rows="3" placeholder="Dirección completa"></textarea>
            </div>

            <!-- NUEVO: categorias (editar) -->
            <div class="col-12">
              <label class="form-label">Categorías que distribuye</label>
              <div id="editar_categorias_container" class="border p-2" style="max-height:200px; overflow-y:auto;">
                <div class="text-muted small">Cargando categorías...</div>
              </div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" id="edit_activo">
                <label class="form-check-label">Activo</label>
              </div>
            </div>
          </div>

          <hr class="my-4">
          <div>
            <h6 class="mb-2">Productos relacionados (solo lectura)</h6>
            <div id="box_productos" class="codebox small">(sin datos)</div>
            <div class="form-text">Se administran desde la tabla <code>producto_proveedor</code>.</div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tabla = $('#tablaProveedores').DataTable({
  language:{
    search:"Buscar:", lengthMenu:"Mostrar _MENU_ registros", info:"Mostrando _START_ a _END_ de _TOTAL_",
    infoEmpty:"Mostrando 0 a 0 de 0", emptyTable:"Sin datos", zeroRecords:"No se encontraron resultados",
    paginate:{ next:"Siguiente", previous:"Anterior" }
  },
  ajax:{ url:'proveedores.php', type:'POST', data:{action:'list'}, dataSrc:'data' },
  order:[[0,'desc']],
  columns:[
    { data:'id_proveedor' },
    { data:'nombre' },
    { data:'telefono', render: v=> v || '<span class="text-muted">—</span>' },
    { data:'email', render: v=> v || '<span class="text-muted">—</span>' },
    { data:'ruc', render: v=> v || '<span class="text-muted">—</span>' },
    { data:'productos_count', render: v=> `<span class="badge bg-info">${v} productos</span>` },
    { data:'activo', render: v=> v==1 ? '<span class="badge bg-success badge-status">Activo</span>' : '<span class="badge bg-secondary badge-status">Inactivo</span>' },
    { data:'fecha_registro', render: v=> v ? new Date(v).toLocaleDateString() : '<span class="text-muted">—</span>' },
    { data:null, orderable:false, render:r=>{
        return `
          <div class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${r.id_proveedor}"><i class="fa fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${r.id_proveedor}"><i class="fa fa-trash"></i></button>
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

/* Función: cargar categorías y marcar seleccionadas (containerId = 'crear_categorias_container' o 'editar_categorias_container') */
function cargarCategorias(selected = [], containerId = 'crear_categorias_container'){
  const fd = new FormData();
  fd.append('action','listCategorias');
  fetch('proveedores.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      if(!j || j.status !== 'ok'){ document.getElementById(containerId).innerHTML = '<div class="text-danger small">Error cargando categorías</div>'; return; }
      const cats = j.data;
      let html = '';
      cats.forEach(c=>{
        const cid = c.id_categoria;
        const isChecked = selected.includes(cid) || selected.includes(String(cid)) ? 'checked' : '';
        html += `
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="categorias[]" value="${cid}" id="${containerId}_cat_${cid}" ${isChecked}>
            <label class="form-check-label" for="${containerId}_cat_${cid}">${c.nombre_categoria}</label>
          </div>`;
      });
      if(html === '') html = '<div class="text-muted small">No hay categorías</div>';
      document.getElementById(containerId).innerHTML = html;
    })
    .catch(()=> {
      document.getElementById(containerId).innerHTML = '<div class="text-danger small">Error de red al cargar categorías</div>';
    });
}

/* CREAR proveedor */
document.getElementById('formCrear').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action','create');
  if(!fd.has('activo')) fd.set('activo','0');

  fetch('proveedores.php',{ method:'POST', body:fd })
   .then(r=>r.json())
   .then(j=>{
     alerta('alertas', j.status==='ok'?'success':'danger', j.message||'');
     if(j.status==='ok'){
       this.reset();
       const modal = bootstrap.Modal.getInstance(document.getElementById('modalCrear'));
       modal.hide();
       tabla.ajax.reload(null,false);
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
  fetch('proveedores.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      if(j.status==='ok'){
        const d = j.data.proveedor;
        $('#edit_id').val(d.id_proveedor);
        $('#edit_nombre').val(d.nombre_proveedor || d.nombre);
        $('#edit_telefono').val(d.telefono || '');
        $('#edit_email').val(d.email || '');
        $('#edit_direccion').val(d.direccion || '');
        $('#edit_ruc').val(d.ruc || '');
        $('#edit_activo').prop('checked', d.activo==1);

        // Cargar y marcar categorias asignadas
        const categoriasAsignadas = d.categorias || [];
        cargarCategorias(categoriasAsignadas, 'editar_categorias_container');

        // Productos relacionados (solo lectura)
        const prod = j.data.productos;
        if(prod.length){
          const lines = prod.map(p=> `• ${p.nombre} (SKU: ${p.sku||'—'}, compra: S/ ${parseFloat(p.precio_compra||0).toFixed(2)}, entrega: ${p.tiempo_entrega||0} día(s))`).join('\n');
          $('#box_productos').text(lines);
        }else{
          $('#box_productos').text('(sin datos)');
        }

        new bootstrap.Modal(document.getElementById('modalEditar')).show();
      } else {
        alerta('alertas','danger', j.message || 'No se pudo obtener proveedor');
      }
    })
    .catch(()=> alerta('alertas','danger','Error de red al obtener proveedor'));
});

/* GUARDAR edición */
document.getElementById('formEditar').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action','update');
  if(!fd.has('activo')) fd.set('activo','0');

  fetch('proveedores.php',{ method:'POST', body:fd })
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
  if(!confirm('¿Eliminar el proveedor #'+id+'?')) return;
  const fd = new FormData();
  fd.append('action','delete');
  fd.append('id', id);
  fetch('proveedores.php',{ method:'POST', body:fd })
    .then(r=>r.json())
    .then(j=>{
      alerta('alertas', j.status==='ok'?'success':'danger', j.message||'Error');
      if(j.status==='ok') tabla.ajax.reload(null,false);
    })
    .catch(()=> alerta('alertas','danger','Error de red'));
});

// Resetear modal cuando se cierra
$('#modalCrear').on('hidden.bs.modal', function () {
  document.getElementById('formCrear').reset();
  document.getElementById('alertCrear').innerHTML = '';
});

// Cargar categorias al abrir modal Crear
document.getElementById('modalCrear').addEventListener('show.bs.modal', function () {
  cargarCategorias([], 'crear_categorias_container');
});
</script>
</body>
</html>