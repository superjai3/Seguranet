<?php
/**
 * Plantilla base. Recibe por variable: $config, $ramos, $pagina (con titulo,
 * descripcion, ruta) y $contenido ya renderizado.
 */
declare(strict_types=1);

$titulo      = $pagina['titulo'] ?? $config['sitio']['nombre'];
$descripcion = $pagina['descripcion'] ?? $config['sitio']['lema'];
$ruta        = $pagina['ruta'] ?? '/';
$canonica    = url($ruta, $config);
$matriculado = $config['registro']['matriculado'] === true;
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($titulo) ?> · Seguranet</title>
<meta name="description" content="<?= e($descripcion) ?>">
<link rel="canonical" href="<?= e($canonica) ?>">

<?php /* Geolocalización: le dice a Google que esto es para Argentina, aunque
         el dominio sea .es. Sin esto, un .es se interpreta como sitio español. */ ?>
<?php if (!empty($pagina['noindex'])): ?>
<meta name="robots" content="noindex, follow">
<?php else: ?>
<meta name="robots" content="index, follow, max-image-preview:large">
<?php endif; ?>
<link rel="alternate" hreflang="es-AR" href="<?= e($canonica) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($canonica) ?>">
<meta name="geo.region" content="AR">
<meta name="geo.placename" content="Argentina">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Seguranet">
<meta property="og:locale" content="es_AR">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e($descripcion) ?>">
<meta property="og:url" content="<?= e($canonica) ?>">
<meta property="og:image" content="<?= e(url('/recursos/img/seguranet-social.png', $config)) ?>">
<meta name="twitter:card" content="summary_large_image">

<meta name="theme-color" content="#0A2540">
<link rel="icon" href="/favicon.ico" sizes="any">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/recursos/css/seguranet.css?v=<?= e((string) @filemtime(__DIR__ . '/../../publico/recursos/css/seguranet.css')) ?>">

<?php /* Datos estructurados. Para buscadores clásicos y para los motores
         generativos, que citan mejor lo que pueden leer sin interpretar.
         El tipo es InsuranceAgency, no LocalBusiness genérico. */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'InsuranceAgency',
    'name'     => 'Seguranet',
    'url'      => $config['sitio']['url'],
    'email'    => $config['sitio']['correo'],
    'areaServed' => ['@type' => 'Country', 'name' => 'Argentina'],
    'availableLanguage' => 'es-AR',
    'description' => $descripcion,
    'knowsAbout' => array_values(array_map(static fn($r) => $r['titulo'], $ramos)),
    'openingHours' => 'Mo-Fr 09:00-18:00, Sa 09:00-14:00',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
</head>

<body>
<a class="sn-saltar" href="#contenido">Saltar al contenido</a>

<header class="sn-cabecera">
    <div class="sn-contenedor sn-cabecera__interior">
        <a class="sn-logo" href="/" aria-label="Seguranet, ir al inicio">
            <img src="/recursos/img/logo-seguranet.webp" alt="" width="160" height="40">
            <span class="sn-solo-lector">Seguranet</span>
        </a>

        <button class="sn-menu-boton" type="button"
                aria-expanded="false" aria-controls="menu-principal"
                data-menu>
            <span class="sn-solo-lector">Abrir el menú</span>
            <?= sn_icono('menu') ?>
        </button>

        <nav class="sn-nav" id="menu-principal" aria-label="Principal">
            <a href="/"<?= $ruta === '/' ? ' aria-current="page"' : '' ?>>Inicio</a>
            <a href="/seguros"<?= str_starts_with($ruta, '/seguros') ? ' aria-current="page"' : '' ?>>Seguros</a>
            <a href="/cotizar"<?= $ruta === '/cotizar' ? ' aria-current="page"' : '' ?>>Cotizar</a>
            <a href="/siniestros"<?= $ruta === '/siniestros' ? ' aria-current="page"' : '' ?>>Siniestros</a>
            <a href="/preguntas"<?= $ruta === '/preguntas' ? ' aria-current="page"' : '' ?>>Preguntas</a>
            <a href="/nosotros"<?= $ruta === '/nosotros' ? ' aria-current="page"' : '' ?>>Nosotros</a>
            <a href="/contacto"<?= $ruta === '/contacto' ? ' aria-current="page"' : '' ?>>Contacto</a>

            <?php $usuario = sn_usuario(); ?>
            <?php
            /* El enlace al panel sólo aparece para quien puede entrar. No es la
               protección —de eso se encarga la ruta, que devuelve 404— sino que
               no tiene sentido mostrarle a un cliente un enlace que para él no
               existe. panel.php se carga acá porque el layout lo dibuja todo. */
            if (sn_usuario() !== null) {
                require_once dirname(__DIR__) . '/panel.php';
            }
            ?>
            <?php if ($usuario !== null && sn_es_admin($config, $usuario)): ?>
                <a href="/panel"<?= $ruta === '/panel' ? ' aria-current="page"' : '' ?>>Consultas</a>
            <?php endif; ?>
            <?php if ($usuario !== null): ?>
                <a href="/cuenta" class="sn-nav__cuenta"<?= str_starts_with($ruta, '/cuenta') ? ' aria-current="page"' : '' ?>>
                    <?= sn_icono('usuario') ?>
                    <span><?= e(explode(' ', $usuario['nombre'])[0]) ?></span>
                </a>
            <?php else: ?>
                <a href="/cuenta/ingresar" class="sn-nav__cuenta">
                    <?= sn_icono('usuario') ?>
                    <span>Ingresar</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="contenido">
<?= $contenido ?>
</main>

<footer class="sn-pie">
    <div class="sn-contenedor">
        <div class="sn-rejilla sn-rejilla--4">
            <div>
                <h4>Seguranet</h4>
                <p><?= e($config['sitio']['lema']) ?></p>
                <p><?= e($config['sitio']['horario']) ?></p>
            </div>
            <div>
                <h4>Seguros</h4>
                <ul>
                    <?php foreach (array_slice($ramos, 0, 5, true) as $clave => $ramo): ?>
                        <li><a href="/seguros/<?= e($clave) ?>"><?= e($ramo['nombre']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="/seguros">Ver todos</a></li>
                </ul>
            </div>
            <div>
                <h4>Ayuda</h4>
                <ul>
                    <li><a href="/contacto">Contacto</a></li>
                    <li><a href="/siniestros">Denunciar un siniestro</a></li>
                    <li><a href="/preguntas">Preguntas frecuentes</a></li>
                    <li><a href="<?= e(sn_whatsapp($config, 'Hola, quiero consultar por un seguro.')) ?>"
                           target="_blank" rel="noopener">WhatsApp</a></li>
                </ul>
            </div>
            <div>
                <h4>Legales</h4>
                <ul>
                    <li><a href="/legales/terminos">Términos y condiciones</a></li>
                    <li><a href="/legales/privacidad">Política de privacidad</a></li>
                    <li><a href="/legales/cookies">Cookies</a></li>
                    <li><a href="/legales/arrepentimiento">Botón de arrepentimiento</a></li>
                    <li><a href="/legales/defensa-consumidor">Defensa del consumidor</a></li>
                </ul>
            </div>
        </div>

        <?php /* Identificación obligatoria. Con matrícula, la Ley 22.400 exige
                 que el productor se identifique con nombre y número en toda su
                 actuación. Sin matrícula, lo que corresponde es decir qué es
                 este sitio y qué no hace, sin ambigüedad. */ ?>
        <div class="sn-pie__legal">
            <?php if ($matriculado): ?>
                <p class="sn-pie__matricula">
                    <?= e($config['registro']['titular']) ?> — Productor Asesor de Seguros,
                    matrícula SSN N.º <?= e($config['registro']['matricula_ssn']) ?>.
                </p>
                <?php if ($config['registro']['organizador'] !== ''): ?>
                    <p>Organizador: <?= e($config['registro']['organizador']) ?>
                       (matrícula SSN N.º <?= e($config['registro']['matricula_organizador']) ?>).</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="sn-pie__matricula">
                    Seguranet es un proyecto en formación y todavía no intermedia en la
                    contratación de seguros.
                </p>
                <p>
                    En la República Argentina, la intermediación en la concertación de
                    contratos de seguro está reservada a los productores asesores inscriptos
                    en el registro que lleva la Superintendencia de Seguros de la Nación
                    (Ley 22.400). Este sitio brinda información y recibe consultas; no emite
                    cobertura, no cobra primas y no sustituye el asesoramiento de un productor
                    matriculado ni la póliza de una aseguradora autorizada por la SSN.
                </p>
            <?php endif; ?>

            <p>
                Las coberturas descriptas son informativas y están sujetas a las condiciones
                generales y particulares de la póliza de cada aseguradora. Las estimaciones
                del cotizador no constituyen oferta ni obligan a aseguradora alguna
                (art. 4, Ley 17.418).
            </p>
            <p>
                Superintendencia de Seguros de la Nación · 0800-666-8400 ·
                <a href="https://www.argentina.gob.ar/ssn" target="_blank" rel="noopener">argentina.gob.ar/ssn</a>
                — organismo de control de la actividad aseguradora.
            </p>
            <p>© <?= date('Y') ?> Seguranet. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<?php /* Analítica: sólo si hay identificador configurado Y la persona aceptó.
         Se usa Plausible, que no instala cookies ni sigue a nadie entre sitios
         —pero igual se pide permiso, porque es lo que corresponde—. */ ?>
<?php if (sn_medicion_activa($config)): ?>
    <?php if ($config['medicion']['proveedor'] === 'plausible'): ?>
        <script defer data-domain="<?= e(parse_url($config['sitio']['url'], PHP_URL_HOST) ?: '') ?>"
                src="https://plausible.io/js/script.js"></script>
    <?php endif; ?>
<?php endif; ?>

<?php if (sn_mostrar_aviso_cookies($config)): ?>
    <div class="sn-cookies" role="dialog" aria-live="polite"
         aria-labelledby="sn-cookies-titulo">
        <div class="sn-cookies__texto">
            <strong id="sn-cookies-titulo">¿Nos dejás medir cómo se usa el sitio?</strong>
            <p>
                Nos sirve para saber qué páginas ayudan y cuáles no. No compartimos
                los datos con nadie ni te seguimos por otros sitios.
                <a href="/legales/cookies">Cómo funciona</a>.
            </p>
        </div>
        <form method="post" action="/cookies" class="sn-cookies__acciones">
            <input type="hidden" name="token" value="<?= e(sn_token()) ?>">
            <input type="hidden" name="volver" value="<?= e($ruta) ?>">
            <?php /* Los dos botones son iguales en tamaño y peso visual a
                     propósito: si rechazar cuesta más que aceptar, el
                     consentimiento no es libre. */ ?>
            <button class="sn-boton sn-boton--linea" type="submit" name="eleccion" value="no">No, gracias</button>
            <button class="sn-boton sn-boton--primario" type="submit" name="eleccion" value="si">Aceptar</button>
        </form>
    </div>
<?php endif; ?>

<script src="/recursos/js/seguranet.js" defer></script>
</body>
</html>
