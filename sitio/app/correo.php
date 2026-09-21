<?php
/**
 * Correo saliente.
 *
 * Hasta acá el sitio mandaba todo con mail(), que en un hosting compartido es
 * la forma más rápida de que un correo termine en spam o directamente no
 * salga: sin autenticación SMTP el mensaje sale con el dominio del servidor y
 * no con el nuestro, así que no lo respaldan ni SPF ni DKIM. Y como mail()
 * devuelve true apenas encola, un fallo posterior no se entera nadie.
 *
 * El problema era doble: config.php ya pedía SN_SMTP_HOST, SN_SMTP_USUARIO y
 * SN_SMTP_CLAVE desde el primer día, pero ninguna línea del código los leía.
 * Acá se usan.
 *
 * Se habla SMTP directo con fsockopen en vez de sumar PHPMailer: son doscientas
 * líneas contra una dependencia con Composer, que en el Hosting Plus de IONOS
 * hay que subir a mano en cada despliegue.
 *
 * Dos reglas que no se negocian:
 *
 *  1. La clave NUNCA viaja en claro. Si el servidor no ofrece cifrado, el envío
 *     se aborta antes de mandar AUTH. Es preferible un correo que no sale a una
 *     contraseña de buzón regalada en la red.
 *  2. Ninguna dirección ni asunto con salto de línea llega a las cabeceras. Es
 *     la inyección de cabeceras de siempre: un "\r\nBcc:" en el correo de
 *     registro convertiría el formulario en un relay para spam.
 */

declare(strict_types=1);

/** Segundos de espera, tanto para conectar como para cada respuesta. */
const SN_SMTP_ESPERA = 15;

/**
 * ¿Es una dirección usable en una cabecera?
 *
 * filter_var() sola no alcanza: valida el formato, pero acá además importa que
 * no traiga CR ni LF, que es lo que permite inyectar cabeceras.
 */
function sn_correo_valida(string $direccion): bool
{
    if ($direccion === '' || strpbrk($direccion, "\r\n") !== false) {
        return false;
    }
    return (bool) filter_var($direccion, FILTER_VALIDATE_EMAIL);
}

/**
 * Codifica un texto para una cabecera (RFC 2047).
 *
 * El asunto lleva tildes y "·", así que hay que codificarlo o llega roto. Se
 * parte en trozos de 45 bytes porque base64 los infla un tercio y la línea
 * codificada no puede pasar de 75 caracteres; además se corta respetando los
 * caracteres multibyte, para no partir una "ñ" al medio.
 */
function sn_correo_cabecera(string $texto): string
{
    $texto = str_replace(["\r", "\n"], ' ', $texto);
    if (preg_match('/^[\x20-\x7E]*$/', $texto) === 1) {
        return $texto;                       // ASCII puro: no hace falta nada
    }
    $lineas = [];
    foreach (mb_str_split($texto, 15, 'UTF-8') as $trozo) {
        $ultima = count($lineas) - 1;
        if ($ultima >= 0 && strlen($lineas[$ultima] . $trozo) <= 45) {
            $lineas[$ultima] .= $trozo;
        } else {
            $lineas[] = $trozo;
        }
    }
    foreach ($lineas as &$linea) {
        $linea = '=?UTF-8?B?' . base64_encode($linea) . '?=';
    }
    return implode("\r\n ", $lineas);        // continuación plegada con un espacio
}

/**
 * Versión en texto plano del cuerpo HTML.
 *
 * No es adorno: un correo que sólo trae HTML puntúa peor en los filtros de
 * spam, y hay gente que lee el correo en texto. Los enlaces se conservan entre
 * paréntesis, porque un "hacé clic acá" sin URL es inservible en texto plano y
 * justamente los correos de confirmación son todos un enlace.
 */
function sn_correo_a_texto(string $html): string
{
    $texto = preg_replace('/<a\b[^>]*href\s*=\s*"([^"]*)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
    $texto = preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/li|\/tr)\b[^>]*>/i', "\n", (string) $texto);
    $texto = preg_replace('/<(\/?(p|div|h[1-6]|ul|ol|table))\b[^>]*>/i', "\n", (string) $texto);
    $texto = preg_replace('/<li\b[^>]*>/i', '- ', (string) $texto);
    $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/[ \t]+/', ' ', $texto);
    $texto = preg_replace('/ ?\n ?/', "\n", (string) $texto);
    $texto = preg_replace('/\n{3,}/', "\n\n", (string) $texto);
    return trim((string) $texto);
}

/**
 * Arma el mensaje completo: cabeceras y cuerpo multiparte.
 *
 * Las dos partes van en base64. Es menos legible que quoted-printable si uno
 * espía el tráfico, pero evita de raíz los dos problemas del transporte SMTP:
 * líneas de más de 998 octetos y líneas que empiezan con un punto.
 *
 * @return array{cabeceras: array<string, string>, cuerpo: string}
 */
function sn_correo_armar(array $config, string $para, string $asunto, string $html): array
{
    $desde   = $config['correo']['desde'];
    $dominio = substr(strrchr($desde, '@') ?: '@seguranet.es', 1);
    $limite  = '=_Seguranet_' . bin2hex(random_bytes(12));

    $cabeceras = [
        'Date'         => date('r'),
        'From'         => sn_correo_cabecera($config['sitio']['nombre']) . ' <' . $desde . '>',
        'To'           => $para,
        'Reply-To'     => $config['sitio']['correo'],
        'Message-ID'   => '<' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
        'Subject'      => sn_correo_cabecera($asunto),
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $limite . '"',
        'X-Mailer'     => 'Seguranet',
    ];

    // Los correos del sitio son transaccionales —confirmar una cuenta, avisar
    // un vencimiento—, no publicidad. Esto le pide a los buzones que no manden
    // respuestas automáticas ni cuenten el mensaje como campaña.
    $cabeceras['Auto-Submitted'] = 'auto-generated';

    $cuerpo = "--$limite\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode(sn_correo_a_texto($html)), 76, "\r\n")
        . "--$limite\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . "--$limite--\r\n";

    return ['cabeceras' => $cabeceras, 'cuerpo' => $cuerpo];
}

/** Lee una respuesta del servidor, juntando las líneas de las multilínea. */
function sn_smtp_leer($flujo, ?string &$detalle = null): int
{
    $detalle = '';
    do {
        $linea = fgets($flujo, 1024);
        if ($linea === false) {
            $detalle = 'el servidor cortó la conexión';
            return 0;
        }
        $detalle .= $linea;
        // "250-EXTENSION" sigue; "250 EXTENSION" cierra.
        $continua = isset($linea[3]) && $linea[3] === '-';
    } while ($continua);

    return (int) substr($detalle, 0, 3);
}

/** Manda una orden y comprueba que el código de respuesta sea el esperado. */
function sn_smtp_orden($flujo, string $orden, int $esperado, ?string &$error, ?string &$detalle = null): bool
{
    if ($orden !== '' && fwrite($flujo, $orden . "\r\n") === false) {
        $error = 'no se pudo escribir en la conexión';
        return false;
    }
    $codigo = sn_smtp_leer($flujo, $detalle);
    if ($codigo !== $esperado) {
        // El texto del servidor se recorta: puede ser largo y termina en un log.
        $error = 'respuesta inesperada (' . $codigo . '): ' . trim(substr((string) $detalle, 0, 200));
        return false;
    }
    return true;
}

/**
 * Entrega un mensaje ya armado por SMTP.
 *
 * $flujo permite inyectar un socket ya abierto: es lo que usa la prueba para
 * hablar con un servidor de mentira en 127.0.0.1 sin salir a internet.
 */
function sn_correo_entregar(
    array $correo,
    string $para,
    string $mensaje,
    ?string &$error = null,
    $flujo = null
): bool {
    $error   = null;
    $propio  = $flujo === null;
    $cifrado = false;

    if ($propio) {
        $puerto = (int) ($correo['puerto'] ?: 587);
        // El 465 es TLS desde el primer byte; el 587 arranca en claro y sube a
        // TLS con STARTTLS. Son dos cosas distintas y se tratan distinto.
        $destino = ($puerto === 465 ? 'ssl://' : 'tcp://') . $correo['host'] . ':' . $puerto;
        $flujo = @stream_socket_client(
            $destino,
            $nro,
            $motivo,
            SN_SMTP_ESPERA,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );
        if ($flujo === false) {
            $error = 'no se pudo conectar a ' . $correo['host'] . ':' . $puerto . ' (' . trim((string) $motivo) . ')';
            return false;
        }
        $cifrado = $puerto === 465;
    }

    stream_set_timeout($flujo, SN_SMTP_ESPERA);
    $yo = $correo['host'] !== '' ? $correo['host'] : 'localhost';

    try {
        if (!sn_smtp_orden($flujo, '', 220, $error)) {          // saludo del servidor
            return false;
        }
        if (!sn_smtp_orden($flujo, 'EHLO ' . $yo, 250, $error, $capacidades)) {
            return false;
        }

        if (!$cifrado && stripos((string) $capacidades, 'STARTTLS') !== false) {
            if (!sn_smtp_orden($flujo, 'STARTTLS', 220, $error)) {
                return false;
            }
            if (!@stream_socket_enable_crypto($flujo, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $error = 'falló el cifrado TLS (¿certificado del servidor vencido o no confiable?)';
                return false;
            }
            $cifrado = true;
            // Hay que volver a presentarse: lo dicho antes de cifrar no vale.
            if (!sn_smtp_orden($flujo, 'EHLO ' . $yo, 250, $error, $capacidades)) {
                return false;
            }
        }

        if (($correo['usuario'] ?? '') !== '') {
            if (!$cifrado) {
                $error = 'el servidor no ofrece cifrado y la clave viajaría en claro: envío cancelado';
                return false;
            }
            if (!sn_smtp_orden($flujo, 'AUTH LOGIN', 334, $error)
                || !sn_smtp_orden($flujo, base64_encode($correo['usuario']), 334, $error)
                || !sn_smtp_orden($flujo, base64_encode((string) $correo['clave']), 235, $error)) {
                return false;
            }
        }

        if (!sn_smtp_orden($flujo, 'MAIL FROM:<' . $correo['desde'] . '>', 250, $error)
            || !sn_smtp_orden($flujo, 'RCPT TO:<' . $para . '>', 250, $error)
            || !sn_smtp_orden($flujo, 'DATA', 354, $error)) {
            return false;
        }

        // El punto solo en una línea termina el mensaje, así que una línea del
        // cuerpo que empiece con punto hay que duplicarla. Con el cuerpo en
        // base64 no puede pasar, pero la regla vale para cualquier cuerpo
        // futuro y cuesta una línea.
        $datos = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\n", $mensaje));
        $datos = str_replace("\n", "\r\n", (string) $datos);
        if (fwrite($flujo, $datos . "\r\n.\r\n") === false) {
            $error = 'se cortó la conexión mientras se enviaba el mensaje';
            return false;
        }
        if (!sn_smtp_orden($flujo, '', 250, $error)) {          // acuse del mensaje
            return false;
        }

        @sn_smtp_orden($flujo, 'QUIT', 221, $ignorado);
        return true;
    } finally {
        if ($propio && is_resource($flujo)) {
            fclose($flujo);
        }
    }
}

/**
 * Manda un correo. Devuelve false si no salió, y deja el motivo en el log.
 *
 * Con SN_SMTP_HOST configurado se habla SMTP autenticado; sin él se cae a
 * mail(), que sirve para probar en local pero no para producción.
 */
function sn_enviar_correo(array $config, string $para, string $asunto, string $cuerpoHtml): bool
{
    if (!sn_correo_valida($para)) {
        error_log('Seguranet: dirección de destino rechazada por el enviador de correo');
        return false;
    }

    $mensaje = sn_correo_armar($config, $para, $asunto, $cuerpoHtml);

    if (($config['correo']['host'] ?? '') === '') {
        // Sin SMTP: mail() arma su propio sobre y quiere el asunto y el destino
        // aparte, así que se le sacan de las cabeceras.
        $cabeceras = $mensaje['cabeceras'];
        unset($cabeceras['To'], $cabeceras['Subject'], $cabeceras['Date']);
        $planas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $planas[] = $nombre . ': ' . $valor;
        }
        $salio = @mail($para, $mensaje['cabeceras']['Subject'], $mensaje['cuerpo'], implode("\r\n", $planas));
        if (!$salio) {
            error_log('Seguranet: mail() no pudo encolar el mensaje "' . $asunto . '"');
        }
        return $salio;
    }

    $texto = '';
    foreach ($mensaje['cabeceras'] as $nombre => $valor) {
        $texto .= $nombre . ': ' . $valor . "\r\n";
    }
    $texto .= "\r\n" . $mensaje['cuerpo'];

    if (!sn_correo_entregar($config['correo'], $para, $texto, $error)) {
        // El destinatario se registra para poder rastrear un correo que no
        // llegó; la clave del buzón no aparece nunca, ni en el mensaje de error
        // que arma sn_smtp_orden().
        error_log('Seguranet: no se pudo enviar "' . $asunto . '" a ' . $para . ': ' . $error);
        return false;
    }
    return true;
}
