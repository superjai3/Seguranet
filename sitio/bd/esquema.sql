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
    -- Para acreditar el consentimiento y frenar abuso. Se purga junto con el
    -- resto del registro según el plazo de la política de privacidad.
    origen_ip   VARCHAR(45)     NOT NULL DEFAULT '',
    agente      VARCHAR(255)    NOT NULL DEFAULT '',
    estado      ENUM('nueva','en_curso','respondida','descartada') NOT NULL DEFAULT 'nueva',
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
