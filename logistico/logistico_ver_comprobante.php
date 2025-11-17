<?php
session_start();
require_once("../config/conexion.php");

// Verificar rol logístico
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php");
    exit;
}

// Verificar ID de pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$id_pedido = intval($_GET['id']);

// Obtener datos del pedido y comprobante
$sql = "SELECT p.*, u.usuario, u.email, p.referencia_pago 
        FROM pedidos p 
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.id_pedido = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    die("Pedido no encontrado.");
}

// Verificar si existe comprobante
if (!$pedido['referencia_pago']) {
    die("Este pedido no tiene comprobante adjunto.");
}

// Construir ruta al comprobante
$ruta_base_comprobantes = "../comprobantes/";
$comprobante_path = $ruta_base_comprobantes . $pedido['referencia_pago'];

// Verificar que el archivo existe
if (!file_exists($comprobante_path)) {
    die("El archivo del comprobante no existe: " . htmlspecialchars($pedido['referencia_pago']));
}

// Obtener información del archivo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $comprobante_path);
finfo_close($finfo);

$file_size = filesize($comprobante_path);
$file_name = basename($comprobante_path);

// Configurar headers para mostrar el archivo en el navegador
header("Content-Type: $mime_type");
header("Content-Length: $file_size");
header("Content-Disposition: inline; filename=\"$file_name\"");
header("Cache-Control: public, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Limpiar buffer de salida y enviar archivo
ob_clean();
flush();
readfile($comprobante_path);
exit;
?>