<?php
/**
 * Avisos de vencimiento de pólizas.
 *
 * Se corre una vez por día desde las Tareas cron de IONOS:
 *
 *     /usr/bin/php /ruta/al/sitio/tareas/avisar-vencimientos.php
 *
 * Conviene a la mañana temprano, hora argentina: un aviso que llega a las 3 AM
 * se lee tarde y encima molesta.
 *
 * Tres cosas que este script hace bien y que un cron mal escrito hace mal:
 *
 *  1. Registra cada aviso enviado con una clave única (póliza + hito). Si el
 *     script corre dos veces el mismo día, el segundo no manda nada. Sin eso,
 *     un reintento después de un error duplica todos los correos.
 *  2. Manda un solo aviso por póliza por corrida, el del hito más cercano. Si
 *     estuvo caído una semana, no vomita tres correos juntos al volver.
 *  3. Marca el hito como enviado ANTES de mandar el correo. Si el envío falla,
 *     se pierde un aviso; si se hiciera al revés y fallara el registro, el
 *     cliente recibiría el mismo correo todos los días hasta que alguien mire.
 *     De los dos errores posibles, este es el barato.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
$ramos  = require $raiz . '/app/ramos.php';
require $raiz . '/app/ayudas.php';
require $raiz . '/app/polizas.php';

$pdo = sn_bd($config);
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No hay conexión a la base. Revisá app/config.local.php\n");
    exit(1);
}

$hoy = sn_ahora();
$maximo = max(SN_HITOS_AVISO);

// Sólo las vigentes que vencen dentro de la ventana de avisos. Las vencidas no
// entran: para esas el mensaje sería otro, y todavía no está escrito.
$consulta = $pdo->prepare(
    'SELECT p.*, u.correo, u.nombre
       FROM polizas p
       JOIN usuarios u ON u.id = p.usuario_id
      WHERE p.estado = "vigente"
        AND u.correo_confirmado = 1
        AND p.vigencia_hasta >= ?
        AND p.vigencia_hasta <= ?
      ORDER BY p.vigencia_hasta ASC'
);
$consulta->execute([
    (new DateTimeImmutable($hoy))->format('Y-m-d'),
    (new DateTimeImmutable($hoy))->modify('+' . $maximo . ' days')->format('Y-m-d'),
]);
$polizas = $consulta->fetchAll();

$enviados = 0;
$salteados = 0;

foreach ($polizas as $poliza) {
    $dias = sn_dias_para_vencer($poliza['vigencia_hasta'], $hoy);

    $yaConsulta = $pdo->prepare('SELECT hito FROM avisos_vencimiento WHERE poliza_id = ?');
    $yaConsulta->execute([$poliza['id']]);
    $ya = array_map('intval', array_column($yaConsulta->fetchAll(), 'hito'));

    $hito = sn_hito_a_enviar($dias, $ya);
    if ($hito === null) {
        $salteados++;
        continue;
    }

    // Se reserva el hito primero. La clave única de la tabla hace que, si dos
    // corridas coinciden, sólo una gane.
    try {
        $pdo->prepare('INSERT INTO avisos_vencimiento (poliza_id, hito, enviado_en) VALUES (?, ?, ?)')
            ->execute([$poliza['id'], $hito, $hoy]);
    } catch (PDOException $ex) {
        // Clave duplicada: otro proceso ya lo tomó. No es un error.
        $salteados++;
        continue;
    }

    $texto = sn_texto_aviso($poliza, $hito, $ramos, $config);
    if (sn_enviar_correo($config, $poliza['correo'], $texto['asunto'], $texto['cuerpo'])) {
        $enviados++;
        printf("aviso de %d días enviado · póliza %d · %s\n", $hito, $poliza['id'], $poliza['correo']);
    } else {
        fwrite(STDERR, sprintf("no se pudo enviar el aviso de la póliza %d\n", $poliza['id']));
    }
}

printf("\nPólizas en ventana: %d · avisos enviados: %d · sin aviso pendiente: %d\n",
    count($polizas), $enviados, $salteados);
exit(0);
