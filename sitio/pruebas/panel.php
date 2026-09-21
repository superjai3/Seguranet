<?php
/**
 * Prueba del panel interno.
 *
 * Lo primero que se prueba no es que el panel funcione sino que NO deje entrar:
 * un panel de consultas expone nombre, correo, teléfono e IP de cada persona
 * que escribió. Equivocarse acá es una filtración de datos personales, no un
 * error de interfaz.
 *
 *     php sitio/pruebas/panel.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
require $raiz . '/app/ayudas.php';
require $raiz . '/app/panel.php';

$fallos = 0;

function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) { echo "  ok    $caso\n"; }
    else { echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n"; $fallos++; }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('
    CREATE TABLE consultas (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre    TEXT NOT NULL,
        correo    TEXT NOT NULL,
        telefono  TEXT NOT NULL DEFAULT "",
        ramo      TEXT NOT NULL DEFAULT "",
        mensaje   TEXT NOT NULL,
        provincia TEXT NOT NULL DEFAULT "",
        localidad TEXT NOT NULL DEFAULT "",
        cp        TEXT NOT NULL DEFAULT "",
        origen_ip TEXT NOT NULL DEFAULT "",
        agente    TEXT NOT NULL DEFAULT "",
        estado    TEXT NOT NULL DEFAULT "nueva",
        nota      TEXT NOT NULL DEFAULT "",
        creada_en TEXT NOT NULL
    )');

// SQLite no tiene FIELD(); se le enseña, así el orden del panel se prueba de
// verdad y no sólo "que no explote".
$pdo->sqliteCreateFunction('FIELD', static function (...$args): int {
    $aguja = array_shift($args);
    $pos = array_search($aguja, $args, true);
    return $pos === false ? count($args) + 1 : $pos + 1;
});

$sembrar = static function (string $nombre, string $estado, string $cuando) use ($pdo): int {
    $pdo->prepare('INSERT INTO consultas (nombre, correo, mensaje, estado, creada_en)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$nombre, mb_strtolower($nombre) . '@ejemplo.com', 'Hola', $estado, $cuando]);
    return (int) $pdo->lastInsertId();
};

echo "\nQuién puede entrar\n";

$conLista = ['panel' => ['admins' => ['jaime@seguranet.es', 'socio@seguranet.es']]];
$admin    = ['id' => 1, 'correo' => 'jaime@seguranet.es', 'correo_confirmado' => 1];

comprobar('un correo de la lista, con cuenta confirmada, entra',
    sn_es_admin($conLista, $admin));
comprobar('el segundo correo de la lista también',
    sn_es_admin($conLista, ['correo' => 'socio@seguranet.es', 'correo_confirmado' => 1]));

// Las cuatro formas de NO entrar. Cada una es un agujero distinto.
comprobar('sin sesión, no', !sn_es_admin($conLista, null));
comprobar('un correo que no está en la lista, no',
    !sn_es_admin($conLista, ['correo' => 'cualquiera@gmail.com', 'correo_confirmado' => 1]));
// Ésta es la importante: si bastara con estar en la lista, alguien que
// registra una cuenta con un correo de la lista —sin tener acceso a ese
// buzón— entraría al panel.
comprobar('un correo de la lista SIN confirmar, no',
    !sn_es_admin($conLista, ['correo' => 'jaime@seguranet.es', 'correo_confirmado' => 0]));
comprobar('sin lista configurada, no entra nadie',
    !sn_es_admin(['panel' => ['admins' => []]], $admin));
comprobar('sin la clave panel en la configuración, tampoco',
    !sn_es_admin([], $admin));
comprobar('un correo vacío, no', !sn_es_admin($conLista, ['correo' => '', 'correo_confirmado' => 1]));

// Robustez de la comparación: el registro ya normaliza a minúsculas, pero el
// permiso no puede depender de que eso siga siendo cierto.
comprobar('no importan las mayúsculas',
    sn_es_admin($conLista, ['correo' => 'JAIME@Seguranet.ES', 'correo_confirmado' => 1]));
comprobar('no importan los espacios al costado en la lista',
    sn_es_admin(['panel' => ['admins' => ['  jaime@seguranet.es  ']]], $admin));
// Que no se cuele por parecerse: ni un prefijo, ni un sufijo, ni un dominio
// que contenga al nuestro.
comprobar('un correo que es prefijo del admin, no',
    !sn_es_admin($conLista, ['correo' => 'jaime@seguranet.e', 'correo_confirmado' => 1]));
comprobar('un correo que contiene al admin, no',
    !sn_es_admin($conLista, ['correo' => 'xjaime@seguranet.es', 'correo_confirmado' => 1]));
comprobar('un dominio parecido, no',
    !sn_es_admin($conLista, ['correo' => 'jaime@seguranet.es.atacante.com', 'correo_confirmado' => 1]));

echo "\nResumen por estado\n";

$vacio = sn_panel_resumen($pdo);
comprobar('con la base vacía devuelve los cuatro estados en cero',
    $vacio === ['nueva' => 0, 'en_curso' => 0, 'respondida' => 0, 'descartada' => 0],
    json_encode($vacio));

$vieja  = $sembrar('Ana',    'nueva',      '2026-09-14 10:00:00');
$media  = $sembrar('Bruno',  'nueva',      '2026-09-18 10:00:00');
$curso  = $sembrar('Carla',  'en_curso',   '2026-09-10 10:00:00');
$lista  = $sembrar('Diego',  'respondida', '2026-09-01 10:00:00');
$fuera  = $sembrar('Elena',  'descartada', '2026-09-02 10:00:00');

$resumen = sn_panel_resumen($pdo);
comprobar('cuenta las nuevas', $resumen['nueva'] === 2, (string) $resumen['nueva']);
comprobar('cuenta las que están en curso', $resumen['en_curso'] === 1);
comprobar('cuenta las respondidas', $resumen['respondida'] === 1);
comprobar('cuenta las descartadas', $resumen['descartada'] === 1);

echo "\nListado\n";

$pagina = sn_panel_consultas($pdo);
comprobar('trae todas si no se filtra', $pagina['total'] === 5, (string) $pagina['total']);
comprobar('las nuevas van primero',
    $pagina['consultas'][0]['estado'] === 'nueva' && $pagina['consultas'][1]['estado'] === 'nueva',
    $pagina['consultas'][0]['estado']);
// Lo contrario de lo habitual, y a propósito: una consulta de hace tres días
// es más urgente que la de hace una hora.
comprobar('dentro de las nuevas, la más vieja arriba',
    (int) $pagina['consultas'][0]['id'] === $vieja,
    $pagina['consultas'][0]['nombre']);
comprobar('las descartadas van al final',
    $pagina['consultas'][4]['estado'] === 'descartada', $pagina['consultas'][4]['estado']);

$soloNuevas = sn_panel_consultas($pdo, 'nueva');
comprobar('filtra por estado', $soloNuevas['total'] === 2);
comprobar('y sólo trae ese estado',
    array_unique(array_column($soloNuevas['consultas'], 'estado')) === ['nueva']);

// Un estado inventado no puede colarse en el SQL ni traer todo sin querer.
$inventado = sn_panel_consultas($pdo, "nueva' OR '1'='1");
comprobar('un estado inventado se ignora y trae todo, sin romper',
    $inventado['total'] === 5, (string) $inventado['total']);

echo "\nPaginación\n";

for ($i = 0; $i < 25; $i++) {
    $sembrar('Relleno' . $i, 'respondida', '2026-08-' . str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT) . ' 10:00:00');
}
$p1 = sn_panel_consultas($pdo, '', 1);
comprobar('la primera página trae el máximo', count($p1['consultas']) === SN_PANEL_POR_PAGINA);
comprobar('calcula cuántas páginas hay', $p1['paginas'] === 2, (string) $p1['paginas']);
$p2 = sn_panel_consultas($pdo, '', 2);
comprobar('la segunda página trae el resto', count($p2['consultas']) === 10, (string) count($p2['consultas']));
comprobar('no se repite nada entre páginas',
    array_intersect(array_column($p1['consultas'], 'id'), array_column($p2['consultas'], 'id')) === []);
comprobar('una página más allá del final devuelve la última',
    sn_panel_consultas($pdo, '', 99)['pagina'] === 2);
comprobar('una página cero o negativa devuelve la primera',
    sn_panel_consultas($pdo, '', -5)['pagina'] === 1);

echo "\nCambiar el estado\n";

comprobar('cambia el estado de una consulta', sn_panel_cambiar_estado($pdo, $vieja, 'en_curso'));
$c = $pdo->query('SELECT estado FROM consultas WHERE id = ' . $vieja)->fetchColumn();
comprobar('y queda guardado', $c === 'en_curso', (string) $c);
comprobar('rechaza un estado que no existe', !sn_panel_cambiar_estado($pdo, $media, 'archivada'));
comprobar('rechaza un intento de inyección en el estado',
    !sn_panel_cambiar_estado($pdo, $media, "nueva'; DROP TABLE consultas; --"));
comprobar('la tabla sigue entera después de eso',
    (int) $pdo->query('SELECT COUNT(*) FROM consultas')->fetchColumn() === 30);
comprobar('la consulta no cambió con el estado rechazado',
    $pdo->query('SELECT estado FROM consultas WHERE id = ' . $media)->fetchColumn() === 'nueva');
comprobar('un id que no existe devuelve false', !sn_panel_cambiar_estado($pdo, 99999, 'nueva'));
comprobar('un id cero o negativo devuelve false', !sn_panel_cambiar_estado($pdo, 0, 'nueva'));

echo "\nNota interna\n";

comprobar('guarda una nota', sn_panel_guardar_nota($pdo, $curso, 'Llamé, no atendió. Reintentar el lunes.'));
comprobar('y se puede leer',
    $pdo->query('SELECT nota FROM consultas WHERE id = ' . $curso)->fetchColumn()
    === 'Llamé, no atendió. Reintentar el lunes.');
comprobar('recorta una nota larguísima en vez de rechazarla',
    sn_panel_guardar_nota($pdo, $curso, str_repeat('x', 5000))
    && mb_strlen((string) $pdo->query('SELECT nota FROM consultas WHERE id = ' . $curso)->fetchColumn()) === 1000);
comprobar('una nota vacía borra la anterior',
    sn_panel_guardar_nota($pdo, $curso, '   ')
    && $pdo->query('SELECT nota FROM consultas WHERE id = ' . $curso)->fetchColumn() === '');
comprobar('un id que no existe devuelve false', !sn_panel_guardar_nota($pdo, 99999, 'algo'));

echo "\nHace cuánto llegó\n";

$ahora = strtotime('2026-09-21 12:00:00');
comprobar('minutos', sn_hace_cuanto('2026-09-21 11:30:00', $ahora) === 'hace 30 min');
comprobar('una hora en singular', sn_hace_cuanto('2026-09-21 11:00:00', $ahora) === 'hace 1 hora');
comprobar('varias horas', sn_hace_cuanto('2026-09-21 06:00:00', $ahora) === 'hace 6 horas');
comprobar('un día en singular', sn_hace_cuanto('2026-09-20 11:00:00', $ahora) === 'hace 1 día');
comprobar('varios días', sn_hace_cuanto('2026-09-14 12:00:00', $ahora) === 'hace 7 días');
comprobar('meses', sn_hace_cuanto('2026-07-01 12:00:00', $ahora) === 'hace 2 meses');
comprobar('menos de un minuto no dice "hace 0 min"',
    sn_hace_cuanto('2026-09-21 11:59:50', $ahora) === 'hace 1 min');
comprobar('una fecha futura no dice "hace -3 días"',
    sn_hace_cuanto('2026-09-24 12:00:00', $ahora) === 'recién');
comprobar('una fecha ilegible devuelve vacío en vez de romper',
    sn_hace_cuanto('no es una fecha', $ahora) === '');

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
exit($fallos === 0 ? 0 : 1);
