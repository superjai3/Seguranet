<?php
/**
 * Configuración de Seguranet.
 *
 * Nada sensible se versiona. Los valores reales viven en config.local.php
 * —que está en .gitignore— o en variables de entorno del hosting. Este archivo
 * define los valores por defecto y documenta qué hay que completar.
 *
 * El proyecto anterior tenía la clave del correo escrita en el código fuente
 * (Servicios/CorreoServicio.cs) y la cadena de conexión apuntando a "Server=MITO",
 * la máquina de quien lo programó. Las dos cosas no se repiten acá.
 */

declare(strict_types=1);

/** Lee una variable de entorno, con valor por defecto. */
function sn_env(string $clave, string $porDefecto = ''): string
{
    $valor = getenv($clave);
    return ($valor === false || $valor === '') ? $porDefecto : $valor;
}

$config = [

    // --- Identidad del sitio ---------------------------------------------
    'sitio' => [
        'nombre'      => 'Seguranet',
        'lema'        => 'Seguros pensados para vos',
        'url'         => sn_env('SN_URL', 'https://seguranet.es'),
        'idioma'      => 'es-AR',
        'pais'        => 'AR',
        'correo'      => sn_env('SN_CORREO_CONTACTO', 'contacto@seguranet.es'),
        'whatsapp'    => sn_env('SN_WHATSAPP', '34632638449'),
        'horario'     => 'Lunes a viernes de 9 a 18 h · Sábados de 9 a 14 h (hora de Argentina)',
    ],

    // --- Situación regulatoria -------------------------------------------
    // Determina qué puede decir el sitio. Con 'matriculado' => false el sitio
    // capta consultas y asesora, pero NO ofrece contratar: la intermediación
    // en seguros está reservada a productores asesores inscriptos en el
    // registro de la Superintendencia de Seguros de la Nación (Ley 22.400,
    // arts. 2 y 4). El día que exista matrícula se completan estos datos y el
    // sitio cambia de lenguaje solo.
    'registro' => [
        'matriculado'      => false,
        'titular'          => sn_env('SN_TITULAR', 'Jaime Valdés Viñas'),
        'matricula_ssn'    => sn_env('SN_MATRICULA', ''),
        'organizador'      => sn_env('SN_ORGANIZADOR', ''),
        'matricula_organizador' => sn_env('SN_MATRICULA_ORGANIZADOR', ''),
        'cuit'             => sn_env('SN_CUIT', ''),
        'domicilio'        => sn_env('SN_DOMICILIO', ''),
    ],

    // --- Base de datos (IONOS: MySQL) ------------------------------------
    'bd' => [
        'host'   => sn_env('SN_BD_HOST', 'localhost'),
        'nombre' => sn_env('SN_BD_NOMBRE', ''),
        'usuario'=> sn_env('SN_BD_USUARIO', ''),
        'clave'  => sn_env('SN_BD_CLAVE', ''),
        'juego'  => 'utf8mb4',
    ],

    // --- Correo saliente (SMTP del hosting, no Gmail con clave en el código)
    'correo' => [
        'host'   => sn_env('SN_SMTP_HOST', ''),
        'puerto' => (int) sn_env('SN_SMTP_PUERTO', '587'),
        'usuario'=> sn_env('SN_SMTP_USUARIO', ''),
        'clave'  => sn_env('SN_SMTP_CLAVE', ''),
        'desde'  => sn_env('SN_SMTP_DESDE', 'no-responder@seguranet.es'),
    ],

    // Poner en true sólo en desarrollo.
    'depuracion' => sn_env('SN_DEPURACION', '0') === '1',
];

// Los valores reales pisan a los de arriba, si el archivo existe.
$local = __DIR__ . '/config.local.php';
if (is_readable($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
