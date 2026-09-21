<?php
/**
 * Prueba del cotizador de automotor.
 *
 * No verifica importes exactos —la tabla es de ejemplo y va a cambiar— sino
 * las relaciones que tienen que sostenerse siempre: que más cobertura cueste
 * más, que la zona y la antigüedad muevan el precio en la dirección correcta,
 * y que el rastreo descuente sólo donde hay robo cubierto.
 *
 *     php sitio/pruebas/cotizador.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/ayudas.php';   // sn_pesos() vive acá
require dirname(__DIR__) . '/app/georef.php';
require dirname(__DIR__) . '/app/cotizador.php';

$fallos = 0;
function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

$anio = (int) date('Y');

/** Caso de referencia: un auto común, en el interior, conductor de mediana edad. */
function base(array $cambios = []): array
{
    return array_merge([
        'marca' => 'Toyota', 'modelo' => 'Corolla', 'anio' => (int) date('Y') - 5,
        'valor' => 20000000, 'provincia' => '14', 'localidad' => 'Córdoba', 'cp' => '',
        'edad' => 40, 'uso' => 'particular',
        'gnc' => false, 'rastreo' => false, 'ajuste' => false,
    ], $cambios);
}

echo "\nCATÁLOGO\n";

$catalogo = sn_catalogo();
comprobar('el catálogo carga', $catalogo !== []);
comprobar('están las marcas de mayor volumen del mercado argentino',
    isset($catalogo['Toyota'], $catalogo['Volkswagen'], $catalogo['Fiat'],
          $catalogo['Renault'], $catalogo['Chevrolet'], $catalogo['Peugeot'], $catalogo['Ford']));
comprobar('Toyota tiene Hilux y Corolla',
    in_array('Hilux', sn_modelos('Toyota'), true) && in_array('Corolla', sn_modelos('Toyota'), true));
comprobar('una marca inexistente devuelve lista vacía', sn_modelos('DeLorean') === []);
$sinModelos = array_filter($catalogo, static fn($m) => $m === []);
comprobar('ninguna marca quedó sin modelos', $sinModelos === [], implode(', ', array_keys($sinModelos)));

echo "\nVALIDACIÓN\n";

comprobar('exige marca del catálogo', isset(sn_cotizacion_errores(base(['marca' => 'Ferrari']), $anio)['marca']));
comprobar('exige modelo', isset(sn_cotizacion_errores(base(['modelo' => '']), $anio)['modelo']));
comprobar('rechaza un año futuro imposible', isset(sn_cotizacion_errores(base(['anio' => $anio + 5]), $anio)['anio']));
comprobar('rechaza un año anterior a 1980', isset(sn_cotizacion_errores(base(['anio' => 1970]), $anio)['anio']));
comprobar('rechaza un valor irrisorio', isset(sn_cotizacion_errores(base(['valor' => 1000]), $anio)['valor']));
comprobar('rechaza un valor absurdo', isset(sn_cotizacion_errores(base(['valor' => 9e9]), $anio)['valor']));
comprobar('rechaza un menor de edad al volante', isset(sn_cotizacion_errores(base(['edad' => 16]), $anio)['edad']));
comprobar('exige la provincia', isset(sn_cotizacion_errores(base(['provincia' => '']), $anio)['provincia']));
comprobar('rechaza un CP de otra provincia',
    isset(sn_cotizacion_errores(base(['provincia' => '14', 'cp' => 'B1636FDA']), $anio)['cp']));
comprobar('acepta el caso de referencia', sn_cotizacion_errores(base(), $anio) === []);

echo "\nORDEN DE LOS PLANES\n";

$c = sn_cotizar(base(), $anio);
$p = $c['planes'];
comprobar('devuelve los cuatro planes', count($p) === 4 && isset($p['A'], $p['B'], $p['C'], $p['D']));
comprobar('a más cobertura, más precio',
    $p['A']['mensual'] < $p['B']['mensual']
    && $p['B']['mensual'] < $p['C']['mensual']
    && $p['C']['mensual'] < $p['D']['mensual'],
    implode(' < ', array_column($p, 'mensual')));
comprobar('el anual es doce veces el mensual, con redondeo',
    abs($p['C']['anual'] - $p['C']['mensual'] * 12) <= 12);
comprobar('el Tercero Completo va destacado', $p['C']['destacado'] === true);
comprobar('ningún precio es cero o negativo',
    count(array_filter($p, static fn($x) => $x['mensual'] > 0)) === 4);

echo "\nFACTORES\n";

$caba = sn_cotizar(base(['provincia' => '02']), $anio)['planes']['C']['mensual'];
$chubut = sn_cotizar(base(['provincia' => '26']), $anio)['planes']['C']['mensual'];
comprobar('CABA cuesta más que Chubut', $caba > $chubut, "$caba vs $chubut");

$viejo = sn_cotizar(base(['anio' => $anio - 25]), $anio)['planes']['C']['mensual'];
$nuevo = sn_cotizar(base(['anio' => $anio]), $anio)['planes']['C']['mensual'];
comprobar('un auto de 25 años cuesta más que uno 0 km', $viejo > $nuevo, "$viejo vs $nuevo");

$joven = sn_cotizar(base(['edad' => 21]), $anio)['planes']['C']['mensual'];
$adulto = sn_cotizar(base(['edad' => 45]), $anio)['planes']['C']['mensual'];
comprobar('un conductor de 21 paga más que uno de 45', $joven > $adulto, "$joven vs $adulto");

$comercial = sn_cotizar(base(['uso' => 'comercial']), $anio)['planes']['C']['mensual'];
comprobar('el uso comercial encarece', $comercial > $adulto);

$conGnc = sn_cotizar(base(['gnc' => true]), $anio)['planes']['C']['mensual'];
comprobar('el GNC encarece', $conGnc > $p['C']['mensual']);

$conAjuste = sn_cotizar(base(['ajuste' => true]), $anio)['planes']['C']['mensual'];
comprobar('la cláusula de ajuste encarece', $conAjuste > $p['C']['mensual']);

echo "\nRASTREO SATELITAL\n";

$conRastreo = sn_cotizar(base(['rastreo' => true]), $anio)['planes'];
comprobar('descuenta en Tercero Completo, que cubre robo',
    $conRastreo['C']['mensual'] < $p['C']['mensual'],
    $conRastreo['C']['mensual'] . ' vs ' . $p['C']['mensual']);
comprobar('descuenta en Todo Riesgo', $conRastreo['D']['mensual'] < $p['D']['mensual']);
// Éste es el que importa: en responsabilidad civil no hay robo cubierto, así
// que fingir un descuento ahí sería mentirle al cliente.
comprobar('NO descuenta en Responsabilidad Civil, que no cubre robo',
    $conRastreo['A']['mensual'] === $p['A']['mensual'],
    $conRastreo['A']['mensual'] . ' vs ' . $p['A']['mensual']);

echo "\nPROPORCIONALIDAD\n";

$barato = sn_cotizar(base(['valor' => 10000000]), $anio)['planes']['D']['mensual'];
$caro   = sn_cotizar(base(['valor' => 40000000]), $anio)['planes']['D']['mensual'];
comprobar('un auto de cuatro veces el valor cuesta más', $caro > $barato);
comprobar('pero no cuatro veces más: hay un piso fijo', $caro < $barato * 4, "$caro vs " . ($barato * 4));

echo "\nFORMATO\n";

comprobar('los pesos se muestran a la argentina', sn_pesos(1234567) === '$ 1.234.567', sn_pesos(1234567));
comprobar('la explicación lista los factores aplicados', count($c['factores']) >= 4);

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
