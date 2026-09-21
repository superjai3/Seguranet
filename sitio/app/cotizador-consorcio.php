<?php
/**
 * Cotizador de consorcio (seguro integral de propiedad horizontal).
 *
 * MISMA ADVERTENCIA QUE LOS OTROS DOS, Y VA EN PANTALLA: las tasas son de
 * ejemplo. No son tarifas de ninguna aseguradora y el resultado no es una
 * oferta —art. 4 de la Ley 17.418—.
 *
 * Por qué es el tercer archivo y no una variante del de hogar: un consorcio no
 * se tarifa por vivienda sino por EDIFICIO, y el componente que más pesa no es
 * el incendio sino la responsabilidad civil. Un incendio se lleva, como mucho,
 * el edificio; un ascensor que se cae o una maceta que vuela de un balcón
 * generan un reclamo que no tiene techo salvo el que le pongan a la póliza. Por
 * eso acá hay una suma asegurada que en hogar no existe: el límite de
 * responsabilidad civil, que se elige aparte y es la decisión de fondo.
 *
 * Lo otro que lo distingue: en consorcio buena parte de la cobertura no es
 * optativa. El Código Civil y Comercial (art. 2067 inc. h) pone entre las
 * obligaciones del administrador contratar el seguro del edificio contra
 * incendio; en la Ciudad de Buenos Aires la Ley 941 lo controla. Un cotizador
 * que ofrezca "sacarle" el incendio para bajar el precio le estaría proponiendo
 * al administrador incumplir. Por eso el incendio está en los tres planes.
 */

declare(strict_types=1);

/**
 * Los tres planes. 'piso' es el costo fijo mensual de administrar la póliza,
 * 'unidad' lo que suma cada unidad funcional, 'edificio' la tasa anual sobre la
 * suma asegurada de las partes comunes y 'rc' la tasa anual sobre el límite de
 * responsabilidad civil elegido.
 */
const SN_PLANES_CONSORCIO = [
    'basico' => [
        'nombre' => 'Plan Básico · Lo que exige la ley',
        'piso' => 12000, 'unidad' => 350, 'edificio' => 0.0022, 'rc' => 0.0090,
        'cubre_ascensor' => false, 'cubre_robo' => false,
        'incluye' => [
            'Incendio del edificio y de las partes comunes',
            'Responsabilidad civil del consorcio hacia terceros',
            'Responsabilidad civil hacia linderos',
            'Remoción de escombros',
        ],
        'resumen' => 'El mínimo que el administrador está obligado a contratar.',
    ],
    'completo' => [
        'nombre' => 'Plan Completo · Ascensores y agua',
        'piso' => 18000, 'unidad' => 620, 'edificio' => 0.0030, 'rc' => 0.0135,
        'cubre_ascensor' => true, 'cubre_robo' => false,
        'incluye' => [
            'Todo lo del Plan Básico',
            'Responsabilidad civil por ascensores y montacargas',
            'Daños por agua en espacios comunes',
            'Cristales y tanques',
        ],
        'resumen' => 'El más elegido: cubre los dos reclamos que más se repiten.',
    ],
    'integral' => [
        'nombre' => 'Plan Integral · Todo el riesgo común',
        'piso' => 25000, 'unidad' => 940, 'edificio' => 0.0038, 'rc' => 0.0190,
        'cubre_ascensor' => true, 'cubre_robo' => true,
        'incluye' => [
            'Todo lo del Plan Completo',
            'Robo de bienes comunes y de la portería',
            'Daños eléctricos a bombas, portones y equipos comunes',
            'Accidentes personales de visitantes en áreas comunes',
            'Amenities: SUM, pileta, gimnasio',
        ],
        'resumen' => 'Para edificios con amenities y equipamiento común caro.',
    ],
];

/**
 * Límites de responsabilidad civil que se pueden elegir. Es la decisión de
 * fondo del ramo y por eso se elige a mano, no sale de una fórmula: un reclamo
 * de un tercero no tiene techo salvo el que le ponga la póliza.
 */
const SN_LIMITES_RC = [
     5000000 => '$ 5.000.000 · edificios chicos sin ascensor',
    15000000 => '$ 15.000.000 · lo habitual en edificios de hasta 30 unidades',
    30000000 => '$ 30.000.000 · recomendado con ascensor o amenities',
    60000000 => '$ 60.000.000 · torres y edificios sobre la vía pública',
];

/**
 * Zona. Acá lo que pesa no es el robo sino la exposición a reclamos de
 * terceros: un edificio sobre una avenida del centro tiene mucha más gente
 * pasando por debajo de sus balcones que uno en un barrio residencial.
 */
const SN_ZONA_CONSORCIO = [
    '02' => 1.25,  // CABA
    '06' => 1.15,  // Buenos Aires
    '82' => 1.08,  // Santa Fe
    '14' => 1.08,  // Córdoba
    '50' => 1.04,  // Mendoza
];
const SN_ZONA_CONSORCIO_RESTO = 1.00;

function sn_consorcio_factor_antiguedad(int $anios): array
{
    // En un edificio la antigüedad pesa más que en una casa: las instalaciones
    // comunes —columnas de agua, tableros, el ascensor— son las que fallan, y
    // cuando fallan el reclamo no es de un propietario sino de todos.
    if ($anios <= 10) { return [1.00, 'hasta 10 años']; }
    if ($anios <= 30) { return [1.08, 'entre 11 y 30 años']; }
    if ($anios <= 50) { return [1.18, 'entre 31 y 50 años']; }
    return [1.30, 'más de 50 años'];
}

function sn_consorcio_factor_altura(int $pisos): array
{
    if ($pisos <= 3)  { return [1.00, 'hasta 3 pisos']; }
    if ($pisos <= 8)  { return [1.10, 'entre 4 y 8 pisos']; }
    if ($pisos <= 15) { return [1.22, 'entre 9 y 15 pisos']; }
    return [1.35, 'más de 15 pisos'];
}

/**
 * Valida los datos del formulario.
 * @return array<string,string> campo => error. Vacío si está todo bien.
 */
function sn_consorcio_errores(array $d): array
{
    $e = [];

    $unidades = (int) ($d['unidades'] ?? 0);
    if ($unidades < 2) {
        // Con una sola unidad no hay propiedad horizontal: es una casa, y para
        // eso está el cotizador de hogar.
        $e['unidades'] = 'Un consorcio tiene al menos 2 unidades. ¿Buscabas el seguro de hogar?';
    } elseif ($unidades > 2000) {
        $e['unidades'] = 'Ese número parece un error. Revisalo.';
    }

    $pisos = (int) ($d['pisos'] ?? 0);
    if ($pisos < 1 || $pisos > 60) {
        $e['pisos'] = 'La cantidad de pisos tiene que estar entre 1 y 60.';
    }

    $anios = (int) ($d['antiguedad'] ?? -1);
    if ($anios < 0 || $anios > 150) {
        $e['antiguedad'] = 'La antigüedad del edificio tiene que estar entre 0 y 150 años.';
    }

    $edificio = (float) ($d['suma_edificio'] ?? 0);
    if ($edificio < 10000000) {
        $e['suma_edificio'] = 'Poné cuánto costaría reconstruir las partes comunes: al menos $ 10.000.000.';
    } elseif ($edificio > 100000000000) {
        $e['suma_edificio'] = 'Ese valor parece un error. Revisalo.';
    }

    $rc = (int) ($d['limite_rc'] ?? 0);
    if (!isset(SN_LIMITES_RC[$rc])) {
        $e['limite_rc'] = 'Elegí un límite de responsabilidad civil de la lista.';
    }

    if (($d['provincia'] ?? '') === '' || !isset(SN_PROVINCIAS[$d['provincia']])) {
        $e['provincia'] = 'Elegí en qué provincia está el edificio.';
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
 * Calcula la cotización de consorcio.
 *
 * @return array{planes:array, factores:array, recargo:float, datos:array, avisos:array}
 */
function sn_cotizar_consorcio(array $d): array
{
    $unidades  = (int) $d['unidades'];
    $pisos     = (int) $d['pisos'];
    $edificio  = (float) $d['suma_edificio'];
    $limiteRc  = (int) $d['limite_rc'];
    $ascensores = max(0, (int) ($d['ascensores'] ?? 0));
    $tieneAmenities = !empty($d['amenities']);

    [$fAnt, $txtAnt]   = sn_consorcio_factor_antiguedad((int) $d['antiguedad']);
    [$fAlt, $txtAlt]   = sn_consorcio_factor_altura($pisos);
    $fZona = SN_ZONA_CONSORCIO[$d['provincia']] ?? SN_ZONA_CONSORCIO_RESTO;

    $factores = [
        ['Zona',       SN_PROVINCIAS[$d['provincia']]['nombre'], $fZona],
        ['Altura',     $pisos . ' pisos (' . $txtAlt . ')',      $fAlt],
        ['Antigüedad', (int) $d['antiguedad'] . ' años (' . $txtAnt . ')', $fAnt],
    ];

    $recargo = $fZona * $fAlt * $fAnt;

    // Cada ascensor suma exposición, pero sólo en los planes que lo cubren:
    // misma regla que la alarma en hogar y el rastreo en automotor, al revés.
    // Cobrar por un ascensor en un plan que no cubre ascensores sería cobrar
    // por nada.
    $fAscensor = $ascensores > 0 ? 1 + min(0.24, 0.08 * $ascensores) : 1.00;
    if ($ascensores > 0) {
        $factores[] = ['Ascensores',
            $ascensores . ($ascensores === 1 ? ' ascensor' : ' ascensores')
                . ' (sólo en los planes que los cubren)', $fAscensor];
    }

    $fAmenities = $tieneAmenities ? 1.12 : 1.00;
    if ($tieneAmenities) {
        $factores[] = ['Amenities', 'SUM, pileta o gimnasio', $fAmenities];
    }

    $planes = [];
    foreach (SN_PLANES_CONSORCIO as $clave => $plan) {
        $porAscensor = $plan['cubre_ascensor'] ? $fAscensor : 1.00;
        $anual = ($plan['piso'] * 12
                  + $plan['unidad'] * 12 * $unidades
                  + $edificio * $plan['edificio']
                  + $limiteRc * $plan['rc']) * $recargo * $porAscensor * $fAmenities;
        $planes[$clave] = [
            'nombre'    => $plan['nombre'],
            'resumen'   => $plan['resumen'],
            'incluye'   => $plan['incluye'],
            'mensual'   => (int) round($anual / 12),
            'anual'     => (int) round($anual),
            // Por unidad y por mes: es el número que el administrador lleva a
            // la asamblea, porque es lo que va a pagar cada propietario en la
            // expensa. Sin esto, la cifra grande asusta y no dice nada.
            'por_unidad'=> (int) round($anual / 12 / $unidades),
            'destacado' => $clave === 'completo',
        ];
    }

    // Avisos que dependen del edificio, no del plan. No son "ventas": un
    // consorcio con ascensor y el límite mínimo está mal cubierto, y el
    // administrador responde con su propio patrimonio si no lo advierte.
    $avisos = [];
    if ($ascensores > 0 && $limiteRc < 30000000) {
        $avisos[] = 'Con ascensor conviene un límite de responsabilidad civil de '
                  . '$ 30.000.000 o más: los reclamos por accidentes en ascensores '
                  . 'son los que se van más arriba.';
    }
    if ($tieneAmenities && $limiteRc < 30000000) {
        $avisos[] = 'Con pileta o SUM, el límite de responsabilidad civil elegido '
                  . 'puede quedar corto frente a un accidente de un tercero.';
    }

    return [
        'planes'   => $planes,
        'factores' => $factores,
        'recargo'  => $recargo,
        'avisos'   => $avisos,
        'datos'    => [
            'unidades'  => $unidades,
            'pisos'     => $pisos,
            'edificio'  => (int) $edificio,
            'limite_rc' => $limiteRc,
            'provincia' => SN_PROVINCIAS[$d['provincia']]['nombre'] ?? '',
            'localidad' => (string) ($d['localidad'] ?? ''),
            'cp'        => sn_cp_normalizar((string) ($d['cp'] ?? '')),
        ],
    ];
}
