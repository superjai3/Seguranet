<?php
/**
 * Prueba de pólizas y avisos de vencimiento.
 *
 * Lo que se juega acá: un error de más manda correos repetidos a clientes, y
 * uno de menos deja a alguien sin cobertura por no avisarle.
 *
 *     php sitio/pruebas/polizas.php
 */

declare(strict_types=1);

ini_set('error_log', sys_get_temp_dir() . '/seguranet-pruebas.log');
ini_set('sendmail_path', '/bin/true');

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
$ramos  = require $raiz . '/app/ramos.php';
require $raiz . '/app/ayudas.php';
require $raiz . '/app/polizas.php';

$fallos = 0;
function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

$hoy = '2026-09-17 10:00:00';
$en = static fn(int $dias): string =>
    (new DateTimeImmutable($hoy))->modify(($dias >= 0 ? '+' : '') . $dias . ' days')->format('Y-m-d');

echo "\nCUENTA DE DÍAS\n";

comprobar('una póliza que vence hoy da 0', sn_dias_para_vencer($en(0), $hoy) === 0);
comprobar('a 30 días da 30', sn_dias_para_vencer($en(30), $hoy) === 30);
comprobar('vencida hace 5 días da -5', sn_dias_para_vencer($en(-5), $hoy) === -5);
// Una póliza que vence hoy vence hoy, sean las 9 o las 23: si se comparara con
// la hora, a las 23 ya figuraría vencida.
comprobar('la hora del día no altera la cuenta',
    sn_dias_para_vencer($en(1), '2026-09-17 23:59:00') === 1);

echo "\nESTADO QUE SE MUESTRA\n";

$poliza = static fn(int $dias, string $estado = 'vigente'): array =>
    ['vigencia_hasta' => $en($dias), 'estado' => $estado];

comprobar('a 200 días figura vigente', sn_estado_poliza($poliza(200), $hoy)['clave'] === 'vigente');
comprobar('a 45 días avisa que se acerca', sn_estado_poliza($poliza(45), $hoy)['clave'] === 'proxima');
comprobar('a 20 días marca por vencer', sn_estado_poliza($poliza(20), $hoy)['clave'] === 'por_vencer');
comprobar('el día del vencimiento lo dice', sn_estado_poliza($poliza(0), $hoy)['clave'] === 'vence_hoy');
comprobar('después figura vencida', sn_estado_poliza($poliza(-3), $hoy)['clave'] === 'vencida');
comprobar('una dada de baja no habla de vencimiento',
    sn_estado_poliza($poliza(10, 'dada_de_baja'), $hoy)['clave'] === 'baja');
comprobar('una renovada tampoco',
    sn_estado_poliza($poliza(-100, 'renovada'), $hoy)['clave'] === 'renovada');
comprobar('lo urgente se muestra en tono de error',
    sn_estado_poliza($poliza(5), $hoy)['tono'] === 'error');

echo "\nQUÉ AVISO CORRESPONDE\n";

comprobar('a 90 días todavía no se avisa', sn_hito_a_enviar(90, []) === null);
comprobar('a 60 justos sale el primer aviso', sn_hito_a_enviar(60, []) === 60);
comprobar('a 45 días, si no se avisó nada, sale el de 60', sn_hito_a_enviar(45, []) === 60);
comprobar('a 45 días, con el de 60 ya enviado, no sale nada', sn_hito_a_enviar(45, [60]) === null);
comprobar('a 30 días, con el de 60 enviado, sale el de 30', sn_hito_a_enviar(30, [60]) === 30);
comprobar('a 7 días, con todos los previos enviados, sale el de 7',
    sn_hito_a_enviar(7, [60, 30, 15]) === 7);
comprobar('con todos enviados no se manda nada más', sn_hito_a_enviar(3, [60, 30, 15, 7]) === null);
comprobar('una póliza vencida no recibe aviso de vencimiento', sn_hito_a_enviar(-2, []) === null);

// Éste es el caso que justifica todo el diseño: si el cron estuvo caído, al
// volver tiene que mandar UN aviso, no la pila atrasada.
$hito = sn_hito_a_enviar(10, []);
comprobar('tras una caída larga manda un solo aviso, el más cercano', $hito === 15, (string) $hito);

echo "\nVALIDACIÓN DEL ALTA\n";

$alta = static fn(array $c = []): array => array_merge([
    'ramo' => 'automotor', 'vigencia_hasta' => '2027-03-01',
    'vigencia_desde' => '2026-03-01', 'aseguradora' => 'La Segunda', 'prima_mensual' => '85000',
], $c);

comprobar('acepta un alta completa', sn_poliza_errores($alta(), $ramos) === []);
comprobar('acepta sólo con ramo y vencimiento',
    sn_poliza_errores(['ramo' => 'hogar', 'vigencia_hasta' => '2027-01-01'], $ramos) === []);
comprobar('exige el ramo', isset(sn_poliza_errores($alta(['ramo' => '']), $ramos)['ramo']));
comprobar('rechaza un ramo que no existe', isset(sn_poliza_errores($alta(['ramo' => 'naves']), $ramos)['ramo']));
comprobar('exige el vencimiento', isset(sn_poliza_errores($alta(['vigencia_hasta' => '']), $ramos)['vigencia_hasta']));
comprobar('rechaza una fecha mal formada',
    isset(sn_poliza_errores($alta(['vigencia_hasta' => '01/03/2027']), $ramos)['vigencia_hasta']));
comprobar('rechaza un vencimiento a 20 años',
    isset(sn_poliza_errores($alta(['vigencia_hasta' => '2046-01-01']), $ramos)['vigencia_hasta']));
comprobar('rechaza que el inicio sea posterior al vencimiento',
    isset(sn_poliza_errores($alta(['vigencia_desde' => '2027-06-01']), $ramos)['vigencia_desde']));
comprobar('rechaza una prima negativa',
    isset(sn_poliza_errores($alta(['prima_mensual' => '-500']), $ramos)['prima_mensual']));
// Una póliza ya vencida se puede cargar: sirve para el historial y para que le
// ofrezcamos algo a alguien que quedó descubierto.
comprobar('deja cargar una póliza ya vencida',
    sn_poliza_errores($alta(['vigencia_hasta' => '2025-01-01', 'vigencia_desde' => '2024-01-01']), $ramos) === []);

echo "\nTEXTO DEL AVISO\n";

$p = ['ramo' => 'automotor', 'aseguradora' => 'La Segunda', 'vigencia_hasta' => '2026-11-16'];
$a60 = sn_texto_aviso($p, 60, $ramos, $config);
$a7  = sn_texto_aviso($p, 7, $ramos, $config);
comprobar('el aviso de 60 días habla de comparar sin apuro', str_contains($a60['cuerpo'], 'sin apuro'));
comprobar('el de 7 días transmite urgencia', str_contains($a7['cuerpo'], 'menos de una semana'));
comprobar('los asuntos son distintos según el hito', $a60['asunto'] !== $a7['asunto']);
comprobar('el aviso nombra la aseguradora', str_contains($a60['cuerpo'], 'La Segunda'));
comprobar('muestra la fecha a la argentina', str_contains($a60['cuerpo'], '16/11/2026'));
comprobar('explica cómo dejar de recibirlos', str_contains($a60['cuerpo'], 'dejás de recibirlos'));

echo "\nASEGURADORAS\n";

comprobar('la lista trae las principales del mercado',
    in_array('Sancor Seguros', SN_ASEGURADORAS, true)
    && in_array('Federación Patronal', SN_ASEGURADORAS, true)
    && in_array('La Caja', SN_ASEGURADORAS, true));
// Comparar con sort() dependería de la configuración regional del servidor, que
// en IONOS no controlamos. Se compara sobre una versión sin acentos y en
// minúscula, que da el mismo resultado en cualquier máquina.
$normalizar = static fn(string $s): string => mb_strtolower(strtr($s,
    ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']));
$ordenadas = array_map($normalizar, SN_ASEGURADORAS);
$esperado = $ordenadas;
sort($esperado, SORT_STRING);
comprobar('están ordenadas alfabéticamente', $ordenadas === $esperado,
    'primera diferencia: ' . (string) (array_diff_assoc($ordenadas, $esperado)[array_key_first(array_diff_assoc($ordenadas, $esperado) ?: [0 => ''])] ?? '—'));
comprobar('no hay repetidas', count(SN_ASEGURADORAS) === count(array_unique(SN_ASEGURADORAS)));

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
