<style>
.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding-top: 20px;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,.08);
}
.sidebar.claro { background: #fff; border-right: 1px solid #dee2e6; }
.sidebar.oscuro { background: #343a40; border-right: 1px solid #495057; }
.sidebar h5 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
.sidebar a {
    display: block;
    padding: 10px 20px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    border-radius: 8px;
    margin: 2px 10px;
    transition: all .2s;
}
.sidebar.claro a { color: #495057; }
.sidebar.oscuro a { color: #adb5bd; }
.sidebar a:hover { background: rgba(13,110,253,.08); color: #0d6efd; }
.sidebar a.text-danger:hover { background: rgba(220,53,69,.08); color: #dc3545 !important; }
</style>
<div class="sidebar <?= htmlspecialchars($tema_usuario) ?>" id="sidebar">
    <h5 class="px-3 mb-3 text-muted">Administración</h5>
    <a href="logistico_dashboard.php"><i class="fas fa-chart-line me-2"></i>Dashboard</a>
    <a href="perfil.php"><i class="fas fa-user me-2"></i>Perfil</a>
    <a href="configuraciones.php"><i class="fas fa-cog me-2"></i>Configuraciones</a>
    <a href="productos.php"><i class="fas fa-box me-2"></i>Productos</a>
    <a href="proveedores.php"><i class="fas fa-truck me-2"></i>Proveedores</a>
    <a href="catalogo_inventario.php"><i class="fas fa-list me-2"></i>Catálogo / Inventario</a>
    
    <a href="../public/logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a>
    <!-- CHATBOT -->
    <link rel="stylesheet" href="../assets/chatbot/chatbot.css">
    <script src="../assets/chatbot/chatbot.js"></script>
</div>