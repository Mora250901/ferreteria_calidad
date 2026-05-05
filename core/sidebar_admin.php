<?php
// includes/sidebar_admin.php
?>
<style>
    :root {
        --primary-color: #dc3545;
        --success-color: #198754;
        --warning-color: #ffc107;
        --info-color:    #0dcaf0;
        --secondary-color: #6c757d;
        --dark-color:    #212529;
        --bg-light:      #f8f9fa;
    }
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
    }
    .sidebar {
        width: 250px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background-color: var(--dark-color);
        padding-top: 15px;
        box-shadow: 2px 0 10px rgba(0,0,0,.2);
        z-index: 1000;
        overflow-y: auto;
    }
    .sidebar a {
        color: #adb5bd;
        padding: 12px 15px;
        text-decoration: none;
        display: block;
        border-radius: 8px;
        margin: 5px 10px;
        transition: all .2s;
    }
    .sidebar a:hover, .sidebar .active-link {
        background-color: #495057;
        color: #fff;
        font-weight: bold;
    }
    .main-content {
        margin-left: 250px;
        padding: 30px;
    }
</style>

<div class="sidebar">
    <h4 class="text-white text-center mb-4 mt-2">ADMIN PANEL 📊</h4>
    <p class="text-secondary text-center small border-bottom border-secondary pb-3 mx-3">
        Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_data']['usuario'] ?? 'Admin'); ?>
    </p>
    <ul class="list-unstyled components">
        <li><a href="admin_dashboard_general.php"> ⚖ Dashboard General</a></li>
        <li><a href="perfil_admin.php"> 🔑 Mi Perfil</a></li>
        <hr class="text-white-50 my-2">
        <li><a href="admin_gestionar_admin.php"> 👑 Gestión Administradores</a></li>
        <li><a href="admin_dashboard.php"> 💼 Gestión Logístico</a></li>
        <li><a href="admin_registrar_logistico.php">📥 Agregar Nuevo Logístico</a></li>
        <li><a href="admin_marcas.php">🏷️ Marcas</a></li>
        <hr class="text-white-50 my-2">
        <li><a href="admin_proveedores.php">👨🏽‍🤝‍👨🏻 Proveedores</a></li>
        <li><a href="admin_reporte_ventas.php">📈 Reportes de Ventas</a></li>
        <li><a href="admin_banners.php">🖼️ Banners</a></li>
        <li><a href="admin_almacenes.php">🏭 Almacenes</a></li>
        <li class="mt-5">
            <a href="../public/logout.php" class="btn btn-danger btn-sm w-75 mx-auto d-block">
                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
            </a>
        </li>
    </ul>
</div>

<!-- CHATBOT -->
<link rel="stylesheet" href="../assets/chatbot/chatbot.css">
<script src="../assets/chatbot/chatbot.js"></script>