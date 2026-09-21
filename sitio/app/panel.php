<?php
/**
 * Panel interno: ver y trabajar las consultas que llegan del formulario.
 *
 * Hasta acá una consulta se guardaba en la base y se avisaba por correo, y no
 * había forma de verla sin entrar a phpMyAdmin. Eso funciona con tres consultas
 * por semana y deja de funcionar a la cuarta: el estado de cada una vive en la
 * cabeza de quien leyó el mail, y una que se responde tarde no se distingue de
 * una que no se respondió nunca.
 *
 * QUIÉN ES ADMINISTRADOR, Y POR QUÉ ASÍ
 *
 * Por una lista de correos en la configuración (SN_ADMINS), no por una columna
 * en la tabla `usuarios`. La diferencia es de seguridad, no de comodidad:
 *
 *  · Con una columna, cualquier fallo que permita escribir en `usuarios` —una
 *    inyección, un error de lógica en el registro— se convierte en escalada de
 *    privilegios. Con la lista en configuración, para hacerse administrador hay
 *    que poder escribir en el servidor de archivos, y quien puede eso ya ganó.
 *  · No existe ninguna ruta del sitio que otorgue el permiso. No es que esté
 *    bien protegida: no existe.
 *
 * El costo es que sumar un administrador pide tocar config.local.php. Con un
 * equipo de una persona, es el intercambio correcto. El día que sean diez, la
 * columna con su propia auditoría se justifica.
 *
 * Y ADEMÁS: el panel exige cuenta confirmada. Estar en la lista no alcanza si
 * el correo nunca se verificó, porque si no cualquiera que registre una cuenta
 * con un correo de la lista —sin tener acceso a ese buzón— entraría.
 */

declare(strict_types=1);

/** Cuántas consultas por página. */
const SN_PANEL_POR_PAGINA = 20;

/** Los estados que una consulta puede tener, en el orden del trabajo. */
const SN_ESTADOS_CONSULTA = [
    'nueva'       => 'Nueva',
    'en_curso'    => 'En curso',
    'respondida'  => 'Respondida',
    'descartada'  => 'Descartada',
];

/**
 * ¿Este usuario puede entrar al panel?
 *
 * Tres condiciones, y las tres hacen falta: sesión iniciada, correo confirmado,
 * y correo en la lista. La comparación es en minúsculas porque el registro ya
 * normaliza así, pero se repite acá para que no dependa de eso.
 */
function sn_es_admin(array $config, ?array $usuario): bool
{
    if ($usuario === null || empty($usuario['correo'])) {
        return false;
    }
    if (empty($usuario['correo_confirmado'])) {
        return false;
    }
    $lista = $config['panel']['admins'] ?? [];
    if ($lista === []) {
        return false;   // sin lista configurada, el panel no existe para nadie
    }
    $correo = mb_strtolower(trim((string) $usuario['correo']));
    foreach ($lista as $admin) {
        // hash_equals y no ==: comparar correos no es comparar secretos, pero
        // acá el resultado decide un permiso y no cuesta nada hacerlo parejo.
        if (hash_equals(mb_strtolower(trim((string) $admin)), $correo)) {
            return true;
        }
    }
    return false;
}

/**
 * Cuántas consultas hay en cada estado.
 *
 * @return array<string,int> estado => cantidad, con los cuatro estados siempre
 *                           presentes aunque estén en cero.
 */
function sn_panel_resumen(PDO $pdo): array
{
    $resumen = array_fill_keys(array_keys(SN_ESTADOS_CONSULTA), 0);
    $filas = $pdo->query('SELECT estado, COUNT(*) AS cuantas FROM consultas GROUP BY estado');
    foreach ($filas as $fila) {
        if (isset($resumen[$fila['estado']])) {
            $resumen[$fila['estado']] = (int) $fila['cuantas'];
        }
    }
    return $resumen;
}

/**
 * Las consultas de una página.
 *
 * $estado vacío trae todas. Las nuevas primero, y dentro de cada estado las
 * más viejas arriba: una consulta de hace tres días es más urgente que la de
 * hace una hora, y ordenar por fecha descendente las escondía al final.
 *
 * @return array{consultas: array, total: int, pagina: int, paginas: int}
 */
function sn_panel_consultas(PDO $pdo, string $estado = '', int $pagina = 1): array
{
    $filtra = isset(SN_ESTADOS_CONSULTA[$estado]);
    $where  = $filtra ? ' WHERE estado = :estado' : '';

    $cuenta = $pdo->prepare('SELECT COUNT(*) FROM consultas' . $where);
    $cuenta->execute($filtra ? [':estado' => $estado] : []);
    $total = (int) $cuenta->fetchColumn();

    $paginas = max(1, (int) ceil($total / SN_PANEL_POR_PAGINA));
    $pagina  = max(1, min($pagina, $paginas));
    $desde   = ($pagina - 1) * SN_PANEL_POR_PAGINA;

    // LIMIT y OFFSET van interpolados y no como parámetros a propósito: en
    // MySQL con emulación desactivada los parámetros de LIMIT llegan como
    // cadena y rompen. Son enteros ya acotados arriba, no entra nada de fuera.
    $sql = 'SELECT * FROM consultas' . $where
         . " ORDER BY FIELD(estado, 'nueva', 'en_curso', 'respondida', 'descartada'), creada_en ASC"
         . ' LIMIT ' . SN_PANEL_POR_PAGINA . ' OFFSET ' . $desde;

    $consulta = $pdo->prepare($sql);
    $consulta->execute($filtra ? [':estado' => $estado] : []);

    return [
        'consultas' => $consulta->fetchAll(),
        'total'     => $total,
        'pagina'    => $pagina,
        'paginas'   => $paginas,
    ];
}

/**
 * Cambia el estado de una consulta. Devuelve false si el estado no existe o si
 * la consulta no está.
 */
function sn_panel_cambiar_estado(PDO $pdo, int $id, string $estado): bool
{
    if (!isset(SN_ESTADOS_CONSULTA[$estado]) || $id <= 0) {
        return false;
    }
    $c = $pdo->prepare('UPDATE consultas SET estado = ? WHERE id = ?');
    $c->execute([$estado, $id]);
    return $c->rowCount() > 0;
}

/**
 * Guarda la nota interna de una consulta.
 *
 * La nota es para el que atiende, no para el cliente: "llamé, no atendió",
 * "pide que lo llamen después de las 18". Sin esto el panel muestra estados
 * pero no explica por qué una consulta lleva cuatro días en curso.
 */
function sn_panel_guardar_nota(PDO $pdo, int $id, string $nota): bool
{
    if ($id <= 0) {
        return false;
    }
    // Se recorta a lo que entra en la columna: una nota más larga que eso es
    // un error de quien pega texto, no algo que haya que rechazar con un cartel.
    $nota = mb_substr(trim($nota), 0, 1000);
    $c = $pdo->prepare('UPDATE consultas SET nota = ? WHERE id = ?');
    $c->execute([$nota, $id]);
    return $c->rowCount() > 0;
}

/** Hace cuánto llegó, en palabras. Para ver de un vistazo qué está demorado. */
function sn_hace_cuanto(string $fecha, ?int $ahora = null): string
{
    $momento = strtotime($fecha);
    if ($momento === false) {
        return '';
    }
    $segundos = ($ahora ?? time()) - $momento;
    if ($segundos < 0)      { return 'recién'; }
    if ($segundos < 3600)   { return 'hace ' . max(1, (int) ($segundos / 60)) . ' min'; }
    if ($segundos < 86400)  { $h = (int) ($segundos / 3600); return 'hace ' . $h . ($h === 1 ? ' hora' : ' horas'); }
    $dias = (int) ($segundos / 86400);
    if ($dias < 30)         { return 'hace ' . $dias . ($dias === 1 ? ' día' : ' días'); }
    $meses = (int) ($dias / 30);
    return 'hace ' . $meses . ($meses === 1 ? ' mes' : ' meses');
}
