<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Traer categorías desde la BD
$sqlCategorias = "SELECT id_categoria, nombre_categoria FROM categorias";
$resultCategorias = $conn->query($sqlCategorias);
?>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <!-- LOGO -->
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-shop"></i> Mi Ferretería
        </a>

        <!-- Botón hamburguesa -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menú principal -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="index_home.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="compras.php">Mis Compras</a></li>
                <li class="nav-item"><a class="nav-link" href="carrito.php">Carrito</a></li>

                <!-- Dropdown dinámico de categorías -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="categoriasDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Categorías
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="categoriasDropdown">
                        <?php while ($cat = $resultCategorias->fetch_assoc()) { ?>
                            <li>
                                <a class="dropdown-item" href="productos.php?categoria=<?php echo $cat['id_categoria']; ?>">
                                    <?php echo $cat['nombre_categoria']; ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            </ul>

            <div class="ms-3">
                <?php if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true): ?>
                    <!-- Mostrar nombre del usuario -->
                    <div class="dropdown">
                        <button class="btn btn-outline-dark dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['usuario_data']['usuario']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-dark">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>