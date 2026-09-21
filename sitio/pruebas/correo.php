<?php
/**
 * Prueba del envío de correo.
 *
 * La conversación SMTP se prueba de verdad, sin salir a internet y sin levantar
 * un servidor: se usa un par de sockets conectados entre sí (stream_socket_pair)
 * y se dejan escritas de antemano todas las respuestas que daría el servidor.
 * El enviador las va leyendo como si hubiera alguien del otro lado, y al final
 * se mira lo que escribió. Alcanza porque las respuestas son unos cientos de
 * bytes y el búfer del socket tiene de sobra.
 *
 *     php sitio/pruebas/correo.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
require $raiz . '/app/ayudas.php';

$fallos = 0;

function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

/**
 * Arma un servidor de mentira con las respuestas dadas y devuelve
 * [punta del cliente, función que lee lo que el cliente escribió].
 */
function servidor_falso(array $respuestas): array
{
    [$cliente, $servidor] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    if ($respuestas === []) {
        // Servidor que corta sin decir nada: se cierra la punta de una.
        fclose($servidor);
        return [$cliente, static fn (): string => ''];
    }
    fwrite($servidor, implode("\r\n", $respuestas) . "\r\n");
    return [$cliente, static function () use ($servidor, $cliente): string {
        fclose($cliente);
        stream_set_blocking($servidor, false);
        $escrito = stream_get_contents($servidor);
        fclose($servidor);
        return (string) $escrito;
    }];
}

/** Las respuestas de un servidor que acepta todo y no ofrece cifrado. */
function respuestas_felices(): array
{
    return ['220 correo.ejemplo listo', '250 correo.ejemplo', '250 ok', '250 ok',
            '354 mandá el mensaje', '250 aceptado', '221 chau'];
}

echo "\nDirecciones\n";

comprobar('acepta una dirección normal', sn_correo_valida('jaime@seguranet.es'));
comprobar('rechaza una vacía', !sn_correo_valida(''));
comprobar('rechaza una que no es dirección', !sn_correo_valida('esto no es un correo'));
// El caso que importa: con un salto de línea, todo lo que sigue se convertiría
// en una cabecera nueva y el formulario de registro pasaría a ser un relay.
comprobar('rechaza una inyección de cabeceras',
    !sn_correo_valida("victima@ejemplo.com\r\nBcc: listado@spam.net"));
comprobar('rechaza también con un solo \\n',
    !sn_correo_valida("victima@ejemplo.com\nBcc: listado@spam.net"));

echo "\nCabeceras\n";

comprobar('deja el ASCII como está', sn_correo_cabecera('Confirma tu cuenta') === 'Confirma tu cuenta');
$asunto = 'Confirmá tu cuenta · Seguranet';
$codificado = sn_correo_cabecera($asunto);
comprobar('codifica el asunto con tildes', str_contains($codificado, '=?UTF-8?B?'), $codificado);
comprobar('el asunto codificado se puede volver a leer',
    mb_decode_mimeheader($codificado) === $asunto, $codificado);
foreach (explode("\r\n", $codificado) as $linea) {
    comprobar('ninguna línea del asunto pasa de 75 caracteres', strlen($linea) <= 76, $linea);
}
$largo = sn_correo_cabecera(str_repeat('Vencimiento de tu póliza ', 8));
comprobar('un asunto largo se pliega en varias líneas', str_contains($largo, "\r\n "), $largo);
comprobar('un asunto largo sobrevive al plegado',
    mb_decode_mimeheader($largo) === str_repeat('Vencimiento de tu póliza ', 8));
comprobar('un salto de línea en el asunto no crea una cabecera',
    !str_contains(sn_correo_cabecera("Hola\r\nBcc: spam@ejemplo.com"), "\r\nBcc"));

echo "\nCuerpo en texto plano\n";

$html = '<p>Hola Jaime,</p><p>Confirmá tu cuenta desde <a href="https://seguranet.es/cuenta/confirmar?t=abc">este enlace</a>.</p><ul><li>Vence en 48 h</li></ul>';
$texto = sn_correo_a_texto($html);
comprobar('no quedan etiquetas', !str_contains($texto, '<'), $texto);
// Sin esto el correo en texto plano diría "este enlace" y nada más: inservible
// justamente en el mensaje que sólo sirve para hacer clic.
comprobar('el enlace conserva la URL',
    str_contains($texto, 'https://seguranet.es/cuenta/confirmar?t=abc'), $texto);
comprobar('la lista se lee como lista', str_contains($texto, '- Vence en 48 h'), $texto);
comprobar('no se amontona todo en un renglón', substr_count($texto, "\n") >= 2, $texto);
comprobar('decodifica las entidades', sn_correo_a_texto('<p>Ma&ntilde;ana &amp; pasado</p>') === 'Mañana & pasado');

echo "\nArmado del mensaje\n";

$m = sn_correo_armar($config, 'jaime@ejemplo.com', $asunto, $html);
comprobar('el destinatario va en To', $m['cabeceras']['To'] === 'jaime@ejemplo.com');
comprobar('declara multipart/alternative', str_contains($m['cabeceras']['Content-Type'], 'multipart/alternative'));
comprobar('tiene Message-ID con dominio propio', (bool) preg_match('/^<[0-9a-f]{32}@seguranet\.es>$/', $m['cabeceras']['Message-ID']), $m['cabeceras']['Message-ID']);
comprobar('se marca como transaccional', ($m['cabeceras']['Auto-Submitted'] ?? '') === 'auto-generated');
preg_match('/boundary="([^"]+)"/', $m['cabeceras']['Content-Type'], $c);
$limite = $c[1] ?? '';
comprobar('el cuerpo abre y cierra con el límite declarado',
    str_starts_with($m['cuerpo'], "--$limite\r\n") && str_ends_with($m['cuerpo'], "--$limite--\r\n"));
comprobar('lleva la parte de texto y la de HTML',
    substr_count($m['cuerpo'], 'Content-Type: text/plain') === 1
    && substr_count($m['cuerpo'], 'Content-Type: text/html') === 1);
comprobar('el HTML viaja entero', str_contains($m['cuerpo'], chunk_split(base64_encode($html), 76, "\r\n")));
$otro = sn_correo_armar($config, 'jaime@ejemplo.com', $asunto, $html);
comprobar('cada mensaje usa su propio límite',
    $otro['cabeceras']['Content-Type'] !== $m['cabeceras']['Content-Type']);

echo "\nConversación SMTP\n";

$smtp = ['host' => 'correo.ejemplo', 'puerto' => 587, 'usuario' => '', 'clave' => '',
         'desde' => 'no-responder@seguranet.es'];

[$cliente, $leer] = servidor_falso(respuestas_felices());
$ok = sn_correo_entregar($smtp, 'jaime@ejemplo.com', "Subject: hola\r\n\r\ncuerpo", $error, $cliente);
$dicho = $leer();
comprobar('entrega el mensaje', $ok, (string) $error);
comprobar('se presenta con EHLO', str_contains($dicho, "EHLO correo.ejemplo\r\n"), $dicho);
comprobar('usa el remitente del sobre configurado', str_contains($dicho, 'MAIL FROM:<no-responder@seguranet.es>'));
comprobar('manda el destinatario', str_contains($dicho, 'RCPT TO:<jaime@ejemplo.com>'));
comprobar('cierra el mensaje con el punto solo', str_contains($dicho, "\r\ncuerpo\r\n.\r\n"), $dicho);
comprobar('se despide', str_contains($dicho, "QUIT\r\n"));

// Si el servidor rechaza al destinatario, el envío falla: no se sigue como si
// nada. Era lo que pasaba con mail(), que devolvía true igual.
[$cliente, $leer] = servidor_falso(['220 listo', '250 correo.ejemplo', '250 ok',
                                    '550 buzón inexistente', '221 chau']);
$ok = sn_correo_entregar($smtp, 'nadie@ejemplo.com', "Subject: hola\r\n\r\ncuerpo", $error, $cliente);
$leer();
comprobar('un destinatario rechazado da false', !$ok);
comprobar('el error dice el código del servidor', str_contains((string) $error, '550'), (string) $error);

[$cliente, $leer] = servidor_falso(['421 hoy no, gracias']);
$ok = sn_correo_entregar($smtp, 'jaime@ejemplo.com', 'x', $error, $cliente);
$leer();
comprobar('un servidor que no saluda bien da false', !$ok);

// Un servidor que corta la conexión no puede colgar la prueba ni dar true.
[$cliente, $leer] = servidor_falso([]);
$ok = sn_correo_entregar($smtp, 'jaime@ejemplo.com', 'x', $error, $cliente);
$leer();
comprobar('un servidor que corta la conexión da false', !$ok);
comprobar('y lo dice', str_contains((string) $error, 'cortó'), (string) $error);

echo "\nLa clave nunca viaja en claro\n";

$conClave = $smtp;
$conClave['usuario'] = 'no-responder@seguranet.es';
$conClave['clave']   = 'SecretoDelBuzon123';

// El servidor no anuncia STARTTLS: hay que abortar ANTES de mandar AUTH.
[$cliente, $leer] = servidor_falso(respuestas_felices());
$ok = sn_correo_entregar($conClave, 'jaime@ejemplo.com', 'x', $error, $cliente);
$dicho = $leer();
comprobar('sin cifrado no se envía', !$ok);
comprobar('el error explica por qué', str_contains((string) $error, 'cifrado'), (string) $error);
comprobar('no se manda AUTH', !str_contains($dicho, 'AUTH'), $dicho);
comprobar('la clave no aparece en claro', !str_contains($dicho, 'SecretoDelBuzon123'));
comprobar('ni codificada en base64', !str_contains($dicho, base64_encode('SecretoDelBuzon123')), $dicho);
comprobar('el mensaje de error tampoco filtra la clave',
    !str_contains((string) $error, 'SecretoDelBuzon123'));

// Con STARTTLS anunciado hay que subir a TLS antes de cualquier AUTH. Acá el
// cifrado no puede completarse (del otro lado hay un socket de juguete, no un
// servidor con certificado), y eso es justamente lo que se quiere ver: que sin
// TLS de verdad la conversación se corta y la clave no sale.
[$cliente, $leer] = servidor_falso(['220 listo', '250-correo.ejemplo', '250-STARTTLS',
                                    '250 OK', '220 adelante con el cifrado']);
$ok = sn_correo_entregar($conClave, 'jaime@ejemplo.com', 'x', $error, $cliente);
$dicho = $leer();
comprobar('si el servidor ofrece STARTTLS, se pide', str_contains($dicho, "STARTTLS\r\n"), $dicho);
comprobar('si el cifrado no se completa, no se envía', !$ok);
comprobar('y no se manda AUTH antes de cifrar', !str_contains($dicho, 'AUTH'), $dicho);
comprobar('la clave sigue sin aparecer', !str_contains($dicho, base64_encode('SecretoDelBuzon123')));

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
