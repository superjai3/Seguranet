<?php
/**
 * Pólizas del usuario y avisos de vencimiento.
 *
 * Acá está el diferencial del proyecto, así que conviene decir para qué sirve
 * cada cosa: un seguro se contrata una vez y se renueva todos los años, y la
 * mayoría de la gente se entera de que vence cuando ya venció. Si Seguranet
 * avisa a tiempo —aunque la póliza sea de otra compañía— se gana el derecho a
 * ofrecer la renovación.
 *
 * Por eso se aceptan pólizas contratadas en otro lado. Dar valor antes de
 * vender no es generosidad: es cómo se consigue estar ahí el día que decide.
 */

declare(strict_types=1);

/**
 * Cuándo se avisa, en días antes del vencimiento.
 *
 * De mayor a menor, y no es arbitrario: a 60 días hay tiempo de cotizar en
 * varias compañías, a 30 de decidir, a 15 de resolver, y a 7 es el último
 * llamado. Más avisos que estos serían acoso.
 */
const SN_HITOS_AVISO = [60, 30, 15, 7];

/**
 * Aseguradoras del mercado argentino, para el desplegable.
 *
 * Es una ayuda para que el usuario registre SU póliza, no una lista de
 * compañías con las que Seguranet opere: no se muestran logos ni se insinúa
 * relación comercial alguna. La lista no es exhaustiva y el campo acepta
 * cualquier texto.
 */
const SN_ASEGURADORAS = [
    'Allianz', 'ATM Seguros', 'Berkley International', 'Boston Seguros', 'Chubb',
    'El Norte Seguros', 'Experta Seguros', 'Federación Patronal', 'Galicia Seguros',
    'HDI Seguros', 'Integrity Seguros', 'La Caja', 'La Holando Sudamericana',
    'La Segunda', 'Mapfre', 'Mercantil Andina', 'Meridional Seguros',
    'Nación Seguros', 'Orbis Seguros', 'Paraná Seguros', 'Provincia Seguros',
    'Río Uruguay Seguros', 'San Cristóbal', 'Sancor Seguros', 'Seguros Rivadavia',
    'Sura', 'Triunfo Seguros', 'Zurich',
];

/**
 * Días que faltan para el vencimiento. Negativo si ya venció.
 */
function sn_dias_para_vencer(string $vigenciaHasta, ?string $hoy = null): int
{
    $fin = new DateTimeImmutable($vigenciaHasta);
    $ahora = new DateTimeImmutable($hoy ?? sn_ahora());
    // Se compara por día, no por hora: una póliza que vence hoy vence hoy,
    // sean las 9 o las 23.
    $fin = $fin->setTime(0, 0);
    $ahora = $ahora->setTime(0, 0);
    return (int) $ahora->diff($fin)->format('%r%a');
}

/**
 * Estado legible de una póliza, para mostrar.
 * @return array{clave:string, texto:string, tono:string}
 */
function sn_estado_poliza(array $poliza, ?string $hoy = null): array
{
    if ($poliza['estado'] === 'dada_de_baja') {
        return ['clave' => 'baja', 'texto' => 'Dada de baja', 'tono' => 'legal'];
    }
    if ($poliza['estado'] === 'renovada') {
        return ['clave' => 'renovada', 'texto' => 'Renovada', 'tono' => 'ok'];
    }

    $dias = sn_dias_para_vencer($poliza['vigencia_hasta'], $hoy);
    if ($dias < 0) {
        return ['clave' => 'vencida', 'texto' => 'Vencida hace ' . abs($dias) . ' días', 'tono' => 'error'];
    }
    if ($dias === 0) {
        return ['clave' => 'vence_hoy', 'texto' => 'Vence hoy', 'tono' => 'error'];
    }
    if ($dias <= 30) {
        return ['clave' => 'por_vencer', 'texto' => 'Vence en ' . $dias . ' días', 'tono' => 'error'];
    }
    if ($dias <= 60) {
        return ['clave' => 'proxima', 'texto' => 'Vence en ' . $dias . ' días', 'tono' => 'dato'];
    }
    return ['clave' => 'vigente', 'texto' => 'Vigente, vence en ' . $dias . ' días', 'tono' => 'ok'];
}

/**
 * Qué aviso corresponde mandar hoy para una póliza, o null si ninguno.
 *
 * Devuelve el hito más chico que ya se alcanzó y que todavía no se envió. Es
 * decir: si el cron estuvo caído una semana y se pasaron los 30 días, al
 * volver manda el de 15 y no los tres atrasados de golpe.
 *
 * @param int[] $yaEnviados hitos ya avisados para esa póliza
 */
function sn_hito_a_enviar(int $diasParaVencer, array $yaEnviados): ?int
{
    if ($diasParaVencer < 0) {
        return null;  // vencida: ya no es un aviso de vencimiento
    }
    $candidatos = [];
    foreach (SN_HITOS_AVISO as $hito) {
        if ($diasParaVencer <= $hito && !in_array($hito, $yaEnviados, true)) {
            $candidatos[] = $hito;
        }
    }
    return $candidatos === [] ? null : min($candidatos);
}

/** Valida el alta o la edición de una póliza. */
function sn_poliza_errores(array $d, array $ramos): array
{
    $e = [];
    if (($d['ramo'] ?? '') === '' || !isset($ramos[$d['ramo']])) {
        $e['ramo'] = 'Elegí qué tipo de seguro es.';
    }
    if (($d['vigencia_hasta'] ?? '') === '') {
        $e['vigencia_hasta'] = 'Decinos cuándo vence: es lo que nos permite avisarte.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['vigencia_hasta'])) {
        $e['vigencia_hasta'] = 'La fecha no tiene un formato válido.';
    } else {
        $hasta = new DateTimeImmutable($d['vigencia_hasta']);
        $limite = new DateTimeImmutable('+10 years');
        if ($hasta > $limite) {
            $e['vigencia_hasta'] = 'Esa fecha está demasiado lejos. Revisala.';
        }
        if (($d['vigencia_desde'] ?? '') !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['vigencia_desde'])
            && new DateTimeImmutable($d['vigencia_desde']) > $hasta) {
            $e['vigencia_desde'] = 'El inicio de vigencia no puede ser posterior al vencimiento.';
        }
    }
    if (($d['prima_mensual'] ?? '') !== '' && (float) $d['prima_mensual'] < 0) {
        $e['prima_mensual'] = 'La prima no puede ser negativa.';
    }
    if (mb_strlen((string) ($d['aseguradora'] ?? '')) > 120) {
        $e['aseguradora'] = 'El nombre de la aseguradora es demasiado largo.';
    }
    return $e;
}

/** Guarda una póliza nueva. Devuelve el id, o null si falló. */
function sn_guardar_poliza(PDO $pdo, int $usuarioId, array $d): ?int
{
    try {
        $pdo->prepare(
            'INSERT INTO polizas (usuario_id, ramo, aseguradora, numero, detalle,
                                  vigencia_desde, vigencia_hasta, prima_mensual,
                                  origen, estado, notas, creada_en, actualizada_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "externa", "vigente", ?, ?, ?)'
        )->execute([
            $usuarioId,
            $d['ramo'],
            (string) ($d['aseguradora'] ?? ''),
            (string) ($d['numero'] ?? ''),
            (string) ($d['detalle'] ?? ''),
            ($d['vigencia_desde'] ?? '') !== '' ? $d['vigencia_desde'] : null,
            $d['vigencia_hasta'],
            ($d['prima_mensual'] ?? '') !== '' ? (float) $d['prima_mensual'] : null,
            (string) ($d['notas'] ?? ''),
            sn_ahora(), sn_ahora(),
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $ex) {
        error_log('Seguranet poliza: ' . $ex->getMessage());
        return null;
    }
}

/** Las pólizas de un usuario, la que vence antes primero. */
function sn_polizas_de(PDO $pdo, int $usuarioId): array
{
    try {
        $c = $pdo->prepare(
            'SELECT * FROM polizas WHERE usuario_id = ?
             ORDER BY FIELD(estado, "vigente", "renovada", "dada_de_baja"), vigencia_hasta ASC'
        );
        $c->execute([$usuarioId]);
        return $c->fetchAll();
    } catch (PDOException $ex) {
        error_log('Seguranet polizas: ' . $ex->getMessage());
        return [];
    }
}

/** Marca una póliza como dada de baja o renovada. */
function sn_cambiar_estado_poliza(PDO $pdo, int $polizaId, int $usuarioId, string $estado): bool
{
    if (!in_array($estado, ['vigente', 'renovada', 'dada_de_baja'], true)) {
        return false;
    }
    try {
        // El usuario_id en el WHERE no es decorativo: sin él, cualquiera podría
        // cambiar el estado de la póliza de otro mandando otro id.
        $c = $pdo->prepare('UPDATE polizas SET estado = ?, actualizada_en = ? WHERE id = ? AND usuario_id = ?');
        $c->execute([$estado, sn_ahora(), $polizaId, $usuarioId]);
        return $c->rowCount() > 0;
    } catch (PDOException $ex) {
        error_log('Seguranet estado poliza: ' . $ex->getMessage());
        return false;
    }
}

/** Texto del aviso de vencimiento, según cuánto falte. */
function sn_texto_aviso(array $poliza, int $hito, array $ramos, array $config): array
{
    $ramo = $ramos[$poliza['ramo']]['titulo'] ?? $poliza['ramo'];
    $quien = $poliza['aseguradora'] !== '' ? ' con ' . $poliza['aseguradora'] : '';

    $asunto = match (true) {
        $hito >= 60 => 'Tu ' . mb_strtolower($ramo) . ' vence en dos meses',
        $hito >= 30 => 'Tu ' . mb_strtolower($ramo) . ' vence en un mes',
        $hito >= 15 => 'Quedan 15 días de tu ' . mb_strtolower($ramo),
        default     => 'Última semana de tu ' . mb_strtolower($ramo),
    };

    $urgencia = match (true) {
        $hito >= 60 => 'Es el mejor momento para comparar: con dos meses hay tiempo de pedir '
                     . 'presupuesto en varias compañías sin apuro.',
        $hito >= 30 => 'Si querés comparar antes de renovar, este es el momento de arrancar.',
        $hito >= 15 => 'Si vas a cambiar de compañía, conviene resolverlo esta quincena: '
                     . 'la cobertura nueva tiene que empezar el día que termina la vieja.',
        default     => 'Queda menos de una semana. Si no hacés nada, revisá que la renovación '
                     . 'automática esté al día para no quedarte sin cobertura.',
    };

    $cuerpo = '<p>Hola,</p>'
        . '<p>Tu <strong>' . e($ramo) . '</strong>' . e($quien) . ' vence el <strong>'
        . e(date('d/m/Y', strtotime($poliza['vigencia_hasta']))) . '</strong>.</p>'
        . '<p>' . e($urgencia) . '</p>'
        . '<p><a href="' . e(url('/cotizar/auto', $config)) . '">Comparar precios</a> · '
        . '<a href="' . e(url('/contacto', $config)) . '">Pedirnos una propuesta</a></p>'
        . '<p>— Seguranet</p>'
        . '<hr><p style="font-size:12px;color:#666">Recibís este aviso porque registraste esta '
        . 'póliza en tu cuenta. Podés borrarla o darla de baja desde '
        . '<a href="' . e(url('/cuenta', $config)) . '">tu panel</a> y dejás de recibirlos.</p>';

    return ['asunto' => $asunto . ' · Seguranet', 'cuerpo' => $cuerpo];
}
