<?php
// ajax_ver_boleta_ingreso.php - CORREGIDO
session_start();
// Asegúrate de que la ruta de tu conexión sea correcta
include("../config/conexion.php");

// 1. Control de Acceso
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    http_response_code(403);
    echo "Acceso denegado.";
    exit();
}

$id_ingreso = $_GET['id_ingreso'] ?? null;

if (!is_numeric($id_ingreso)) {
    http_response_code(400);
    echo "ID de ingreso inválido.";
    exit();
}

// ==========================================================
// 2. CONSULTA DE DATOS DE LA BOLETA
// ==========================================================

// A) Datos principales de la Boleta (ingresos_inventario) y usuario
$sql_ingreso = "
    SELECT 
        ii.numero_factura, ii.fecha_ingreso, ii.fecha_emision, ii.metodo_pago, 
        ii.dias_credito, ii.fecha_pago, ii.subtotal, ii.igv, ii.total, ii.observaciones,
        u.usuario AS usuario_registro
    FROM ingresos_inventario ii
    JOIN usuarios u ON ii.id_usuario = u.id_usuario
    WHERE ii.id_ingreso = ?
";
$stmt_ingreso = $conn->prepare($sql_ingreso);
$stmt_ingreso->bind_param("i", $id_ingreso);
$stmt_ingreso->execute();
$ingreso_data = $stmt_ingreso->get_result()->fetch_assoc();
$stmt_ingreso->close();

if (!$ingreso_data) {
    http_response_code(404);
    echo "Boleta de ingreso no encontrada.";
    exit();
}

// B) Detalles de los Productos ingresados y el nombre del proveedor
$sql_detalle = "
    SELECT 
        iid.nombre_producto, iid.marca, iid.cantidad, iid.precio_compra, iid.subtotal_detalle,
        p.nombre_proveedor
    FROM ingreso_inventario_detalle iid
    JOIN proveedores p ON iid.id_proveedor = p.id_proveedor
    WHERE iid.id_ingreso = ?
";
$stmt_detalle = $conn->prepare($sql_detalle);
$stmt_detalle->bind_param("i", $id_ingreso);
$stmt_detalle->execute();
$detalle_result = $stmt_detalle->get_result();
$detalle_data = $detalle_result->fetch_all(MYSQLI_ASSOC);
$stmt_detalle->close();

$conn->close();

// ==========================================================
// 3. PROCESAMIENTO DE DATOS
// ==========================================================

// Calcular el IGV real
$igv_monto = $ingreso_data['total'] - $ingreso_data['subtotal'];

// Determinar el nombre del proveedor (tomamos el primero del detalle, ya que en esta tabla es consistente)
$nombre_proveedor = $detalle_data[0]['nombre_proveedor'] ?? 'Proveedor Desconocido'; 

// Determinar la fecha de vencimiento (si aplica)
$fecha_vencimiento = 'N/A';
if ($ingreso_data['metodo_pago'] === 'credito' && $ingreso_data['fecha_pago'] !== '0000-00-00' && $ingreso_data['fecha_pago'] !== NULL) {
    // Si la columna fecha_pago tiene un valor válido, lo usamos como fecha de vencimiento/pago
    $fecha_vencimiento = date('d/m/Y', strtotime($ingreso_data['fecha_pago']));
} else if ($ingreso_data['metodo_pago'] === 'credito' && $ingreso_data['dias_credito'] > 0) {
    // Si no tiene fecha de pago explícita, se calcula sumando los días crédito a la fecha de emisión
    $fecha_emision = new DateTime($ingreso_data['fecha_emision']);
    $fecha_emision->modify("+" . $ingreso_data['dias_credito'] . " days");
    $fecha_vencimiento = $fecha_emision->format('d/m/Y');
}


?>

<div class="modal-header bg-success text-white">
    <h5 class="modal-title" id="boletaModalLabel"><i class="fas fa-file-alt me-2"></i> Comprobante de Ingreso: <?php echo htmlspecialchars($ingreso_data['numero_factura']); ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    
    <div class="row mb-3">
        <div class="col-md-6">
            <p class="mb-1"><strong>Proveedor:</strong> <?php echo htmlspecialchars($nombre_proveedor); ?></p>
            <p class="mb-1"><strong>Factura/Boleta #:</strong> <?php echo htmlspecialchars($ingreso_data['numero_factura']); ?></p>
            <p class="mb-1"><strong>Método Pago:</strong> <span class="badge bg-<?php echo $ingreso_data['metodo_pago'] === 'contado' ? 'primary' : 'warning'; ?>"><?php echo strtoupper(htmlspecialchars($ingreso_data['metodo_pago'])); ?></span></p>
            <?php if ($ingreso_data['metodo_pago'] === 'credito'): ?>
                <p class="mb-1"><strong>Días Crédito:</strong> <?php echo htmlspecialchars($ingreso_data['dias_credito'] ?? 0) . ' días'; ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1"><strong>Fecha Emisión:</strong> <?php echo date('d/m/Y', strtotime($ingreso_data['fecha_emision'])); ?></p>
            <p class="mb-1"><strong>Fecha Registro (Ingreso):</strong> <?php echo date('d/m/Y', strtotime($ingreso_data['fecha_ingreso'])); ?></p>
            <p class="mb-1 text-danger"><strong>Fecha Vencimiento/Pago:</strong> <?php echo htmlspecialchars($fecha_vencimiento); ?></p>
            <p class="mb-1"><strong>Registrado por:</strong> <?php echo htmlspecialchars($ingreso_data['usuario_registro']); ?></p>
        </div>
    </div>

    <h6 class="mt-4 mb-2">Detalle de Productos Añadidos al Stock:</h6>
    <div class="table-responsive">
        <?php if (empty($detalle_data)): ?>
            <div class="alert alert-danger">No se encontraron productos en el detalle de esta boleta.</div>
        <?php else: ?>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Producto / Marca</th>
                        <th class="text-center">Cant. Añadida (Stock)</th>
                        <th class="text-end">Precio Compra Unit.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalle_data as $detalle): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($detalle['nombre_producto']); ?>
                            <span class="d-block text-muted small">Marca: <?php echo htmlspecialchars($detalle['marca'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="text-center bg-light fw-bold"><?php echo htmlspecialchars($detalle['cantidad']); ?></td>
                        <td class="text-end">S/ <?php echo number_format($detalle['precio_compra'], 2); ?></td>
                        <td class="text-end">S/ <?php echo number_format($detalle['subtotal_detalle'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="row justify-content-end mt-3">
        <div class="col-md-5">
            <table class="table table-sm table-borderless text-end">
                <tr>
                    <th>Subtotal (Base Imponible):</th>
                    <td>S/ <?php echo number_format($ingreso_data['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <th>IGV (<?php echo number_format($ingreso_data['igv'], 0); ?>%):</th>
                    <td>S/ <?php echo number_format($igv_monto, 2); ?></td>
                </tr>
                <tr class="table-dark fw-bold">
                    <th>TOTAL PAGADO:</th>
                    <td>S/ <?php echo number_format($ingreso_data['total'], 2); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php if (!empty($ingreso_data['observaciones'])): ?>
    <div class="alert alert-info mt-3 small">
        <strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($ingreso_data['observaciones'])); ?>
    </div>
    <?php endif; ?>

</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>