<?php
/**
 * Cotizador de automotor.
 *
 * ADVERTENCIA QUE TAMBIÉN VA EN PANTALLA: la tabla de abajo es de ejemplo. No
 * son tarifas de ninguna aseguradora y el resultado no es una oferta —conforme
 * el art. 4 de la Ley 17.418, el contrato se celebra con la propuesta aceptada
 * por el asegurador—. Sirve para que la persona tenga un orden de magnitud y
 * para comparar los cuatro planes entre sí, que es la decisión que de verdad
 * tiene que tomar.
 *
 * Cuando haya acuerdo con aseguradoras, esta función se reemplaza por la
 * llamada a sus tarifarios y la interfaz no cambia: entra el mismo arreglo de
 * datos y sale la misma estructura.
 *
 * El cálculo es deliberadamente explicable. Un cotizador que tira un número sin
 * decir de dónde sale no ayuda a decidir, y en seguros la desconfianza se paga
 * con la venta.
 */

declare(strict_types=1);

/**
 * Los cuatro planes, en el mismo orden y con los mismos nombres que la página
 * de Coberturas. 'piso' es el costo fijo mensual de administrar la póliza;
 * 'tasa' es la porción anual de la suma asegurada.
 */
const SN_PLANES = [
    'A' => [
        'nombre' => 'Plan A · Responsabilidad Civil',
        'piso' => 9000, 'tasa' => 0.008, 'cubre_robo' => false,
        'incluye' => ['Responsabilidad civil hacia terceros', '3 remolques por año'],
        'resumen' => 'Lo mínimo que exige la ley para circular.',
    ],
    'B' => [
        'nombre' => 'Plan B · Todo Total',
        'piso' => 11000, 'tasa' => 0.018, 'cubre_robo' => true,
        'incluye' => ['Todo lo del Plan A', 'Robo total', 'Incendio total', 'Destrucción total', '5 remolques por año'],
        'resumen' => 'Te cubre si perdés el auto entero.',
    ],
    'C' => [
        'nombre' => 'Plan C · Tercero Completo',
        'piso' => 13000, 'tasa' => 0.030, 'cubre_robo' => true,
        'incluye' => ['Todo lo del Plan B', 'Robo parcial', 'Incendio parcial', '10 remolques por año'],
        'resumen' => 'El más elegido: cubre lo grave sin llegar a todo riesgo.',
    ],
    'D' => [
        'nombre' => 'Plan D · Todo Riesgo',
        'piso' => 16000, 'tasa' => 0.046, 'cubre_robo' => true,
        'incluye' => ['Todo lo del Plan C', 'Daños parciales', 'Granizo', 'Remolque ilimitado'],
        'resumen' => 'Cubre también los golpes de tu propio auto, con franquicia.',
    ],
];

/**
 * Zona. La provincia donde duerme el auto mueve la prima más que casi
 * cualquier otro dato: cambian el robo, el granizo y la siniestralidad.
 * Los valores son de ejemplo, pero el orden refleja el mercado: el AMBA
 * concentra el robo de vehículos.
 */
const SN_ZONA = [
    '02' => 1.35,  // CABA
    '06' => 1.25,  // Buenos Aires
    '82' => 1.15,  // Santa Fe
    '14' => 1.12,  // Córdoba
    '50' => 1.05,  // Mendoza
    '90' => 1.05,  // Tucumán
];
const SN_ZONA_RESTO = 1.00;

/** Devuelve el catálogo de marcas y modelos. */
function sn_catalogo(): array
{
    static $catalogo = null;
    if ($catalogo === null) {
        $crudo = @file_get_contents(__DIR__ . '/datos/catalogo-vehiculos.json');
        $datos = is_string($crudo) ? json_decode($crudo, true) : null;
        $catalogo = is_array($datos) && isset($datos['marcas']) ? $datos['marcas'] : [];
    }
    return $catalogo;
}

/** Los modelos de una marca, o [] si la marca no está. */
function sn_modelos(string $marca): array
{
    return sn_catalogo()[$marca] ?? [];
}

function sn_factor_antiguedad(int $antiguedad): array
{
    if ($antiguedad <= 3)  { return [1.00, '0 a 3 años']; }
    if ($antiguedad <= 10) { return [1.06, '4 a 10 años']; }
    if ($antiguedad <= 20) { return [1.15, '11 a 20 años']; }
    return [1.25, 'más de 20 años'];
}

function sn_factor_edad(int $edad): array
{
    if ($edad < 25)  { return [1.35, 'menor de 25']; }
    if ($edad <= 30) { return [1.15, 'entre 25 y 30']; }
    if ($edad <= 65) { return [1.00, 'entre 31 y 65']; }
    return [1.10, 'mayor de 65'];
}

/**
 * Valida los datos del formulario.
 * @return array<string,string> campo => error. Vacío si está todo bien.
 */
function sn_cotizacion_errores(array $d, int $anioActual): array
{
    $e = [];
    $catalogo = sn_catalogo();

    if (($d['marca'] ?? '') === '' || !isset($catalogo[$d['marca']])) {
        $e['marca'] = 'Elegí una marca de la lista.';
    }
    if (($d['modelo'] ?? '') === '') {
        $e['modelo'] = 'Elegí o escribí el modelo.';
    }
    $anio = (int) ($d['anio'] ?? 0);
    if ($anio < 1980 || $anio > $anioActual + 1) {
        $e['anio'] = 'El año tiene que estar entre 1980 y ' . ($anioActual + 1) . '.';
    }
    $valor = (float) ($d['valor'] ?? 0);
    if ($valor < 500000) {
        $e['valor'] = 'Poné cuánto vale hoy un auto como el tuyo: al menos $ 500.000.';
    }
    if ($valor > 1000000000) {
        $e['valor'] = 'Ese valor parece un error. Revisalo.';
    }
    $edad = (int) ($d['edad'] ?? 0);
    if ($edad < 18 || $edad > 99) {
        $e['edad'] = 'La edad del conductor tiene que estar entre 18 y 99.';
    }
    if (($d['provincia'] ?? '') === '' || !isset(SN_PROVINCIAS[$d['provincia']])) {
        $e['provincia'] = 'Elegí dónde duerme el auto: la zona cambia el precio.';
    }
    if (($d['cp'] ?? '') !== '') {
        $cp = sn_cp_validar((string) $d['cp'], (string) ($d['provincia'] ?? ''));
        if (!$cp['ok']) {
            $e['cp'] = $cp['error'] . ($cp['sugerencia'] !== '' ? ' ¿Quisiste poner ' . $cp['sugerencia'] . '?' : '');
        }
    }
    return $e;
}

/**
 * Calcula la cotización.
 *
 * @return array{planes:array, factores:array, recargo:float, datos:array}
 */
function sn_cotizar(array $d, int $anioActual): array
{
    $valor = (float) $d['valor'];
    $antiguedad = max(0, $anioActual - (int) $d['anio']);
    $edad = (int) $d['edad'];

    [$fAnt, $txtAnt] = sn_factor_antiguedad($antiguedad);
    [$fEdad, $txtEdad] = sn_factor_edad($edad);
    $fUso = ($d['uso'] ?? 'particular') === 'comercial' ? 1.30 : 1.00;
    $fZona = SN_ZONA[$d['provincia']] ?? SN_ZONA_RESTO;

    // El GNC sube el riesgo de incendio; la cláusula de ajuste actualiza la
    // suma asegurada contra la inflación y por eso cuesta más.
    $fGnc    = !empty($d['gnc'])    ? 1.08 : 1.00;
    $fAjuste = !empty($d['ajuste']) ? 1.06 : 1.00;

    $factores = [
        ['Zona',                SN_PROVINCIAS[$d['provincia']]['nombre'], $fZona],
        ['Antigüedad',          $antiguedad . ' años (' . $txtAnt . ')',  $fAnt],
        ['Edad del conductor',  $edad . ' (' . $txtEdad . ')',            $fEdad],
        ['Uso',                 ($d['uso'] ?? 'particular') === 'comercial' ? 'comercial' : 'particular', $fUso],
    ];
    if (!empty($d['gnc']))    { $factores[] = ['Equipo de GNC', 'sí', $fGnc]; }
    if (!empty($d['ajuste'])) { $factores[] = ['Cláusula de ajuste', 'sí', $fAjuste]; }

    $recargo = $fZona * $fAnt * $fEdad * $fUso * $fGnc * $fAjuste;

    // El rastreo satelital baja el riesgo de robo, así que sólo descuenta en
    // los planes que cubren robo. En responsabilidad civil no cambia nada, y
    // fingir un descuento ahí sería mentir.
    $tieneRastreo = !empty($d['rastreo']);
    if ($tieneRastreo) {
        $factores[] = ['Rastreo satelital', 'sí (descuenta en los planes con robo)', 0.92];
    }

    $planes = [];
    foreach (SN_PLANES as $clave => $plan) {
        $descuento = ($tieneRastreo && $plan['cubre_robo']) ? 0.92 : 1.00;
        $anual = ($plan['piso'] * 12 + $valor * $plan['tasa']) * $recargo * $descuento;
        $planes[$clave] = [
            'nombre'   => $plan['nombre'],
            'resumen'  => $plan['resumen'],
            'incluye'  => $plan['incluye'],
            'mensual'  => (int) round($anual / 12),
            'anual'    => (int) round($anual),
            'destacado'=> $clave === 'C',
        ];
    }

    return [
        'planes'   => $planes,
        'factores' => $factores,
        'recargo'  => $recargo,
        'datos'    => [
            'vehiculo'   => trim(($d['marca'] ?? '') . ' ' . ($d['modelo'] ?? '') . ' ' . ($d['anio'] ?? '')),
            'valor'      => (int) $valor,
            'provincia'  => SN_PROVINCIAS[$d['provincia']]['nombre'] ?? '',
            'localidad'  => (string) ($d['localidad'] ?? ''),
            'cp'         => sn_cp_normalizar((string) ($d['cp'] ?? '')),
        ],
    ];
}
