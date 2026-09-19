<?php
/**
 * Prueba de la validación de zona: provincia, localidad y código postal.
 *
 * georef no se llama de verdad: se le pasa una función que devuelve una
 * respuesta preparada. Lo que se prueba es qué hace el sitio con lo que llega
 * —y, sobre todo, qué hace cuando no llega nada—.
 *
 *     php sitio/pruebas/zona.php
 */

declare(strict_types=1);

// Los casos de "georef no responde" escriben en el log a propósito; se manda
// a un archivo temporal para que la salida de la prueba quede limpia.
ini_set('error_log', sys_get_temp_dir() . '/seguranet-pruebas.log');

require dirname(__DIR__) . '/app/georef.php';

$fallos = 0;
function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

echo "\nCÓDIGO POSTAL\n";

$r = sn_cp_validar('1636', '06');
comprobar('acepta el formato viejo de 4 dígitos', $r['ok'] && $r['cpa'] === '1636');
comprobar('sugiere el CPA con la letra de la provincia', $r['sugerencia'] === 'B1636');

$r = sn_cp_validar('B1636FDA', '06');
comprobar('acepta un CPA correcto de Buenos Aires', $r['ok'] && $r['cpa'] === 'B1636FDA');

$r = sn_cp_validar('b1636fda', '06');
comprobar('normaliza minúsculas', $r['ok'] && $r['cpa'] === 'B1636FDA');

$r = sn_cp_validar('B 1636 FDA', '06');
comprobar('tolera espacios', $r['ok'] && $r['cpa'] === 'B1636FDA');

$r = sn_cp_validar('C1425DKE', '02');
comprobar('acepta un CPA de CABA', $r['ok']);

// Éste es el error que se quería cazar: CP de una provincia con otra elegida.
$r = sn_cp_validar('X5000ABC', '06');
comprobar('detecta un CP de Córdoba con Buenos Aires elegida', !$r['ok']);
comprobar('dice de qué provincia es ese CP', str_contains($r['error'], 'Córdoba'), $r['error']);
comprobar('propone la corrección', $r['sugerencia'] === 'B5000ABC', $r['sugerencia']);

$r = sn_cp_validar('C1425DKE', '02');
comprobar('no se queja cuando la letra coincide', $r['ok']);

comprobar('rechaza letras sueltas', !sn_cp_validar('hola', '06')['ok']);
comprobar('rechaza un número de más', !sn_cp_validar('B16366FDA', '06')['ok']);
comprobar('rechaza 3 dígitos', !sn_cp_validar('163', '06')['ok']);
comprobar('rechaza el vacío', !sn_cp_validar('', '06')['ok']);

$r = sn_cp_validar('B1636FDA', '');
comprobar('sin provincia, valida sólo el formato', $r['ok']);

echo "\nCOBERTURA DE PROVINCIAS\n";

comprobar('están las 24 jurisdicciones', count(SN_PROVINCIAS) === 24, (string) count(SN_PROVINCIAS));
$letras = array_column(SN_PROVINCIAS, 'letra');
comprobar('ninguna letra se repite', count($letras) === count(array_unique($letras)));

$sinLetra = array_filter(SN_PROVINCIAS, static fn($p) => !preg_match('/^[A-Z]$/', $p['letra']));
comprobar('todas las letras son una mayúscula', $sinLetra === []);

echo "\nGEOREF\n";

$respuestaFalsa = static fn(string $url): string => json_encode(['provincias' => [
    ['id' => '06', 'nombre' => 'Buenos Aires'],
    ['id' => '14', 'nombre' => 'Córdoba'],
]]);
$p = sn_provincias($respuestaFalsa);
comprobar('usa lo que devuelve georef', isset($p['06']) && $p['06'] === 'Buenos Aires');

// Lo importante: qué pasa cuando georef está caído.
$caida = static fn(string $url) => false;
$p = sn_provincias($caida);
comprobar('si georef no responde, igual devuelve las 24 provincias', count($p) === 24);
comprobar('y las devuelve con nombre', ($p['06'] ?? '') === 'Buenos Aires');

$l = sn_localidades('06', $caida);
comprobar('si georef no responde, las localidades quedan vacías', $l === []);

$l = sn_localidades('99', $caida);
comprobar('rechaza una provincia inexistente', $l === []);

$conLocalidades = static fn(string $url): string => json_encode(['localidades' => [
    ['nombre' => 'La Plata'], ['nombre' => 'Mar del Plata'], ['nombre' => 'La Plata'],
]]);
$l = sn_localidades('06', $conLocalidades);
comprobar('no repite localidades', count($l) === 2, implode(', ', $l));

$basura = static fn(string $url): string => 'esto no es json';
comprobar('aguanta una respuesta que no sea JSON', count(sn_provincias($basura)) === 24);

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
