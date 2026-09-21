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
require $raizApp . '/medicion.php';

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

// --- Elección sobre la medición --------------------------------------------
if ($ruta === '/cookies' && $metodo === 'POST') {
    if (sn_token_valido($_POST['token'] ?? null)) {
        sn_guardar_consentimiento((string) ($_POST['eleccion'] ?? ''));
    }
    // Se vuelve a donde estaba, no a la portada: que elegir no te saque de lo
    // que estabas leyendo. Sólo rutas internas, para que nadie use esto como
    // redirector hacia otro sitio.
    $volver = (string) ($_POST['volver'] ?? '/');
    if ($volver === '' || $volver[0] !== '/' || str_starts_with($volver, '//')) {
        $volver = '/';
    }
    header('Location: ' . $volver, true, 303);
    exit;
}

// --- Localidades para los formularios --------------------------------------
// El navegador le pregunta a este sitio y este sitio a georef, en vez de que el
// navegador llame a georef directo: así la respuesta se cachea una vez para
// todos los visitantes y no hay que depender de que georef habilite CORS.
if ($ruta === '/api/localidades') {
    require $raizApp . '/georef.php';
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    $provincia = preg_replace('/\D/', '', (string) ($_GET['provincia'] ?? ''));
    $localidades = $provincia !== '' ? sn_localidades($provincia) : [];
    echo json_encode([
        'provincia'   => $provincia,
        'localidades' => $localidades,
        // El navegador necesita saber si la lista vino vacía porque georef no
        // respondió, para ofrecer escribir la localidad a mano.
        'disponible'  => $localidades !== [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    // Los cotizadores no están en $rutas porque tienen su propio manejo. Se
    // sacan de los ramos, así el día que haya un tercero aparece solo.
    foreach ($ramos as $ramo) {
        if ($ramo['cotizador'] !== '') {
            printf("  <url><loc>%s</loc><lastmod>%s</lastmod><priority>0.9</priority></url>\n",
                e(url($ramo['cotizador'], $config)), $hoy);
        }
    }
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
    require $raizApp . '/georef.php';
    $resultado = sn_procesar_consulta($config, $ramos);
    sn_responder('contacto', [
        'titulo'      => $rutas['/contacto'][1],
        'descripcion' => $rutas['/contacto'][2],
        'ruta'        => '/contacto',
    ], $config, $ramos, ['resultado' => $resultado, 'provincias' => sn_provincias()]);
    exit;
}

// --- Cotizador de automotor ------------------------------------------------
if ($ruta === '/cotizar/auto') {
    require $raizApp . '/georef.php';
    require $raizApp . '/cotizador.php';

    $anioActual = (int) date('Y');
    $datosVista = ['provincias' => sn_provincias()];

    if ($metodo === 'POST') {
        if (!sn_token_valido($_POST['token'] ?? null)) {
            $datosVista['errores'] = ['marca' => 'El formulario venció. Volvé a enviarlo.'];
            $datosVista['valores'] = $_POST;
        } else {
            $entrada = [
                'marca'     => trim((string) ($_POST['marca'] ?? '')),
                'modelo'    => trim((string) ($_POST['modelo'] ?? '')),
                'anio'      => (int) ($_POST['anio'] ?? 0),
                'valor'     => (float) ($_POST['valor'] ?? 0),
                'provincia' => preg_replace('/\D/', '', (string) ($_POST['provincia'] ?? '')),
                'localidad' => trim((string) ($_POST['localidad'] ?? '')),
                'cp'        => trim((string) ($_POST['cp'] ?? '')),
                'edad'      => (int) ($_POST['edad'] ?? 0),
                'uso'       => ($_POST['uso'] ?? '') === 'comercial' ? 'comercial' : 'particular',
                'gnc'       => !empty($_POST['gnc']),
                'rastreo'   => !empty($_POST['rastreo']),
                'ajuste'    => !empty($_POST['ajuste']),
            ];
            $errores = sn_cotizacion_errores($entrada, $anioActual);
            $datosVista['valores'] = $entrada;
            $datosVista['errores'] = $errores;

            if ($errores === []) {
                $cotizacion = sn_cotizar($entrada, $anioActual);
                $datosVista['cotizacion'] = $cotizacion;

                // Si hay sesión, queda guardada para que pueda recuperarla.
                $usuario = sn_usuario();
                $pdo = sn_bd($config);
                if ($usuario !== null && $pdo instanceof PDO) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO cotizaciones (usuario_id, ramo, datos, resultado, creada_en)
                             VALUES (?, "automotor", ?, ?, ?)'
                        )->execute([
                            $usuario['id'],
                            json_encode($entrada, JSON_UNESCAPED_UNICODE),
                            json_encode($cotizacion['planes'], JSON_UNESCAPED_UNICODE),
                            sn_ahora(),
                        ]);
                    } catch (PDOException $ex) {
                        // Que no se pueda guardar no puede impedir ver el precio.
                        error_log('Seguranet cotizacion: ' . $ex->getMessage());
                    }
                }
            }
        }
    }

    sn_responder('cotizar-auto', [
        'titulo'      => 'Cotizador de auto',
        'descripcion' => 'Calculá en línea cuánto sale tu seguro de auto y compará los cuatro '
                       . 'planes de Seguranet según el vehículo, la zona y el conductor.',
        'ruta'        => '/cotizar/auto',
    ], $config, $ramos, $datosVista);
    exit;
}

// --- Cotizador de hogar ----------------------------------------------------
if ($ruta === '/cotizar/hogar') {
    require $raizApp . '/georef.php';
    require $raizApp . '/cotizador-hogar.php';

    $datosVista = ['provincias' => sn_provincias()];

    if ($metodo === 'POST') {
        if (!sn_token_valido($_POST['token'] ?? null)) {
            $datosVista['errores'] = ['tipo' => 'El formulario venció. Volvé a enviarlo.'];
            $datosVista['valores'] = $_POST;
        } else {
            $entrada = [
                'tipo'           => trim((string) ($_POST['tipo'] ?? '')),
                'condicion'      => trim((string) ($_POST['condicion'] ?? '')),
                'suma_edificio'  => (float) ($_POST['suma_edificio'] ?? 0),
                'suma_contenido' => (float) ($_POST['suma_contenido'] ?? 0),
                'antiguedad'     => (int) ($_POST['antiguedad'] ?? -1),
                'seguridad'      => trim((string) ($_POST['seguridad'] ?? '')),
                'planta_baja'    => !empty($_POST['planta_baja']),
                'provincia'      => preg_replace('/\D/', '', (string) ($_POST['provincia'] ?? '')),
                'localidad'      => trim((string) ($_POST['localidad'] ?? '')),
                'cp'             => trim((string) ($_POST['cp'] ?? '')),
            ];
            $errores = sn_hogar_errores($entrada);
            $datosVista['valores'] = $entrada;
            $datosVista['errores'] = $errores;

            if ($errores === []) {
                $cotizacion = sn_cotizar_hogar($entrada);
                $datosVista['cotizacion'] = $cotizacion;

                $usuario = sn_usuario();
                $pdo = sn_bd($config);
                if ($usuario !== null && $pdo instanceof PDO) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO cotizaciones (usuario_id, ramo, datos, resultado, creada_en)
                             VALUES (?, "hogar", ?, ?, ?)'
                        )->execute([
                            $usuario['id'],
                            json_encode($entrada, JSON_UNESCAPED_UNICODE),
                            json_encode($cotizacion['planes'], JSON_UNESCAPED_UNICODE),
                            sn_ahora(),
                        ]);
                    } catch (PDOException $ex) {
                        // Que no se pueda guardar no puede impedir ver el precio.
                        error_log('Seguranet cotizacion hogar: ' . $ex->getMessage());
                    }
                }
            }
        }
    }

    sn_responder('cotizar-hogar', [
        'titulo'      => 'Cotizador de hogar',
        'descripcion' => 'Calculá en línea cuánto sale el seguro de tu casa o departamento '
                       . 'y compará los tres planes. Si alquilás, no pagás el edificio.',
        'ruta'        => '/cotizar/hogar',
    ], $config, $ramos, $datosVista);
    exit;
}

// --- Cotizador de consorcio ------------------------------------------------
if ($ruta === '/cotizar/consorcio') {
    require $raizApp . '/georef.php';
    require $raizApp . '/cotizador-consorcio.php';

    $datosVista = ['provincias' => sn_provincias()];

    if ($metodo === 'POST') {
        if (!sn_token_valido($_POST['token'] ?? null)) {
            $datosVista['errores'] = ['unidades' => 'El formulario venció. Volvé a enviarlo.'];
            $datosVista['valores'] = $_POST;
        } else {
            $entrada = [
                'unidades'      => (int) ($_POST['unidades'] ?? 0),
                'pisos'         => (int) ($_POST['pisos'] ?? 0),
                'antiguedad'    => (int) ($_POST['antiguedad'] ?? -1),
                'suma_edificio' => (float) ($_POST['suma_edificio'] ?? 0),
                'limite_rc'     => (int) ($_POST['limite_rc'] ?? 0),
                'ascensores'    => (int) ($_POST['ascensores'] ?? 0),
                'amenities'     => !empty($_POST['amenities']),
                'provincia'     => preg_replace('/\D/', '', (string) ($_POST['provincia'] ?? '')),
                'localidad'     => trim((string) ($_POST['localidad'] ?? '')),
                'cp'            => trim((string) ($_POST['cp'] ?? '')),
            ];
            $errores = sn_consorcio_errores($entrada);
            $datosVista['valores'] = $entrada;
            $datosVista['errores'] = $errores;

            if ($errores === []) {
                $cotizacion = sn_cotizar_consorcio($entrada);
                $datosVista['cotizacion'] = $cotizacion;

                $usuario = sn_usuario();
                $pdo = sn_bd($config);
                if ($usuario !== null && $pdo instanceof PDO) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO cotizaciones (usuario_id, ramo, datos, resultado, creada_en)
                             VALUES (?, "consorcio", ?, ?, ?)'
                        )->execute([
                            $usuario['id'],
                            json_encode($entrada, JSON_UNESCAPED_UNICODE),
                            json_encode($cotizacion['planes'], JSON_UNESCAPED_UNICODE),
                            sn_ahora(),
                        ]);
                    } catch (PDOException $ex) {
                        error_log('Seguranet cotizacion consorcio: ' . $ex->getMessage());
                    }
                }
            }
        }
    }

    sn_responder('cotizar-consorcio', [
        'titulo'      => 'Cotizador de consorcio',
        'descripcion' => 'Calculá el seguro integral de tu consorcio y compará los tres '
                       . 'planes, con el costo por unidad y por mes para llevar a la asamblea.',
        'ruta'        => '/cotizar/consorcio',
    ], $config, $ramos, $datosVista);
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
        case '/cuenta/polizas':
            if (sn_usuario() === null) {
                header('Location: /cuenta/ingresar', true, 303);
                exit;
            }
            require $raizApp . '/polizas.php';
            $pdo = sn_bd($config);
            $lista = $pdo instanceof PDO ? sn_polizas_de($pdo, sn_usuario()['id']) : [];
            sn_responder('cuenta-polizas', ['titulo' => 'Mis pólizas', 'ruta' => $ruta,
                'descripcion' => 'Tus pólizas y sus vencimientos.', 'noindex' => true],
                $config, $ramos, ['polizas' => $lista]);
            exit;

        case '/cuenta/polizas/nueva':
            if (sn_usuario() === null) {
                header('Location: /cuenta/ingresar', true, 303);
                exit;
            }
            require $raizApp . '/polizas.php';
            $vista = ['valores' => [], 'errores' => []];

            if ($metodo === 'POST') {
                if (!sn_token_valido($_POST['token'] ?? null)) {
                    $vista['errores'] = ['ramo' => 'El formulario venció. Volvé a enviarlo.'];
                    $vista['valores'] = $_POST;
                } else {
                    $entrada = [
                        'ramo'           => trim((string) ($_POST['ramo'] ?? '')),
                        'aseguradora'    => trim((string) ($_POST['aseguradora'] ?? '')),
                        'numero'         => trim((string) ($_POST['numero'] ?? '')),
                        'detalle'        => trim((string) ($_POST['detalle'] ?? '')),
                        'vigencia_desde' => trim((string) ($_POST['vigencia_desde'] ?? '')),
                        'vigencia_hasta' => trim((string) ($_POST['vigencia_hasta'] ?? '')),
                        'prima_mensual'  => trim((string) ($_POST['prima_mensual'] ?? '')),
                        'notas'          => '',
                    ];
                    $errores = sn_poliza_errores($entrada, $ramos);
                    $vista = ['valores' => $entrada, 'errores' => $errores];

                    if ($errores === []) {
                        $pdo = sn_bd($config);
                        if ($pdo instanceof PDO && sn_guardar_poliza($pdo, sn_usuario()['id'], $entrada) !== null) {
                            header('Location: /cuenta/polizas?guardada=1', true, 303);
                            exit;
                        }
                        $vista['errores'] = ['ramo' => 'No pudimos guardarla en este momento. Probá de nuevo.'];
                    }
                }
            }

            sn_responder('cuenta-poliza-nueva', ['titulo' => 'Registrar una póliza', 'ruta' => $ruta,
                'descripcion' => 'Registrá una póliza para recibir avisos de vencimiento.', 'noindex' => true],
                $config, $ramos, $vista);
            exit;

        case '/cuenta/polizas/estado':
            if (sn_usuario() !== null && $metodo === 'POST' && sn_token_valido($_POST['token'] ?? null)) {
                require $raizApp . '/polizas.php';
                $pdo = sn_bd($config);
                if ($pdo instanceof PDO) {
                    sn_cambiar_estado_poliza(
                        $pdo,
                        (int) ($_POST['poliza'] ?? 0),
                        sn_usuario()['id'],
                        (string) ($_POST['estado'] ?? '')
                    );
                }
            }
            header('Location: /cuenta/polizas', true, 303);
            exit;
    }
}

// --- Rutas simples ---------------------------------------------------------
if (isset($rutas[$ruta])) {
    [$vista, $titulo, $descripcion] = $rutas[$ruta];
    $extra = [];
    if ($ruta === '/contacto') {
        require $raizApp . '/georef.php';
        $extra['provincias'] = sn_provincias();
    }
    sn_responder($vista, compact('titulo', 'descripcion') + ['ruta' => $ruta], $config, $ramos, $extra);
    exit;
}

http_response_code(404);
sn_responder('error-404', ['titulo' => 'Página no encontrada', 'ruta' => $ruta,
    'descripcion' => 'La página que buscás no existe.'], $config, $ramos);
