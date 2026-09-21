<?php
/**
 * Prueba del cotizador de consorcio.
 *
 *     php sitio/pruebas/cotizador-consorcio.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
require $raiz . '/app/ayudas.php';
require $raiz . '/app/georef.php';
require $raiz . '/app/cotizador-consorcio.php';

$fallos = 0;

function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

function base(array $cambios = []): array
{
    return array_replace([
        'unidades'      => 24,
        'pisos'         => 8,
        'antiguedad'    => 25,
        'suma_edificio' => 300000000,
        'limite_rc'     => 15000000,
        'ascensores'    => 0,
        'amenities'     => false,
        'provincia'     => '02',
        'localidad'     => 'Caballito',
        'cp'            => '',
    ], $cambios);
}

echo "\nValidación\n";

comprobar('el caso base no tiene errores', sn_consorcio_errores(base()) === [],
    implode(' / ', sn_consorcio_errores(base())));

// Una sola unidad no es propiedad horizontal: es una casa, y para eso está el
// otro cotizador. El mensaje lo dice en vez de rechazar y ya.
$unaUnidad = sn_consorcio_errores(base(['unidades' => 1]));
comprobar('rechaza un consorcio de una sola unidad', isset($unaUnidad['unidades']));
comprobar('y manda al cotizador de hogar',
    str_contains($unaUnidad['unidades'] ?? '', 'hogar'), $unaUnidad['unidades'] ?? '');

comprobar('rechaza una cantidad de unidades absurda',
    isset(sn_consorcio_errores(base(['unidades' => 5000]))['unidades']));
comprobar('rechaza cero pisos', isset(sn_consorcio_errores(base(['pisos' => 0]))['pisos']));
comprobar('rechaza una suma de edificio irrisoria',
    isset(sn_consorcio_errores(base(['suma_edificio' => 100000]))['suma_edificio']));
comprobar('rechaza una antigüedad negativa',
    isset(sn_consorcio_errores(base(['antiguedad' => -5]))['antiguedad']));
comprobar('exige un límite de RC de la lista',
    isset(sn_consorcio_errores(base(['limite_rc' => 7777]))['limite_rc']));
comprobar('acepta cada uno de los límites ofrecidos',
    array_reduce(array_keys(SN_LIMITES_RC),
        static fn(bool $ok, int $l): bool => $ok && sn_consorcio_errores(base(['limite_rc' => $l])) === [], true));
comprobar('rechaza una provincia inventada',
    isset(sn_consorcio_errores(base(['provincia' => '77']))['provincia']));
comprobar('rechaza un CPA de otra provincia',
    isset(sn_consorcio_errores(base(['cp' => 'B1900']))['cp']));

echo "\nCálculo\n";

$c = sn_cotizar_consorcio(base());
comprobar('devuelve los tres planes', count($c['planes']) === 3);
comprobar('los planes van del más barato al más caro',
    $c['planes']['basico']['anual'] < $c['planes']['completo']['anual']
    && $c['planes']['completo']['anual'] < $c['planes']['integral']['anual']);
comprobar('destaca el plan completo', $c['planes']['completo']['destacado'] === true);
comprobar('la mensual es la anual dividida en doce',
    abs($c['planes']['completo']['mensual'] * 12 - $c['planes']['completo']['anual']) <= 12);

// El número que el administrador lleva a la asamblea.
comprobar('calcula el costo por unidad y por mes',
    $c['planes']['completo']['por_unidad'] > 0);
comprobar('el costo por unidad es la mensual dividida por las unidades',
    abs($c['planes']['completo']['por_unidad'] - $c['planes']['completo']['mensual'] / 24) <= 1,
    $c['planes']['completo']['por_unidad'] . ' vs ' . ($c['planes']['completo']['mensual'] / 24));
comprobar('más unidades reparten mejor el costo fijo',
    sn_cotizar_consorcio(base(['unidades' => 120]))['planes']['completo']['por_unidad']
    < $c['planes']['completo']['por_unidad']);

echo "\nCada factor mueve la prima para donde dice\n";

$refe = sn_cotizar_consorcio(base())['planes']['completo']['anual'];

comprobar('un edificio más alto cuesta más',
    sn_cotizar_consorcio(base(['pisos' => 20]))['planes']['completo']['anual'] > $refe);
comprobar('un edificio más viejo cuesta más',
    sn_cotizar_consorcio(base(['antiguedad' => 70]))['planes']['completo']['anual'] > $refe);
comprobar('más unidades cuesta más en total',
    sn_cotizar_consorcio(base(['unidades' => 120]))['planes']['completo']['anual'] > $refe);
comprobar('un límite de RC más alto cuesta más',
    sn_cotizar_consorcio(base(['limite_rc' => 60000000]))['planes']['completo']['anual'] > $refe);
comprobar('los amenities suben la prima',
    sn_cotizar_consorcio(base(['amenities' => true]))['planes']['completo']['anual'] > $refe);
comprobar('la provincia cambia el precio',
    sn_cotizar_consorcio(base(['provincia' => '14']))['planes']['completo']['anual'] !== $refe);
comprobar('una provincia sin zona propia usa el factor neutro',
    abs(sn_cotizar_consorcio(base(['provincia' => '58', 'pisos' => 3, 'antiguedad' => 5]))['recargo'] - 1.00) < 0.0001);

echo "\nLo que no corresponde, no se cobra\n";

// Misma regla que la alarma en hogar, al revés: si el plan no cubre
// ascensores, tener ascensores no puede encarecerlo.
$sinAscensor = sn_cotizar_consorcio(base());
$conAscensor = sn_cotizar_consorcio(base(['ascensores' => 2]));
comprobar('el ascensor encarece el plan que lo cubre',
    $conAscensor['planes']['completo']['anual'] > $sinAscensor['planes']['completo']['anual']);
comprobar('el ascensor NO encarece el plan que no lo cubre',
    $conAscensor['planes']['basico']['anual'] === $sinAscensor['planes']['basico']['anual'],
    $conAscensor['planes']['basico']['anual'] . ' vs ' . $sinAscensor['planes']['basico']['anual']);
comprobar('dos ascensores cuestan más que uno',
    $conAscensor['planes']['completo']['anual']
    > sn_cotizar_consorcio(base(['ascensores' => 1]))['planes']['completo']['anual']);
comprobar('el recargo por ascensores tiene tope',
    sn_cotizar_consorcio(base(['ascensores' => 9]))['planes']['completo']['anual']
    === sn_cotizar_consorcio(base(['ascensores' => 3]))['planes']['completo']['anual']);
comprobar('el ascensor aparece entre los factores',
    str_contains(json_encode($conAscensor['factores'], JSON_UNESCAPED_UNICODE), 'Ascensores'));
comprobar('y no aparece si no hay',
    !str_contains(json_encode($sinAscensor['factores'], JSON_UNESCAPED_UNICODE), 'Ascensores'));

echo "\nEl incendio va en los tres planes\n";

// No es un detalle de redacción: el art. 2067 inc. h del Código Civil y
// Comercial pone el seguro contra incendio entre las obligaciones del
// administrador. Un plan sin incendio sería proponerle incumplir.
foreach (SN_PLANES_CONSORCIO as $clave => $plan) {
    $texto = strtolower(implode(' ', $plan['incluye']));
    comprobar("el plan $clave incluye incendio, directo o heredado",
        str_contains($texto, 'incendio') || str_contains($texto, 'todo lo del plan'));
}
comprobar('el plan más barato lo dice explícitamente',
    str_contains(strtolower(implode(' ', SN_PLANES_CONSORCIO['basico']['incluye'])), 'incendio'));

echo "\nAvisos sobre el límite de responsabilidad civil\n";

comprobar('con ascensor y el límite mínimo, avisa',
    sn_cotizar_consorcio(base(['ascensores' => 1, 'limite_rc' => 5000000]))['avisos'] !== []);
comprobar('el aviso nombra el ascensor',
    str_contains(implode(' ', sn_cotizar_consorcio(base(['ascensores' => 1, 'limite_rc' => 5000000]))['avisos']), 'ascensor'));
comprobar('con ascensor y un límite adecuado, no avisa',
    sn_cotizar_consorcio(base(['ascensores' => 1, 'limite_rc' => 30000000]))['avisos'] === []);
comprobar('con amenities y el límite mínimo, avisa',
    sn_cotizar_consorcio(base(['amenities' => true, 'limite_rc' => 5000000]))['avisos'] !== []);
comprobar('sin ascensor ni amenities, no avisa nada',
    sn_cotizar_consorcio(base(['limite_rc' => 5000000]))['avisos'] === []);

echo "\nResumen de los datos\n";

$c = sn_cotizar_consorcio(base(['cp' => 'c1425dke']));
comprobar('normaliza el código postal', $c['datos']['cp'] === 'C1425DKE', $c['datos']['cp']);
comprobar('guarda el nombre de la provincia',
    $c['datos']['provincia'] === 'Ciudad Autónoma de Buenos Aires');
comprobar('guarda el límite de RC elegido', $c['datos']['limite_rc'] === 15000000);
comprobar('guarda las unidades', $c['datos']['unidades'] === 24);

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
