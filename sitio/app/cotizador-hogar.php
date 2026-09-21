<?php
/**
 * Cotizador de hogar (combinado familiar).
 *
 * MISMA ADVERTENCIA QUE EL DE AUTOMOTOR, Y VA EN PANTALLA: las tasas de abajo
 * son de ejemplo. No son tarifas de ninguna aseguradora y el resultado no es una
 * oferta —art. 4 de la Ley 17.418, el contrato se celebra con la propuesta
 * aceptada por el asegurador—. Sirve para dar un orden de magnitud y, sobre
 * todo, para comparar los tres planes entre sí.
 *
 * Por qué no es el mismo archivo que el de automotor: el combinado familiar se
 * tarifa sobre DOS sumas aseguradas independientes —el edificio y el contenido—
 * y cada cobertura pega sobre una o sobre otra. Un incendio se lleva el
 * edificio; un robo, nunca. Meterlo a la fuerza en la estructura de "un valor
 * por una tasa" del auto habría dado números que no se pueden explicar.
 *
 * La distinción que más cambia la cotización, y que casi ningún cotizador
 * pregunta bien: si sos inquilino no asegurás el edificio, que no es tuyo. Lo
 * tuyo es el contenido y la responsabilidad civil por lo que le pase al
 * vecino. Cobrarle a un inquilino la prima del edificio es venderle algo que
 * no puede cobrar.
 */

declare(strict_types=1);

/**
 * Los tres planes. 'piso' es el costo fijo mensual de administrar la póliza;
 * 'edificio' y 'contenido' son las tasas anuales sobre cada suma asegurada.
 */
const SN_PLANES_HOGAR = [
    'basico' => [
        'nombre' => 'Plan Básico · Incendio y responsabilidad',
        'piso' => 4000, 'edificio' => 0.0020, 'contenido' => 0.0035, 'cubre_robo' => false,
        'incluye' => [
            'Incendio del edificio y del contenido',
            'Responsabilidad civil hacia linderos',
            'Remoción de escombros',
        ],
        'resumen' => 'Lo que suele exigir el contrato de alquiler o la hipoteca.',
    ],
    'completo' => [
        'nombre' => 'Plan Completo · Robo y daños por agua',
        'piso' => 6000, 'edificio' => 0.0026, 'contenido' => 0.0180, 'cubre_robo' => true,
        'incluye' => [
            'Todo lo del Plan Básico',
            'Robo y hurto del contenido',
            'Daños por agua',
            'Cristales',
        ],
        'resumen' => 'El más elegido: cubre lo que de verdad pasa en una casa.',
    ],
    'integral' => [
        'nombre' => 'Plan Integral · Eléctricos y objetos personales',
        'piso' => 8500, 'edificio' => 0.0032, 'contenido' => 0.0280, 'cubre_robo' => true,
        'incluye' => [
            'Todo lo del Plan Completo',
            'Daños eléctricos a electrodomésticos',
            'Objetos personales fuera de la vivienda',
            'Granizo y viento',
        ],
        'resumen' => 'Suma lo que se rompe solo: un pico de tensión, el granizo.',
    ],
];

/**
 * Zona. No son los mismos números que en automotor y no es un descuido: lo que
 * mueve la prima de una casa no es el robo de vehículos sino el robo en
 * vivienda, el granizo y, en el litoral, la inundación. Por eso CABA no
 * encabeza acá: un departamento en altura con portero es de los riesgos más
 * bajos que hay.
 */
const SN_ZONA_HOGAR = [
    '06' => 1.20,  // Buenos Aires
    '02' => 1.10,  // CABA
    '82' => 1.12,  // Santa Fe
    '14' => 1.10,  // Córdoba
    '30' => 1.12,  // Entre Ríos
    '50' => 1.05,  // Mendoza
    '90' => 1.05,  // Tucumán
];
const SN_ZONA_HOGAR_RESTO = 1.00;

/** Qué mejoras de seguridad se reconocen, y cuánto descuentan. */
const SN_SEGURIDAD_HOGAR = [
    ''             => [1.00, 'sin medidas declaradas'],
    'rejas'        => [0.96, 'rejas o puerta blindada'],
    'alarma'       => [0.90, 'alarma con monitoreo'],
    'barrio'       => [0.88, 'barrio cerrado con vigilancia'],
];

/** Tipos de vivienda aceptados. */
const SN_VIVIENDAS = [
    'departamento' => 'Departamento',
    'casa'         => 'Casa',
    'pH'           => 'PH',
];

function sn_hogar_factor_vivienda(string $tipo, bool $plantaBaja): array
{
    // Una casa da más frente por donde entrar que un departamento en altura.
    // El PH queda en el medio: tiene entrada propia pero comparte medianeras.
    $base = ['casa' => 1.15, 'pH' => 1.08, 'departamento' => 1.00][$tipo] ?? 1.00;
    if ($tipo === 'departamento' && $plantaBaja) {
        // Planta baja: se entra desde la calle o desde el patio.
        return [$base * 1.10, 'departamento en planta baja'];
    }
    return [$base, SN_VIVIENDAS[$tipo] ?? $tipo];
}

function sn_hogar_factor_antiguedad(int $anios): array
{
    // La antigüedad no pega por robo sino por instalaciones: la eléctrica y la
    // de agua son las que provocan incendio y daños por agua.
    if ($anios <= 15) { return [1.00, 'hasta 15 años']; }
    if ($anios <= 40) { return [1.06, 'entre 16 y 40 años']; }
    return [1.14, 'más de 40 años'];
}

/**
 * Valida los datos del formulario.
 * @return array<string,string> campo => error. Vacío si está todo bien.
 */
function sn_hogar_errores(array $d): array
{
    $e = [];

    $tipo = (string) ($d['tipo'] ?? '');
    if (!isset(SN_VIVIENDAS[$tipo])) {
        $e['tipo'] = 'Elegí qué tipo de vivienda es.';
    }

    $condicion = (string) ($d['condicion'] ?? '');
    if (!in_array($condicion, ['propietario', 'inquilino'], true)) {
        $e['condicion'] = 'Decinos si sos propietario o inquilino: cambia qué se asegura.';
    }

    $edificio  = (float) ($d['suma_edificio'] ?? 0);
    $contenido = (float) ($d['suma_contenido'] ?? 0);

    // Un inquilino no asegura el edificio, así que acá no se le pide ni se le
    // cobra. Si mandó un valor igual, se ignora en el cálculo.
    if ($condicion === 'propietario') {
        if ($edificio < 5000000) {
            $e['suma_edificio'] = 'Poné cuánto costaría reconstruir la vivienda: al menos $ 5.000.000.';
        } elseif ($edificio > 10000000000) {
            $e['suma_edificio'] = 'Ese valor parece un error. Revisalo.';
        }
    }

    if ($contenido < 500000) {
        $e['suma_contenido'] = 'Poné cuánto vale todo lo que hay adentro: al menos $ 500.000.';
    } elseif ($contenido > 5000000000) {
        $e['suma_contenido'] = 'Ese valor parece un error. Revisalo.';
    }

    $anios = (int) ($d['antiguedad'] ?? -1);
    if ($anios < 0 || $anios > 150) {
        $e['antiguedad'] = 'La antigüedad de la vivienda tiene que estar entre 0 y 150 años.';
    }

    if (($d['seguridad'] ?? '') !== '' && !isset(SN_SEGURIDAD_HOGAR[$d['seguridad']])) {
        $e['seguridad'] = 'Elegí una opción de la lista.';
    }

    if (($d['provincia'] ?? '') === '' || !isset(SN_PROVINCIAS[$d['provincia']])) {
        $e['provincia'] = 'Elegí en qué provincia está la vivienda: la zona cambia el precio.';
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
 * Calcula la cotización de hogar.
 *
 * @return array{planes:array, factores:array, recargo:float, datos:array}
 */
function sn_cotizar_hogar(array $d): array
{
    $esPropietario = ($d['condicion'] ?? '') === 'propietario';
    // Acá se hace efectiva la regla: el inquilino no paga prima de edificio.
    $edificio  = $esPropietario ? (float) $d['suma_edificio'] : 0.0;
    $contenido = (float) $d['suma_contenido'];

    $tipo = (string) $d['tipo'];
    $plantaBaja = !empty($d['planta_baja']);
    [$fVivienda, $txtVivienda] = sn_hogar_factor_vivienda($tipo, $plantaBaja);
    [$fAnt, $txtAnt] = sn_hogar_factor_antiguedad((int) $d['antiguedad']);
    $fZona = SN_ZONA_HOGAR[$d['provincia']] ?? SN_ZONA_HOGAR_RESTO;

    $factores = [
        ['Zona',        SN_PROVINCIAS[$d['provincia']]['nombre'], $fZona],
        ['Vivienda',    $txtVivienda,                             $fVivienda],
        ['Antigüedad',  (int) $d['antiguedad'] . ' años (' . $txtAnt . ')', $fAnt],
    ];

    $recargo = $fZona * $fVivienda * $fAnt;

    // Misma regla honesta que el rastreo satelital en automotor: una alarma
    // baja el riesgo de ROBO, así que sólo descuenta en los planes que cubren
    // robo. En el Plan Básico no cambia nada y fingir un descuento ahí sería
    // mentir.
    $seguridad = (string) ($d['seguridad'] ?? '');
    [$fSeguridad, $txtSeguridad] = SN_SEGURIDAD_HOGAR[$seguridad] ?? SN_SEGURIDAD_HOGAR[''];
    if ($fSeguridad < 1.00) {
        $factores[] = ['Seguridad', $txtSeguridad . ' (descuenta en los planes con robo)', $fSeguridad];
    }

    $planes = [];
    foreach (SN_PLANES_HOGAR as $clave => $plan) {
        $descuento = ($plan['cubre_robo'] && $fSeguridad < 1.00) ? $fSeguridad : 1.00;
        $anual = ($plan['piso'] * 12
                  + $edificio * $plan['edificio']
                  + $contenido * $plan['contenido']) * $recargo * $descuento;
        $planes[$clave] = [
            'nombre'    => $plan['nombre'],
            'resumen'   => $plan['resumen'],
            'incluye'   => $plan['incluye'],
            'mensual'   => (int) round($anual / 12),
            'anual'     => (int) round($anual),
            'destacado' => $clave === 'completo',
        ];
    }

    return [
        'planes'   => $planes,
        'factores' => $factores,
        'recargo'  => $recargo,
        'datos'    => [
            'vivienda'   => $txtVivienda,
            'condicion'  => $esPropietario ? 'propietario' : 'inquilino',
            'edificio'   => (int) $edificio,
            'contenido'  => (int) $contenido,
            'provincia'  => SN_PROVINCIAS[$d['provincia']]['nombre'] ?? '',
            'localidad'  => (string) ($d['localidad'] ?? ''),
            'cp'         => sn_cp_normalizar((string) ($d['cp'] ?? '')),
        ],
    ];
}
