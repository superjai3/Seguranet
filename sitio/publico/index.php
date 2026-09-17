<?php
/**
 * Controlador frontal. Todo entra por acá (ver .htaccess).
 */

declare(strict_types=1);

// El servidor de desarrollo de PHP (php -S) manda todo acá, incluidos los
// archivos estáticos. Devolverle false le dice que sirva el archivo él mismo.
// En producción esto no se ejecuta: el .htaccess ya excluye lo que existe.
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (is_file($archivo)) {
        return false;
    }
}

$raizApp = dirname(__DIR__) . '/app';

$config = require $raizApp . '/config.php';
$ramos  = require $raizApp . '/ramos.php';
require $raizApp . '/ayudas.php';
require $raizApp . '/usuarios.php';

error_reporting($config['depuracion'] ? E_ALL : 0);
ini_set('display_errors', $config['depuracion'] ? '1' : '0');

// La sesión lleva el token de formularios y, más adelante, el usuario.
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$ruta   = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$ruta   = $ruta === '' ? '/' : $ruta;
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/** Renderiza una plantilla de página y devuelve su HTML. */
function sn_vista(string $nombre, array $datos = []): string
{
    extract($datos, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/app/plantillas/paginas/' . $nombre . '.php';
    return (string) ob_get_clean();
}

/** Arma la respuesta completa con la plantilla base. */
function sn_responder(string $vista, array $pagina, array $config, array $ramos, array $datos = []): void
{
    $contenido = sn_vista($vista, $datos + compact('config', 'ramos', 'pagina'));
    require dirname(__DIR__) . '/app/plantillas/layout.php';
}

// --- Rutas simples: ruta => [plantilla, título, descripción] ---------------
$rutas = [
    '/' => ['inicio', 'Seguros en Argentina',
        'Asesoramiento en seguros de auto, hogar, consorcio, vida, ART, comercio y caución. Cotizá en línea y recibí una propuesta a tu medida.'],
    '/seguros' => ['seguros', 'Nuestros seguros',
        'Todos los ramos que asesora Seguranet: automotor, hogar, consorcio, vida, accidentes personales, ART, comercio y caución.'],
    '/cotizar' => ['cotizar', 'Cotizar un seguro',
        'Calculá en línea una estimación para tu seguro de auto, o pedí una propuesta para el resto de los ramos.'],
    '/siniestros' => ['siniestros', 'Denunciar un siniestro',
        'Qué hacer ante un siniestro, qué documentación reunir y cómo denunciarlo dentro de los plazos que fija la ley.'],
    '/preguntas' => ['preguntas', 'Preguntas frecuentes',
        'Franquicia, plazos de denuncia, carta de no siniestro, formas de pago y qué mira una aseguradora al cotizar.'],
    '/nosotros' => ['nosotros', 'Quiénes somos',
        'Qué es Seguranet, cómo trabajamos y en qué situación regulatoria estamos hoy.'],
    '/contacto' => ['contacto', 'Contacto',
        'Escribinos por correo o WhatsApp, o dejanos tu consulta. Respondemos dentro del horario de atención.'],
    '/legales/terminos' => ['legales-terminos', 'Términos y condiciones',
        'Condiciones de uso del sitio de Seguranet.'],
    '/legales/privacidad' => ['legales-privacidad', 'Política de privacidad',
        'Qué datos personales tratamos, con qué finalidad y cómo ejercer tus derechos (Ley 25.326).'],
    '/legales/cookies' => ['legales-cookies', 'Política de cookies',
        'Qué cookies usa este sitio y cómo desactivarlas.'],
    '/legales/arrepentimiento' => ['legales-arrepentimiento', 'Botón de arrepentimiento',
        'Ejercé tu derecho de revocación dentro de los 10 días corridos, conforme la Ley 24.240.'],
    '/legales/defensa-consumidor' => ['legales-consumidor', 'Defensa del consumidor',
        'Tus derechos como consumidor y dónde reclamar.'],
];

// --- Recursos generados ----------------------------------------------------
if ($ruta === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\n\n";
    echo "# El área de cuenta no le aporta nada a quien busca.\n";
    echo "Disallow: /cuenta/\n\n";
    echo "Sitemap: " . url('/sitemap.xml', $config) . "\n";
    exit;
}

if ($ruta === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $hoy = date('Y-m-d');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $prioridades = ['/' => '1.0', '/cotizar' => '0.9', '/seguros' => '0.9', '/contacto' => '0.8'];
    foreach (array_keys($rutas) as $r) {
        printf("  <url><loc>%s</loc><lastmod>%s</lastmod><priority>%s</priority></url>\n",
            e(url($r, $config)), $hoy, $prioridades[$r] ?? '0.6');
    }
    foreach (array_keys($ramos) as $clave) {
        printf("  <url><loc>%s</loc><lastmod>%s</lastmod><priority>0.8</priority></url>\n",
            e(url('/seguros/' . $clave, $config)), $hoy);
    }
    echo '</urlset>';
    exit;
}

// --- Detalle de un ramo ----------------------------------------------------
if (preg_match('#^/seguros/([a-z-]+)$#', $ruta, $coincide)) {
    $clave = $coincide[1];
    if (!isset($ramos[$clave])) {
        http_response_code(404);
        sn_responder('error-404', ['titulo' => 'Página no encontrada', 'ruta' => $ruta,
            'descripcion' => 'La página que buscás no existe.'], $config, $ramos);
        exit;
    }
    $ramo = $ramos[$clave];
    sn_responder('ramo', [
        'titulo'      => $ramo['titulo'],
        'descripcion' => $ramo['resumen'],
        'ruta'        => $ruta,
    ], $config, $ramos, ['clave' => $clave, 'ramo' => $ramo]);
    exit;
}

// --- Envío del formulario de contacto --------------------------------------
if ($ruta === '/contacto' && $metodo === 'POST') {
    require $raizApp . '/consultas.php';
    $resultado = sn_procesar_consulta($config, $ramos);
    sn_responder('contacto', [
        'titulo'      => $rutas['/contacto'][1],
        'descripcion' => $rutas['/contacto'][2],
        'ruta'        => '/contacto',
    ], $config, $ramos, ['resultado' => $resultado]);
    exit;
}

// --- Área de cuenta --------------------------------------------------------
if (str_starts_with($ruta, '/cuenta')) {
    $paginaCuenta = static function (string $vista, string $titulo, string $ruta) use ($config, $ramos) {
        return ['vista' => $vista, 'titulo' => $titulo, 'ruta' => $ruta,
                // Las páginas de cuenta no aportan nada a quien busca, y el
                // panel es privado: fuera del índice.
                'descripcion' => 'Área de cuenta de Seguranet.'];
    };

    switch ($ruta) {
        case '/cuenta/registrar':
            $resultado = $metodo === 'POST' ? sn_registrar($config) : null;
            sn_responder('cuenta-registrar', ['titulo' => 'Crear una cuenta', 'ruta' => $ruta,
                'descripcion' => 'Creá tu cuenta en Seguranet.', 'noindex' => true],
                $config, $ramos, ['resultado' => $resultado]);
            exit;

        case '/cuenta/ingresar':
            if ($metodo === 'POST') {
                $resultado = sn_ingresar($config);
                if ($resultado['ok']) {
                    header('Location: /cuenta', true, 303);
                    exit;
                }
            } else {
                $resultado = null;
            }
            sn_responder('cuenta-ingresar', ['titulo' => 'Ingresar', 'ruta' => $ruta,
                'descripcion' => 'Ingresá a tu cuenta de Seguranet.', 'noindex' => true],
                $config, $ramos, ['resultado' => $resultado]);
            exit;

        case '/cuenta/confirmar':
            $resultado = sn_confirmar($config, (string) ($_GET['t'] ?? ''));
            sn_responder('cuenta-confirmar', ['titulo' => 'Confirmar la cuenta', 'ruta' => $ruta,
                'descripcion' => 'Confirmación de la cuenta.', 'noindex' => true],
                $config, $ramos, ['resultado' => $resultado]);
            exit;

        case '/cuenta/olvide':
            $resultado = $metodo === 'POST' ? sn_pedir_restablecer($config) : null;
            sn_responder('cuenta-olvide', ['titulo' => 'Olvidé mi contraseña', 'ruta' => $ruta,
                'descripcion' => 'Recuperá el acceso a tu cuenta.', 'noindex' => true],
                $config, $ramos, ['resultado' => $resultado]);
            exit;

        case '/cuenta/restablecer':
            $tokenCrudo = (string) ($_GET['t'] ?? '');
            $resultado = $metodo === 'POST' ? sn_restablecer($config, $tokenCrudo) : null;
            sn_responder('cuenta-restablecer', ['titulo' => 'Contraseña nueva', 'ruta' => $ruta,
                'descripcion' => 'Elegí una contraseña nueva.', 'noindex' => true],
                $config, $ramos, ['resultado' => $resultado, 'tokenCrudo' => $tokenCrudo]);
            exit;

        case '/cuenta/salir':
            // Sólo por POST y con token: con un GET bastaría con inducir al
            // navegador a pedir esa dirección para sacar a alguien de su sesión.
            if ($metodo === 'POST' && sn_token_valido($_POST['token'] ?? null)) {
                sn_salir();
            }
            header('Location: /', true, 303);
            exit;

        case '/cuenta':
            if (sn_usuario() === null) {
                header('Location: /cuenta/ingresar', true, 303);
                exit;
            }
            sn_responder('cuenta-panel', ['titulo' => 'Mi cuenta', 'ruta' => $ruta,
                'descripcion' => 'Tu cuenta en Seguranet.', 'noindex' => true], $config, $ramos);
            exit;
    }
}

// --- Rutas simples ---------------------------------------------------------
if (isset($rutas[$ruta])) {
    [$vista, $titulo, $descripcion] = $rutas[$ruta];
    sn_responder($vista, compact('titulo', 'descripcion') + ['ruta' => $ruta], $config, $ramos);
    exit;
}

http_response_code(404);
sn_responder('error-404', ['titulo' => 'Página no encontrada', 'ruta' => $ruta,
    'descripcion' => 'La página que buscás no existe.'], $config, $ramos);
