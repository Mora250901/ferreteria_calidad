<?php
session_start();
require_once("../config/conexion.php");

/* 1) Verifica login */
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    $_SESSION['redirigir_despues_login'] = "checkout.php";
    header("Location: login.php");
    exit;
}

/* 2) Verifica carrito */
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    header("Location: carrito.php");
    exit;
}

$usuario = $_SESSION['usuario_data'];
$total = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total += $item['precio'] * $item['cantidad'];
}

/* Dirección inicial */
$direccion_inicial = isset($usuario['direccion']) ? trim($usuario['direccion']) : "";

/* Si el usuario envió nueva dirección */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['direccion_envio'])) {
    $direccion_inicial = trim($_POST['direccion_envio']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Finalizar compra</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos -->
    <link rel="stylesheet" href="styles.css">
    <style>
        #map { width: 100%; height: 350px; border-radius: .5rem; }
    </style>
</head>
<body>

<div class="container my-5">
    <h2 class="mb-4">Finalizar compra</h2>

    <!-- Datos del usuario -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Datos del cliente</div>
        <div class="card-body">
            <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario['usuario']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?></p>
            <p>
                <strong>Dirección en tu perfil:</strong>
                <?php if ($direccion_inicial !== ""): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($direccion_inicial) ?>" target="_blank">
                        <?= htmlspecialchars($direccion_inicial) ?>
                    </a>
                <?php else: ?>
                    <em>No registrada</em>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Dirección editable + mapa -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Dirección de envío</div>
        <div class="card-body">
            <form id="formDireccion" method="post" action="checkout.php" class="mb-3">
                <label for="direccion_envio" class="form-label fw-bold">Escribe tu dirección o selecciónala en el mapa:</label>
                <input type="text" id="direccion_envio" name="direccion_envio" class="form-control"
                       value="<?= htmlspecialchars($direccion_inicial) ?>" placeholder="Calle, número, ciudad">
                <div class="form-text">Puedes escribir la dirección o mover el marcador en el mapa.</div>

                <div id="map" class="my-3"></div>

                <input type="hidden" id="lat" name="lat">
                <input type="hidden" id="lng" name="lng">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">Actualizar vista</button>
                    <a id="ver_en_maps" class="btn btn-outline-primary"
                       href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($direccion_inicial) ?>"
                       target="_blank">Ver en Google Maps</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen del pedido -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Resumen del pedido</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Precio</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($_SESSION['carrito'] as $item):
                    $subtotal = $item['precio'] * $item['cantidad']; ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nombre_producto']) ?></td>
                        <td class="text-end">S/ <?= number_format($item['precio'], 2) ?></td>
                        <td class="text-center"><?= $item['cantidad'] ?></td>
                        <td class="text-end">S/ <?= number_format($subtotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h4 class="text-end">Total: <span class="text-danger fw-bold">S/ <?= number_format($total, 2) ?></span></h4>
        </div>
    </div>

    <!-- Formulario de pago -->
    <form action="pago.php" method="post">
        <input type="hidden" name="total" value="<?= $total ?>">
        <input type="hidden" name="direccion_envio" id="direccion_envio_pago" value="<?= htmlspecialchars($direccion_inicial) ?>">
        <input type="hidden" name="lat" id="lat_pago">
        <input type="hidden" name="lng" id="lng_pago">

        <!-- Métodos de pago -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">Método de pago</div>
            <div class="card-body">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="metodo_pago" id="tarjeta" value="tarjeta" required>
                    <label class="form-check-label" for="tarjeta">Tarjeta de crédito/débito</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="metodo_pago" id="yape" value="yape">
                    <label class="form-check-label" for="yape">Yape</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="metodo_pago" id="plin" value="plin">
                    <label class="form-check-label" for="plin">Plin</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="metodo_pago" id="efectivo" value="efectivo">
                    <label class="form-check-label" for="efectivo">Efectivo contra entrega</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="metodo_pago" id="transferencia" value="transferencia">
                    <label class="form-check-label" for="transferencia">Transferencia bancaria</label>
                </div>
            </div>
        </div>

        <!-- Botón de pago -->
        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">Pagar ahora</button>
        </div>
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Google Maps -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBT8qbUWmzAozhGEUcX9m8xAB-DYV62cIs&libraries=places&callback=init" async defer></script>

<script>
let map, marker, geocoder, autocomplete;

function init() {
    const direccionInicial = <?= json_encode($direccion_inicial) ?>;
    const defaultCenter = { lat: -12.046373, lng: -77.042754 };

    const inputDireccion = document.getElementById('direccion_envio');
    const hiddenLat = document.getElementById('lat');
    const hiddenLng = document.getElementById('lng');
    const direccionPago = document.getElementById('direccion_envio_pago');
    const latPago = document.getElementById('lat_pago');
    const lngPago = document.getElementById('lng_pago');

    geocoder = new google.maps.Geocoder();

    map = new google.maps.Map(document.getElementById('map'), {
        center: defaultCenter,
        zoom: 14
    });

    marker = new google.maps.Marker({
        position: defaultCenter,
        map: map,
        draggable: true
    });

    autocomplete = new google.maps.places.Autocomplete(inputDireccion, {
        fields: ["formatted_address", "geometry"],
        types: ["geocode"]
    });

    autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();
        if (!place.geometry) return;
        const pos = {
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng()
        };
        map.setCenter(pos);
        map.setZoom(16);
        marker.setPosition(pos);
        actualizarDatos(pos.lat, pos.lng, place.formatted_address);
    });

    marker.addListener('dragend', () => {
        const pos = marker.getPosition();
        geocoder.geocode({ location: pos }, (results, status) => {
            if (status === "OK" && results[0]) {
                inputDireccion.value = results[0].formatted_address;
                actualizarDatos(pos.lat(), pos.lng(), results[0].formatted_address);
            }
        });
    });

    if (direccionInicial) {
        geocoder.geocode({ address: direccionInicial }, (results, status) => {
            if (status === "OK" && results[0]) {
                const pos = results[0].geometry.location;
                map.setCenter(pos);
                map.setZoom(16);
                marker.setPosition(pos);
                actualizarDatos(pos.lat(), pos.lng(), direccionInicial);
            }
        });
    }
}

function actualizarDatos(lat, lng, direccion) {
    document.getElementById('lat').value = lat.toFixed(6);
    document.getElementById('lng').value = lng.toFixed(6);
    document.getElementById('lat_pago').value = lat.toFixed(6);
    document.getElementById('lng_pago').value = lng.toFixed(6);
    document.getElementById('direccion_envio_pago').value = direccion;
    document.getElementById('ver_en_maps').href =
        "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(direccion);
}
</script>
<link rel="stylesheet" href="../assets/chatbot/chatbot.css">
<script src="../assets/chatbot/chatbot.js"></script>
</body>
</html>