<?php
/**
 * Funciones de apoyo. Cortas y sin estado: todo lo que necesitan llega por
 * parámetro.
 */

declare(strict_types=1);

/**
 * Escapa texto para HTML. Se usa en TODA salida: es la defensa contra XSS y
 * no tiene excepciones "porque este dato es nuestro".
 */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Dirección absoluta de una ruta interna. */
function url(string $ruta = '', array $config = []): string
{
    $base = rtrim($config['sitio']['url'] ?? '', '/');
    $ruta = '/' . ltrim($ruta, '/');
    return $base . ($ruta === '/' ? '/' : $ruta);
}

/** Token antifalsificación para los formularios (CSRF). */
function sn_token(): string
{
    if (empty($_SESSION['sn_token'])) {
        $_SESSION['sn_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['sn_token'];
}

/** Compara en tiempo constante: un == común filtra información por el tiempo. */
function sn_token_valido(?string $enviado): bool
{
    return is_string($enviado)
        && !empty($_SESSION['sn_token'])
        && hash_equals($_SESSION['sn_token'], $enviado);
}

/**
 * Conexión PDO a MySQL, perezosa y en modo excepción.
 * Devuelve null si falta configuración, para que el sitio siga en pie aunque
 * la base no esté: una web institucional que se cae entera porque no hay base
 * de datos es una web mal hecha.
 */
function sn_bd(array $config): ?PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $bd = $config['bd'];
    if ($bd['nombre'] === '' || $bd['usuario'] === '') {
        return null;
    }
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $bd['host'], $bd['nombre'], $bd['juego']);
        $pdo = new PDO($dsn, $bd['usuario'], $bd['clave'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Seguranet: no se pudo conectar a la base: ' . $e->getMessage());
        return null;
    }
}

/**
 * Envía un correo por SMTP del hosting.
 * Sin biblioteca externa: mail() alcanza para el volumen de un sitio de
 * captación, y en IONOS sale por su propio servidor.
 */
function sn_enviar_correo(array $config, string $para, string $asunto, string $cuerpoHtml): bool
{
    $desde = $config['correo']['desde'];
    $cabeceras = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Seguranet <' . $desde . '>',
        'Reply-To: ' . $config['sitio']['correo'],
        'X-Mailer: Seguranet',
    ];
    return @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpoHtml, implode("\r\n", $cabeceras));
}

/** Número de WhatsApp en formato de enlace. */
function sn_whatsapp(array $config, string $texto = ''): string
{
    $url = 'https://wa.me/' . preg_replace('/\D+/', '', $config['sitio']['whatsapp']);
    return $texto === '' ? $url : $url . '?text=' . rawurlencode($texto);
}

/**
 * Los iconos son SVG en línea: no se baja un paquete de fuentes de 70 KB para
 * mostrar ocho dibujos. Antes el sitio cargaba Font Awesome entero desde dos
 * CDN distintos.
 */
function sn_icono(string $nombre): string
{
    $trazos = [
        'auto'     => '<path d="M5 17h14M6.5 17a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm14 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM4 13l1.6-4.8A2 2 0 0 1 7.5 7h9a2 2 0 0 1 1.9 1.2L20 13v4H4v-4Z"/>',
        'hogar'    => '<path d="M3 10.5 12 3l9 7.5M5.5 9.5V20h13V9.5M9.5 20v-6h5v6"/>',
        'edificio' => '<path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16M15 21V9h3a2 2 0 0 1 2 2v10M4 21h17M8 7h3M8 11h3M8 15h3"/>',
        'vida'     => '<path d="M12 20s-7-4.35-7-9a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 4.65-7 9-7 9Z"/>',
        'salud'    => '<path d="M3 12h4l2-5 3 10 2.5-5H21"/>',
        'trabajo'  => '<path d="M3 8h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Zm6 0V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
        'comercio' => '<path d="M4 9h16l-1 11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 9Zm4 0V7a4 4 0 0 1 8 0v2"/>',
        'garantia' => '<path d="M12 3l7 3v5.5c0 4.2-2.9 7.9-7 9.5-4.1-1.6-7-5.3-7-9.5V6l7-3Zm-2.5 9 2 2 4-4.5"/>',
        'escudo'   => '<path d="M12 3l7 3v5.5c0 4.2-2.9 7.9-7 9.5-4.1-1.6-7-5.3-7-9.5V6l7-3Z"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'usuario'  => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/>',
    ];
    $d = $trazos[$nombre] ?? $trazos['escudo'];
    // width y height explícitos: un SVG sin dimensiones mide 300x150 por
    // defecto, y eso desbordaba la cabecera en pantallas chicas.
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
         . $d . '</svg>';
}
