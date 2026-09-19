<?php
/**
 * Prueba del área de cuenta de punta a punta.
 *
 * Corre contra SQLite en memoria en vez de MySQL: lo que se está probando es
 * la lógica —qué se guarda, qué se rechaza, qué se filtra—, no el motor. Es
 * posible porque el SQL no usa nada específico de MySQL desde que las fechas
 * las pone PHP.
 *
 *     php sitio/pruebas/cuentas.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);
$config = require $raiz . '/app/config.php';
require $raiz . '/app/ayudas.php';
require $raiz . '/app/usuarios.php';

// Los correos no se mandan de verdad: mail() cae en un comando que no hace
// nada, en vez de llenar la salida de errores de sendmail.
ini_set('sendmail_path', '/bin/true');

// La salida se guarda hasta el final: sin esto, el primer echo manda las
// cabeceras y después session_start() y session_regenerate_id() no pueden
// trabajar. Es una limitación de correr esto por línea de comandos, no del
// código del sitio.
ob_start();
session_start();

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('
    CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        correo TEXT NOT NULL UNIQUE,
        clave_hash TEXT NOT NULL,
        correo_confirmado INTEGER NOT NULL DEFAULT 0,
        token_hash TEXT,
        token_proposito TEXT,
        token_expira TEXT,
        intentos_fallidos INTEGER NOT NULL DEFAULT 0,
        bloqueado_hasta TEXT,
        creado_en TEXT NOT NULL,
        ultimo_acceso TEXT
    )');
sn_bd($config, $pdo);

$fallos = 0;
function comprobar(string $caso, bool $condicion, string $detalle = ''): void
{
    global $fallos;
    if ($condicion) {
        echo "  ok    $caso\n";
    } else {
        echo "  FALLA $caso" . ($detalle !== '' ? " — $detalle" : '') . "\n";
        $fallos++;
    }
}

/** Simula el envío de un formulario. */
function enviar(array $campos): void
{
    $_POST = $campos + ['token' => sn_token()];
}

echo "\nREGISTRO\n";

enviar(['nombre' => 'Jaime', 'correo' => 'jaime@ejemplo.com', 'clave' => '1234567890',
        'clave2' => '1234567890', 'condiciones' => '1']);
$r = sn_registrar($config);
comprobar('rechaza una contraseña de sólo números', !$r['ok'] && isset($r['errores']['clave']));

enviar(['nombre' => 'Jaime', 'correo' => 'jaime@ejemplo.com', 'clave' => 'calle Alsina 1234',
        'clave2' => 'otra cosa distinta', 'condiciones' => '1']);
$r = sn_registrar($config);
comprobar('rechaza cuando las contraseñas no coinciden', !$r['ok'] && isset($r['errores']['clave2']));

enviar(['nombre' => 'Jaime', 'correo' => 'jaime@ejemplo.com', 'clave' => 'calle Alsina 1234',
        'clave2' => 'calle Alsina 1234']);
$r = sn_registrar($config);
comprobar('exige aceptar los términos', !$r['ok'] && isset($r['errores']['condiciones']));

enviar(['nombre' => 'Jaime Valdés', 'correo' => 'jaime@ejemplo.com', 'clave' => 'calle Alsina 1234',
        'clave2' => 'calle Alsina 1234', 'condiciones' => '1']);
$r = sn_registrar($config);
comprobar('acepta un registro válido', $r['ok'], $r['mensaje']);

$u = $pdo->query('SELECT * FROM usuarios WHERE correo = "jaime@ejemplo.com"')->fetch();
comprobar('la contraseña quedó hasheada, no en claro',
    $u !== false && $u['clave_hash'] !== 'calle Alsina 1234' && password_verify('calle Alsina 1234', $u['clave_hash']));
comprobar('la cuenta arranca sin confirmar', (int) $u['correo_confirmado'] === 0);
comprobar('el token quedó guardado hasheado (64 hex)',
    strlen((string) $u['token_hash']) === 64 && ctype_xdigit((string) $u['token_hash']));

$mensajeExistente = $r['mensaje'];
enviar(['nombre' => 'Otro', 'correo' => 'jaime@ejemplo.com', 'clave' => 'otra clave larga',
        'clave2' => 'otra clave larga', 'condiciones' => '1']);
$r2 = sn_registrar($config);
comprobar('no revela que el correo ya existe', $r2['ok'] && $r2['mensaje'] === $mensajeExistente);

echo "\nCONFIRMACIÓN\n";

// El token original no está en la base: se reconstruye el caso generando uno.
[$crudo, $hash] = sn_generar_token();
$pdo->prepare('UPDATE usuarios SET token_hash = ?, token_proposito = "confirmacion", token_expira = ? WHERE id = ?')
    ->execute([$hash, sn_ahora('+48 hours'), $u['id']]);

comprobar('rechaza un token inventado', !sn_confirmar($config, 'token-falso-123')['ok']);

$r = sn_confirmar($config, $crudo);
comprobar('confirma con el token correcto', $r['ok'], $r['mensaje']);
comprobar('no se puede usar el mismo token dos veces', !sn_confirmar($config, $crudo)['ok']);

[$crudo2, $hash2] = sn_generar_token();
$pdo->prepare('UPDATE usuarios SET token_hash = ?, token_proposito = "confirmacion", token_expira = ? WHERE id = ?')
    ->execute([$hash2, sn_ahora('-1 hour'), $u['id']]);
comprobar('rechaza un token vencido', !sn_confirmar($config, $crudo2)['ok']);

echo "\nINGRESO\n";

$pdo->prepare('UPDATE usuarios SET correo_confirmado = 1, token_hash = NULL WHERE id = ?')->execute([$u['id']]);

enviar(['correo' => 'jaime@ejemplo.com', 'clave' => 'equivocada del todo']);
$r = sn_ingresar($config);
$mensajeMalo = $r['mensaje'];
comprobar('rechaza la contraseña equivocada', !$r['ok']);

enviar(['correo' => 'noexiste@ejemplo.com', 'clave' => 'equivocada del todo']);
$r = sn_ingresar($config);
comprobar('no distingue correo inexistente de clave errada', !$r['ok'] && $r['mensaje'] === $mensajeMalo);

$_POST = ['correo' => 'jaime@ejemplo.com', 'clave' => 'calle Alsina 1234', 'token' => 'falsificado'];
$r = sn_ingresar($config);
comprobar('rechaza sin token antifalsificación válido', !$r['ok'] && str_contains($r['mensaje'], 'venció'));

enviar(['correo' => 'jaime@ejemplo.com', 'clave' => 'calle Alsina 1234']);
$r = sn_ingresar($config);
comprobar('acepta las credenciales correctas', $r['ok'], $r['mensaje']);
comprobar('deja al usuario en la sesión', sn_usuario() !== null && sn_usuario()['correo'] === 'jaime@ejemplo.com');

sn_salir();
@session_start();
comprobar('al salir, la sesión queda vacía', sn_usuario() === null);

echo "\nBLOQUEO POR INTENTOS\n";

for ($i = 1; $i <= 5; $i++) {
    enviar(['correo' => 'jaime@ejemplo.com', 'clave' => 'mal mal mal mal']);
    sn_ingresar($config);
}
$u2 = $pdo->query('SELECT bloqueado_hasta FROM usuarios WHERE correo = "jaime@ejemplo.com"')->fetch();
comprobar('bloquea tras 5 intentos fallidos', $u2['bloqueado_hasta'] !== null);

enviar(['correo' => 'jaime@ejemplo.com', 'clave' => 'calle Alsina 1234']);
$r = sn_ingresar($config);
comprobar('la clave correcta no entra mientras está bloqueado', !$r['ok'] && str_contains($r['mensaje'], 'bloqueado'));

echo "\nRESTABLECER\n";

$pdo->prepare('UPDATE usuarios SET bloqueado_hasta = NULL, intentos_fallidos = 0 WHERE id = ?')->execute([$u['id']]);

enviar(['correo' => 'jaime@ejemplo.com']);
$r = sn_pedir_restablecer($config);
$mensajeExiste = $r['mensaje'];
enviar(['correo' => 'nadie@ejemplo.com']);
$r2 = sn_pedir_restablecer($config);
comprobar('no revela si el correo tiene cuenta', $r2['mensaje'] === $mensajeExiste);

[$crudo3, $hash3] = sn_generar_token();
$pdo->prepare('UPDATE usuarios SET token_hash = ?, token_proposito = "restablecer", token_expira = ? WHERE id = ?')
    ->execute([$hash3, sn_ahora('+2 hours'), $u['id']]);

enviar(['clave' => 'corta', 'clave2' => 'corta']);
comprobar('rechaza una contraseña nueva demasiado corta', !sn_restablecer($config, $crudo3)['ok']);

enviar(['clave' => 'mi frase nueva seguranet', 'clave2' => 'mi frase nueva seguranet']);
$r = sn_restablecer($config, $crudo3);
comprobar('cambia la contraseña con el token válido', $r['ok'], $r['mensaje']);

enviar(['correo' => 'jaime@ejemplo.com', 'clave' => 'mi frase nueva seguranet']);
comprobar('la contraseña nueva sirve para ingresar', sn_ingresar($config)['ok']);

enviar(['clave' => 'otra distinta todavia', 'clave2' => 'otra distinta todavia']);
comprobar('el token de restablecer no se puede reutilizar', !sn_restablecer($config, $crudo3)['ok']);

echo "\n" . ($fallos === 0 ? "TODO BIEN\n" : "HAY $fallos FALLA(S)\n") . "\n";
$salida = ob_get_clean();
echo $salida;
exit($fallos === 0 ? 0 : 1);
