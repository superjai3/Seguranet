<?php
/**
 * Recepción de consultas del formulario de contacto.
 *
 * Tres cosas que el proyecto anterior no hacía y acá sí:
 *
 *  1. Los datos se guardan. Antes el POST de contacto ponía un mensaje de
 *     "gracias" en TempData y tiraba el formulario a la basura: nadie recibía
 *     la consulta.
 *  2. Se pide consentimiento expreso para tratar los datos, porque nombre,
 *     teléfono y correo son datos personales (Ley 25.326) y sin consentimiento
 *     informado no se pueden tratar.
 *  3. Se pide sólo lo necesario. El cotizador viejo arrancaba pidiendo DNI,
 *     fecha de nacimiento y domicilio antes de mostrar un solo precio. Para
 *     responder una consulta no hace falta nada de eso.
 */

declare(strict_types=1);

/** @return array{ok:bool, errores:array<string,string>, valores:array<string,string>, mensaje:string} */
function sn_procesar_consulta(array $config, array $ramos): array
{
    $valores = [
        'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
        'correo'   => trim((string) ($_POST['correo'] ?? '')),
        'telefono' => trim((string) ($_POST['telefono'] ?? '')),
        'ramo'     => trim((string) ($_POST['ramo'] ?? '')),
        'mensaje'  => trim((string) ($_POST['mensaje'] ?? '')),
    ];
    $errores = [];

    if (!sn_token_valido($_POST['token'] ?? null)) {
        return ['ok' => false, 'errores' => [], 'valores' => $valores,
            'mensaje' => 'El formulario venció. Volvé a enviarlo, por favor.'];
    }

    // Trampa para robots: un campo que una persona nunca ve ni completa.
    if (($_POST['sitio_web'] ?? '') !== '') {
        return ['ok' => true, 'errores' => [], 'valores' => [], 'mensaje' => ''];
    }

    if ($valores['nombre'] === '' || mb_strlen($valores['nombre']) < 2) {
        $errores['nombre'] = 'Decinos cómo te llamás.';
    }
    if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Revisá el correo: no parece una dirección válida.';
    }
    if ($valores['telefono'] !== '' && !preg_match('/^[\d\s()+-]{6,25}$/', $valores['telefono'])) {
        $errores['telefono'] = 'El teléfono sólo puede tener números, espacios y los signos + - ( ).';
    }
    if ($valores['ramo'] !== '' && !isset($ramos[$valores['ramo']])) {
        $errores['ramo'] = 'Elegí un seguro de la lista.';
    }
    if (mb_strlen($valores['mensaje']) < 10) {
        $errores['mensaje'] = 'Contanos un poco más: con al menos diez caracteres podemos ayudarte mejor.';
    }
    if (mb_strlen($valores['mensaje']) > 4000) {
        $errores['mensaje'] = 'El mensaje es demasiado largo. Resumilo en 4000 caracteres.';
    }
    if (empty($_POST['consentimiento'])) {
        $errores['consentimiento'] = 'Necesitamos tu conformidad para poder tratar tus datos y responderte.';
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'valores' => $valores,
            'mensaje' => 'Revisá los campos marcados.'];
    }

    // Se guarda primero: si después falla el correo, la consulta no se pierde.
    $guardada = false;
    $pdo = sn_bd($config);
    if ($pdo instanceof PDO) {
        try {
            $sql = 'INSERT INTO consultas (nombre, correo, telefono, ramo, mensaje, origen_ip, agente, creada_en)
                    VALUES (:nombre, :correo, :telefono, :ramo, :mensaje, :ip, :agente, NOW())';
            $pdo->prepare($sql)->execute([
                ':nombre'   => $valores['nombre'],
                ':correo'   => $valores['correo'],
                ':telefono' => $valores['telefono'],
                ':ramo'     => $valores['ramo'],
                ':mensaje'  => $valores['mensaje'],
                // La IP se guarda para poder acreditar el consentimiento y
                // frenar abuso; se borra con el resto según la política.
                ':ip'       => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                ':agente'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            $guardada = true;
        } catch (PDOException $e) {
            error_log('Seguranet: no se pudo guardar la consulta: ' . $e->getMessage());
        }
    }

    $nombreRamo = $valores['ramo'] !== '' ? $ramos[$valores['ramo']]['titulo'] : 'Sin especificar';

    $aviso = '<h2>Nueva consulta desde el sitio</h2>'
        . '<p><strong>Nombre:</strong> ' . e($valores['nombre']) . '</p>'
        . '<p><strong>Correo:</strong> ' . e($valores['correo']) . '</p>'
        . '<p><strong>Teléfono:</strong> ' . e($valores['telefono'] ?: '—') . '</p>'
        . '<p><strong>Seguro:</strong> ' . e($nombreRamo) . '</p>'
        . '<p><strong>Mensaje:</strong><br>' . nl2br(e($valores['mensaje'])) . '</p>'
        . '<p><small>Guardada en la base: ' . ($guardada ? 'sí' : 'NO — revisar') . '</small></p>';
    sn_enviar_correo($config, $config['sitio']['correo'], 'Consulta de ' . $valores['nombre'], $aviso);

    $acuse = '<p>Hola ' . e($valores['nombre']) . ',</p>'
        . '<p>Recibimos tu consulta sobre <strong>' . e($nombreRamo) . '</strong> y te vamos a responder '
        . 'dentro del horario de atención: ' . e($config['sitio']['horario']) . '.</p>'
        . '<p>Si es urgente, escribinos por WhatsApp.</p>'
        . '<p>— Seguranet</p>'
        . '<hr><p style="font-size:12px;color:#666">Este mensaje es un acuse automático. '
        . 'Tus datos se tratan conforme a nuestra política de privacidad y a la Ley 25.326; '
        . 'podés pedir su acceso, rectificación o supresión escribiendo a '
        . e($config['sitio']['correo']) . '.</p>';
    sn_enviar_correo($config, $valores['correo'], 'Recibimos tu consulta · Seguranet', $acuse);

    // Token nuevo: evita que recargar la página reenvíe la consulta.
    unset($_SESSION['sn_token']);

    return ['ok' => true, 'errores' => [], 'valores' => [],
        'mensaje' => 'Recibimos tu consulta. Te respondemos dentro del horario de atención.'];
}
