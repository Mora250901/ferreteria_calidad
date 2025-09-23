-- =========================
-- ESQUEMA COMPLETO E-COMMERCE (Ferretería no industrial)
-- Compatible MySQL (InfinityFree / phpMyAdmin)
-- =========================

/* =====================
   TABLA: usuarios
   Roles: cliente, admin, logistico
   ===================== */
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    DNI VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('cliente','admin','logistico') NOT NULL DEFAULT 'cliente',
    telefono VARCHAR(20) NOT NULL,
    direccion TEXT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================
-- TABLA: proveedores
-- =====================
CREATE TABLE proveedores (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    nombre_proveedor VARCHAR(150) NOT NULL,
    telefono VARCHAR(30),
    email VARCHAR(150),
    direccion TEXT,
    ruc VARCHAR(20),
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================
-- TABLA: categorias
-- =====================
CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT
) ENGINE=InnoDB;

-- =====================
-- TABLA: productos (datos generales)
-- =====================
CREATE TABLE productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(200) NOT NULL,
    descripcion TEXT,
    sku VARCHAR(100) UNIQUE,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    id_categoria INT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imagen_principal VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================
-- RELACIÓN: producto_proveedor (M:N)
-- =====================
CREATE TABLE producto_proveedor (
    id_relacion INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    id_proveedor INT NOT NULL,
    precio_compra DECIMAL(10,2) NOT NULL,
    tiempo_entrega INT DEFAULT 1, -- días
    codigo_proveedor VARCHAR(100),
    UNIQUE (id_producto, id_proveedor),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- ATRIBUTOS DINÁMICOS
-- =====================
-- atributos: lista de posibles atributos (ej: Color, Diametro, Litros)
CREATE TABLE atributos (
    id_atributo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_atributo VARCHAR(150) NOT NULL,
    tipo_atributo ENUM('texto','numero','decimal','booleano','fecha') NOT NULL DEFAULT 'texto'
) ENGINE=InnoDB;

-- categorias_atributos: liga categoria <-> atributo
CREATE TABLE categorias_atributos (
    id_categoria INT NOT NULL,
    id_atributo INT NOT NULL,
    obligatorio BOOLEAN NOT NULL DEFAULT TRUE,
    orden INT DEFAULT 0,
    PRIMARY KEY (id_categoria, id_atributo),
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria) ON DELETE CASCADE,
    FOREIGN KEY (id_atributo) REFERENCES atributos(id_atributo) ON DELETE CASCADE
) ENGINE=InnoDB;

-- productos_atributos: valor real por producto
CREATE TABLE productos_atributos (
    id_producto INT NOT NULL,
    id_atributo INT NOT NULL,
    valor_texto VARCHAR(255),
    valor_numero INT,
    valor_decimal DECIMAL(18,6),
    valor_booleano BOOLEAN,
    valor_fecha DATE,
    PRIMARY KEY (id_producto, id_atributo),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_atributo) REFERENCES atributos(id_atributo) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLAS: variaciones (OPCIONAL, para opciones seleccionables en tienda)
-- =====================
CREATE TABLE variaciones (
    id_variacion INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    nombre_variacion VARCHAR(100) NOT NULL, -- Ej: "Tamaño", "Presentación"
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE variacion_opciones (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_variacion INT NOT NULL,
    valor_opcion VARCHAR(150) NOT NULL, -- Ej: "1 pulgada", "5 L", "Rojo"
    sku_opcion VARCHAR(100),
    stock INT NOT NULL DEFAULT 0,
    precio_extra DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (id_variacion) REFERENCES variaciones(id_variacion) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLAS: carrito persistente
-- =====================
CREATE TABLE carrito (
    id_carrito INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE carrito_items (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    id_carrito INT NOT NULL,
    id_producto INT NOT NULL,
    id_variacion_opcion INT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    FOREIGN KEY (id_carrito) REFERENCES carrito(id_carrito) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_variacion_opcion) REFERENCES variacion_opciones(id_opcion) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================
-- TABLAS: favoritos (wishlist)
-- =====================
CREATE TABLE favoritos (
    id_favorito INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_producto INT NOT NULL,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_usuario, id_producto),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLAS: reseñas
-- =====================
CREATE TABLE resenas (
    id_resena INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_producto INT NOT NULL,
    calificacion TINYINT NOT NULL,
    comentario TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLAS: historico_precios
-- =====================
CREATE TABLE historico_precios (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    precio_anterior DECIMAL(10,2) NOT NULL,
    precio_nuevo DECIMAL(10,2) NOT NULL,
    id_usuario_modifico INT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_modifico) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================
-- TABLA: alertas_stock
-- =====================
CREATE TABLE alertas_stock (
    id_alerta INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    stock_minimo INT NOT NULL,
    notificado BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- TABLAS: pedidos y detalle
-- =====================
CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    direccion_envio TEXT,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente','pagado','enviado','entregado','cancelado') DEFAULT 'pendiente',
    total DECIMAL(10,2) NOT NULL,
    metodo_envio VARCHAR(100),
    referencia_pago VARCHAR(100),
    estado_pago ENUM('pendiente', 'completado', 'rechazado') DEFAULT 'pendiente',
    comprobante_pago VARCHAR(255),
    metodo_pago ENUM('tarjeta', 'yape', 'plin', 'efectivo', 'transferencia'),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pedido_detalle (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    id_variacion_opcion INT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_variacion_opcion) REFERENCES variacion_opciones(id_opcion) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================
-- TABLAS: pagos y transacciones
-- =====================
CREATE TABLE pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    metodo_pago ENUM('tarjeta','transferencia','yape','plin','efectivo') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente','completado','fallido') DEFAULT 'pendiente',
    comprobante VARCHAR(255),
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transacciones (
    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_pago INT NULL,
    tipo ENUM('compra','reembolso','ajuste') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    detalle TEXT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================
-- TABLA: configuraciones_usuario (preferencias)
-- =====================
CREATE TABLE configuraciones_usuario (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tema ENUM('claro','oscuro') NOT NULL DEFAULT 'claro',
    notificaciones_email BOOLEAN NOT NULL DEFAULT 1,
    idioma VARCHAR(5) NOT NULL DEFAULT 'es',
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================
-- ÍNDICES ÚTILES (ejemplos)
-- =====================
CREATE INDEX idx_productos_categoria ON productos(id_categoria);
CREATE INDEX idx_productos_stock ON productos(stock);
CREATE INDEX idx_pedidos_usuario ON pedidos(id_usuario);
CREATE INDEX idx_proveedor_email ON proveedores(email);

-- =====================
-- TRIGGERS (básicos)
-- - before_insert pedido_detalle: calcula subtotal
-- - after_insert pedido_detalle: reduce stock (producto o variacion)
-- - after_update productos: registra cambio de precio en historico_precios
-- =====================

DELIMITER $$

-- 1) Calcular subtotal antes de insertar un detalle
CREATE TRIGGER trg_bd_pedido_detalle_before_insert
BEFORE INSERT ON pedido_detalle
FOR EACH ROW
BEGIN
    IF NEW.cantidad <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cantidad debe ser mayor a 0';
    END IF;
    SET NEW.subtotal = NEW.cantidad * NEW.precio_unitario;
END$$

-- 2) Actualizar stock después de insertar detalle (resta stock)
CREATE TRIGGER trg_ad_pedido_detalle_after_insert
AFTER INSERT ON pedido_detalle
FOR EACH ROW
BEGIN
    -- Si existe opción de variación, restar de esa opción
    IF NEW.id_variacion_opcion IS NOT NULL THEN
        UPDATE variacion_opciones
        SET stock = stock - NEW.cantidad
        WHERE id_opcion = NEW.id_variacion_opcion;
    ELSE
        UPDATE productos
        SET stock = stock - NEW.cantidad
        WHERE id_producto = NEW.id_producto;
    END IF;
END$$

-- 3) Registrar histórico de precios al cambiar precio en productos
CREATE TRIGGER trg_ad_productos_after_update
AFTER UPDATE ON productos
FOR EACH ROW
BEGIN
    IF OLD.precio <> NEW.precio THEN
        INSERT INTO historico_precios (id_producto, precio_anterior, precio_nuevo, id_usuario_modifico)
        VALUES (OLD.id_producto, OLD.precio, NEW.precio, NULL);
    END IF;
END$$

DELIMITER ;

-- =====================
-- EJEMPLOS: Inserciones iniciales (categorías, atributos, proveedor, producto)
-- =====================

-- Categorías ejemplo
INSERT INTO categorias (nombre_categoria, descripcion) VALUES
('Tornillos, Tuercas y Pernos', 'Sujetadores metálicos de diferentes tipos y medidas'),
('Clavos y Grapas', 'Clavos, grapas y accesorios para carpintería ligera'),
('Pinturas y Barnices', 'Pinturas interiores/exteriores, esmaltes, barnices y lacas'),
('Fontanería', 'Tuberías, codos, llaves, accesorios de plomería'),
('Electricidad', 'Cables, enchufes, interruptores, tomas de corriente y focos'),
('Herramientas Manuales', 'Martillos, destornilladores, alicates y llaves inglesas'),
('Cintas y Adhesivos', 'Cintas aislantes, adhesivos, pegamentos y silicona'),
('Seguridad y Protección', 'Guantes, gafas, mascarillas y cascos ligeros'),
('Ferretería General / Accesorios', 'Bisagras, perillas, cerraduras, soportes y ganchos'),
('Lubricantes y Aceites', 'Aceites para bisagras, lubricantes multifunción y grasa'),
('Abrasivos y Lijas', 'Lijas, discos de lijado y piedras de afilar'),
('Almacenamiento y Organización', 'Cajones, cajas de herramientas y estanterías ligeras');

-- =====================
-- ATRIBUTOS: insertar en tabla atributos
-- =====================
INSERT INTO atributos (nombre_atributo, tipo_atributo) VALUES
('Material','texto'),           -- Para tornillos, tuercas, pernos
('Diametro','texto'),           -- Para tornillos, tuercas, pernos, clavos
('Longitud','texto'),           -- Para tornillos, tuercas, pernos, clavos
('Rosca','texto'),              -- Para tornillos, pernos
('Color','texto'),              -- Para pinturas, barnices, adhesivos
('Acabado','texto'),            -- Para pinturas y barnices
('Rendimiento_m2','texto'),     -- Para pinturas
('Contenido_Litros','texto'),   -- Para pinturas
('Tipo','texto'),               -- Para clavos, grapas, herramientas, lubricantes
('Diametro_Tubo','texto'),      -- Para fontanería
('Longitud_Tubo','texto'),      -- Para fontanería
('Presion_Maxima','texto'),     -- Para fontanería
('Voltaje','texto'),            -- Para electricidad
('Amperaje','texto'),           -- Para electricidad
('Tipo_Enchufe','texto'),       -- Para electricidad
('Tipo_Herramienta','texto'),   -- Para herramientas manuales
('Longitud_Herramienta','texto'),-- Para herramientas manuales
('Tipo_Cinta','texto'),         -- Para cintas y adhesivos
('Resistencia_Aderencia','texto'),-- Para cintas y adhesivos
('Tipo_Proteccion','texto'),    -- Para seguridad
('Talla','texto'),              -- Para guantes o cascos
('Material_Seguridad','texto'), -- Para seguridad
('Tipo_Lubricante','texto'),    -- Para lubricantes y aceites
('Viscosidad','texto'),         -- Para lubricantes y aceites
('Tipo_Lija','texto'),           -- Para abrasivos y lijas
('Granulometria','texto'),      -- Para abrasivos y lijas
('Material_Almacenamiento','texto'), -- Para almacenamiento y organizadores
('Capacidad_Litros','texto');   -- Para almacenamiento y organizadores

-- =====================
-- ASOCIAR ATRIBUTOS A CATEGORÍAS
-- =====================

-- Tornillos, Tuercas y Pernos
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Tornillos, Tuercas y Pernos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Material'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Tornillos, Tuercas y Pernos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Diametro'), TRUE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Tornillos, Tuercas y Pernos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Longitud'), TRUE, 3),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Tornillos, Tuercas y Pernos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Rosca'), TRUE, 4);

-- Clavos y Grapas
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Clavos y Grapas'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Clavos y Grapas'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Diametro'), TRUE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Clavos y Grapas'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Longitud'), TRUE, 3);

-- Pinturas y Barnices
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Pinturas y Barnices'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Color'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Pinturas y Barnices'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Acabado'), TRUE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Pinturas y Barnices'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Rendimiento_m2'), FALSE, 3),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Pinturas y Barnices'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Contenido_Litros'), TRUE, 4);

-- Fontanería
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Fontanería'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Diametro_Tubo'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Fontanería'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Longitud_Tubo'), TRUE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Fontanería'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Presion_Maxima'), FALSE, 3),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Fontanería'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Material'), TRUE, 4);

-- Electricidad
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Electricidad'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Voltaje'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Electricidad'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Amperaje'), TRUE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Electricidad'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo_Enchufe'), FALSE, 3);

-- Herramientas Manuales
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Herramientas Manuales'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo_Herramienta'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Herramientas Manuales'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Longitud_Herramienta'), FALSE, 2);

-- Cintas y Adhesivos
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Cintas y Adhesivos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo_Cinta'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Cintas y Adhesivos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Resistencia_Aderencia'), TRUE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Cintas y Adhesivos'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Color'), FALSE, 3);

-- Seguridad y Protección
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Seguridad y Protección'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo_Proteccion'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Seguridad y Protección'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Talla'), FALSE, 2),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Seguridad y Protección'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Material_Seguridad'), FALSE, 3);

-- Lubricantes y Aceites
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Lubricantes y Aceites'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo_Lubricante'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Lubricantes y Aceites'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Viscosidad'), FALSE, 2);

-- Abrasivos y Lijas
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Abrasivos y Lijas'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Tipo_Lija'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Abrasivos y Lijas'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Granulometria'), TRUE, 2);

-- Almacenamiento y Organización
INSERT INTO categorias_atributos (id_categoria, id_atributo, obligatorio, orden)
VALUES
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Almacenamiento y Organización'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Material_Almacenamiento'), TRUE, 1),
((SELECT id_categoria FROM categorias WHERE nombre_categoria='Almacenamiento y Organización'), (SELECT id_atributo FROM atributos WHERE nombre_atributo='Capacidad_Litros'), FALSE, 2);
