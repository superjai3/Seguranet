<?php
/**
 * Prueba del cotizador de hogar.
 *
 * Lo que se verifica no es que los números den "bien" —son de ejemplo— sino
 * que el cálculo sea coherente y explicable: que cada factor mueva la prima
 * en la dirección que dice, que lo que no corresponde no se cobre, y que un
 * formulario mal llenado no llegue nunca al cálculo.
 *
 *     php sitio/pruebas/cotizador-hogar.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
require $raiz . '/app/ayudas.php';
require $raiz . '/app/georef.php';
require $raiz . '/app/cotizador-hogar.php';

$fallos = 0;

function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

/** Un caso base al que después se le cambia un dato por vez. */
function base(array $cambios = []): array
{
    return array_replace([
        'tipo'           => 'departamento',
        'condicion'      => 'propietario',
        'suma_edificio'  => 40000000,
        'suma_contenido' => 8000000,
        'antiguedad'     => 10,
        'seguridad'      => '',
        'planta_baja'    => false,
        'provincia'      => '02',
        'localidad'      => 'Palermo',
        'cp'             => '',
    ], $cambios);
}

echo "\nValidación\n";

comprobar('el caso base no tiene errores', sn_hogar_errores(base()) === [],
    implode(' / ', sn_hogar_errores(base())));
comprobar('exige tipo de vivienda', isset(sn_hogar_errores(base(['tipo' => 'carpa']))['tipo']));
comprobar('exige decir si es propietario o inquilino',
    isset(sn_hogar_errores(base(['condicion' => '']))['condicion']));
comprobar('rechaza una suma de contenido irrisoria',
    isset(sn_hogar_errores(base(['suma_contenido' => 1000]))['suma_contenido']));
comprobar('rechaza una suma de contenido absurda',
    isset(sn_hogar_errores(base(['suma_contenido' => 9000000000]))['suma_contenido']));
comprobar('rechaza una antigüedad negativa',
    isset(sn_hogar_errores(base(['antiguedad' => -1]))['antiguedad']));
comprobar('rechaza una provincia inventada',
    isset(sn_hogar_errores(base(['provincia' => '99']))['provincia']));
comprobar('rechaza una medida de seguridad que no está en la lista',
    isset(sn_hogar_errores(base(['seguridad' => 'perro']))['seguridad']));

// Al propietario se le pide la suma del edificio; al inquilino, no. Es la
// diferencia que define el producto.
comprobar('al propietario le exige la suma del edificio',
    isset(sn_hogar_errores(base(['suma_edificio' => 0]))['suma_edificio']));
comprobar('al inquilino NO le exige la suma del edificio',
    !isset(sn_hogar_errores(base(['condicion' => 'inquilino', 'suma_edificio' => 0]))['suma_edificio']));

echo "\nCódigo postal\n";

comprobar('acepta un CPA que corresponde a la provincia',
    !isset(sn_hogar_errores(base(['cp' => 'C1425DKE']))['cp']));
comprobar('rechaza un CPA de otra provincia',
    isset(sn_hogar_errores(base(['cp' => 'B1900']))['cp']));
comprobar('el CP vacío no es un error', !isset(sn_hogar_errores(base())['cp']));

echo "\nCálculo\n";

$c = sn_cotizar_hogar(base());
comprobar('devuelve los tres planes', count($c['planes']) === 3, (string) count($c['planes']));
comprobar('los planes vienen del más barato al más caro',
    $c['planes']['basico']['anual'] < $c['planes']['completo']['anual']
    && $c['planes']['completo']['anual'] < $c['planes']['integral']['anual']);
comprobar('destaca el plan completo', $c['planes']['completo']['destacado'] === true);
comprobar('la mensual es la anual dividida en doce',
    abs($c['planes']['completo']['mensual'] * 12 - $c['planes']['completo']['anual']) <= 12);
comprobar('la prima es un entero de pesos', is_int($c['planes']['basico']['anual']));

echo "\nCada factor mueve la prima para donde dice\n";

$refe = sn_cotizar_hogar(base())['planes']['completo']['anual'];

comprobar('una casa cuesta más que un departamento',
    sn_cotizar_hogar(base(['tipo' => 'casa']))['planes']['completo']['anual'] > $refe);
comprobar('un PH queda entre el departamento y la casa',
    sn_cotizar_hogar(base(['tipo' => 'pH']))['planes']['completo']['anual'] > $refe
    && sn_cotizar_hogar(base(['tipo' => 'pH']))['planes']['completo']['anual']
       < sn_cotizar_hogar(base(['tipo' => 'casa']))['planes']['completo']['anual']);
comprobar('un departamento en planta baja cuesta más que uno en altura',
    sn_cotizar_hogar(base(['planta_baja' => true]))['planes']['completo']['anual'] > $refe);
comprobar('una vivienda vieja cuesta más',
    sn_cotizar_hogar(base(['antiguedad' => 60]))['planes']['completo']['anual'] > $refe);
comprobar('más contenido asegurado cuesta más',
    sn_cotizar_hogar(base(['suma_contenido' => 16000000]))['planes']['completo']['anual'] > $refe);
comprobar('la provincia cambia el precio',
    sn_cotizar_hogar(base(['provincia' => '06']))['planes']['completo']['anual'] !== $refe);
// El caso base tiene vivienda y antigüedad neutras (factor 1.00 las dos), así
// que en una provincia fuera de la tabla el recargo tiene que dar exactamente
// 1.00. Comparar contra otra provincia pasaría por cualquier motivo.
comprobar('una provincia sin zona propia usa el factor neutro',
    abs(sn_cotizar_hogar(base(['provincia' => '58']))['recargo'] - 1.00) < 0.0001,
    (string) sn_cotizar_hogar(base(['provincia' => '58']))['recargo']);

echo "\nLo que no corresponde, no se cobra ni se descuenta\n";

// La regla del proyecto: una mejora contra el robo no puede descontar en un
// plan que no cubre robo. Es la misma de "rastreo satelital" en automotor.
$sinAlarma = sn_cotizar_hogar(base());
$conAlarma = sn_cotizar_hogar(base(['seguridad' => 'alarma']));
comprobar('la alarma descuenta en el plan que cubre robo',
    $conAlarma['planes']['completo']['anual'] < $sinAlarma['planes']['completo']['anual']);
comprobar('la alarma NO descuenta en el plan que no cubre robo',
    $conAlarma['planes']['basico']['anual'] === $sinAlarma['planes']['basico']['anual'],
    $conAlarma['planes']['basico']['anual'] . ' vs ' . $sinAlarma['planes']['basico']['anual']);
comprobar('el barrio cerrado descuenta más que las rejas',
    sn_cotizar_hogar(base(['seguridad' => 'barrio']))['planes']['completo']['anual']
    < sn_cotizar_hogar(base(['seguridad' => 'rejas']))['planes']['completo']['anual']);
comprobar('la seguridad aparece entre los factores cuando descuenta',
    str_contains(json_encode($conAlarma['factores'], JSON_UNESCAPED_UNICODE), 'Seguridad'));
comprobar('y no aparece cuando no hay ninguna',
    !str_contains(json_encode($sinAlarma['factores'], JSON_UNESCAPED_UNICODE), 'Seguridad'));

// Lo más importante del ramo: el inquilino no paga por el edificio del dueño.
$inquilino = sn_cotizar_hogar(base(['condicion' => 'inquilino', 'suma_edificio' => 40000000]));
$dueno     = sn_cotizar_hogar(base());
comprobar('al inquilino no se le cobra el edificio',
    $inquilino['planes']['completo']['anual'] < $dueno['planes']['completo']['anual']);
comprobar('y el resumen muestra el edificio en cero',
    $inquilino['datos']['edificio'] === 0, (string) $inquilino['datos']['edificio']);
comprobar('aunque haya mandado una suma de edificio, se ignora',
    $inquilino['planes']['completo']['anual']
    === sn_cotizar_hogar(base(['condicion' => 'inquilino', 'suma_edificio' => 999000000]))['planes']['completo']['anual']);
comprobar('el inquilino igual queda cubierto: paga más que cero',
    $inquilino['planes']['completo']['anual'] > 0);

echo "\nResumen de los datos\n";

$c = sn_cotizar_hogar(base(['cp' => 'c1425dke']));
comprobar('normaliza el código postal', $c['datos']['cp'] === 'C1425DKE', $c['datos']['cp']);
comprobar('guarda el nombre de la provincia, no el número',
    $c['datos']['provincia'] === 'Ciudad Autónoma de Buenos Aires', $c['datos']['provincia']);
comprobar('dice en qué condición está', $c['datos']['condicion'] === 'propietario');
comprobar('describe la vivienda en palabras', $c['datos']['vivienda'] === 'Departamento', $c['datos']['vivienda']);

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
