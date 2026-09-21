-- Seguranet — esquema mínimo para MySQL (IONOS Hosting Plus).
-- Se aplica una vez desde phpMyAdmin o por consola.
--
-- utf8mb4 y no utf8: sin eso, un nombre con emoji o ciertos caracteres rompe
-- la inserción, y utf8 de MySQL nunca fue UTF-8 completo.

SET NAMES utf8mb4;

-- Consultas del formulario de contacto.
CREATE TABLE IF NOT EXISTS consultas (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(120)    NOT NULL,
    correo      VARCHAR(190)    NOT NULL,
    telefono    VARCHAR(40)     NOT NULL DEFAULT '',
    ramo        VARCHAR(40)     NOT NULL DEFAULT '',
    mensaje     TEXT            NOT NULL,
    -- Zona del riesgo. Opcional en una consulta, imprescindible para cotizar:
    -- provincia y localidad vienen normalizadas de georef; cp se valida por
    -- estructura contra la provincia.
    provincia   CHAR(2)         NOT NULL DEFAULT '',
    localidad   VARCHAR(120)    NOT NULL DEFAULT '',
    cp          VARCHAR(8)      NOT NULL DEFAULT '',
    -- Para acreditar el consentimiento y frenar abuso. Se purga junto con el
    -- resto del registro según el plazo de la política de privacidad.
    origen_ip   VARCHAR(45)     NOT NULL DEFAULT '',
    agente      VARCHAR(255)    NOT NULL DEFAULT '',
    estado      ENUM('nueva','en_curso','respondida','descartada') NOT NULL DEFAULT 'nueva',
    -- Nota interna del panel: para quien atiende, no para el cliente.
    -- "Llamé y no atendió", "pide que lo llamen después de las 18". Sin esto
    -- el panel muestra estados pero no explica por qué una consulta lleva
    -- cuatro días en curso.
    nota        VARCHAR(1000)   NOT NULL DEFAULT '',
    creada_en   DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_consultas_creada (creada_en),
    KEY idx_consultas_estado (estado),
    KEY idx_consultas_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios del área de cuenta.
--
-- Dos decisiones que no son de estilo:
--
--  · clave_hash guarda el resultado de password_hash(), nunca la clave ni un
--    MD5/SHA1, que hoy se rompen con un diccionario.
--  · token_hash guarda el SHA-256 del token, no el token. El que viaja por
--    correo es el original; si alguien lee la base no puede confirmar cuentas
--    ajenas ni, sobre todo, restablecer contraseñas.
CREATE TABLE IF NOT EXISTS usuarios (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(120)    NOT NULL,
    correo              VARCHAR(190)    NOT NULL,
    clave_hash          VARCHAR(255)    NOT NULL,
    correo_confirmado   TINYINT(1)      NOT NULL DEFAULT 0,
    token_hash          CHAR(64)        DEFAULT NULL,
    token_proposito     ENUM('confirmacion','restablecer') DEFAULT NULL,
    token_expira        DATETIME        DEFAULT NULL,
    intentos_fallidos   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta     DATETIME        DEFAULT NULL,
    creado_en           DATETIME        NOT NULL,
    ultimo_acceso       DATETIME        DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_correo (correo),
    KEY idx_usuarios_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cotizaciones guardadas, para que el usuario las recupere desde su cuenta.
CREATE TABLE IF NOT EXISTS cotizaciones (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  BIGINT UNSIGNED DEFAULT NULL,
    ramo        VARCHAR(40)     NOT NULL,
    datos       JSON            NOT NULL,
    resultado   JSON            DEFAULT NULL,
    creada_en   DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cotizaciones_usuario (usuario_id),
    CONSTRAINT fk_cotizaciones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pólizas que sigue el usuario.
--
-- Acepta pólizas contratadas en otro lado, y es a propósito: si alguien puede
-- registrar acá la póliza que ya tiene, obtiene valor antes de comprarnos nada
-- —avisos de vencimiento, qué hacer ante un siniestro— y nosotros sabemos
-- cuándo vence para poder ofrecerle algo a tiempo. Esa es toda la estrategia de
-- renovación.
CREATE TABLE IF NOT EXISTS polizas (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id      BIGINT UNSIGNED NOT NULL,
    ramo            VARCHAR(40)     NOT NULL,
    aseguradora     VARCHAR(120)    NOT NULL DEFAULT '',
    numero          VARCHAR(60)     NOT NULL DEFAULT '',
    detalle         VARCHAR(190)    NOT NULL DEFAULT '',
    vigencia_desde  DATE            DEFAULT NULL,
    vigencia_hasta  DATE            NOT NULL,
    prima_mensual   DECIMAL(12,2)   DEFAULT NULL,
    -- 'seguranet' si la intermediamos nosotros; 'externa' si el usuario la
    -- trajo de otra parte.
    origen          ENUM('seguranet','externa') NOT NULL DEFAULT 'externa',
    estado          ENUM('vigente','renovada','dada_de_baja') NOT NULL DEFAULT 'vigente',
    notas           TEXT,
    creada_en       DATETIME        NOT NULL,
    actualizada_en  DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_polizas_usuario (usuario_id),
    -- El índice que importa: la consulta caliente es "qué vence pronto".
    KEY idx_polizas_vencimiento (estado, vigencia_hasta),
    CONSTRAINT fk_polizas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Avisos de vencimiento ya enviados.
--
-- La clave única es lo que evita el problema clásico de un cron: que se ejecute
-- dos veces y el cliente reciba el mismo correo repetido. Sin esto, un reintento
-- después de un error manda todo de nuevo.
CREATE TABLE IF NOT EXISTS avisos_vencimiento (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    poliza_id   BIGINT UNSIGNED NOT NULL,
    hito        SMALLINT UNSIGNED NOT NULL,  -- días de antelación: 60, 30, 15, 7, 1
    enviado_en  DATETIME        NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aviso (poliza_id, hito),
    CONSTRAINT fk_avisos_poliza FOREIGN KEY (poliza_id)
        REFERENCES polizas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
