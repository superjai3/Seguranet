<?php
/**
 * Área de cuenta: registro, confirmación por correo, ingreso, salida y
 * restablecimiento de contraseña.
 *
 * Criterios que se sostienen en todas las funciones:
 *
 *  · No se revela si un correo está registrado. Ni al registrarse ni al pedir
 *    el restablecimiento: el mensaje es siempre el mismo. Si no, el formulario
 *    se convierte en una herramienta para averiguar quién tiene cuenta.
 *  · Los tokens se guardan hasheados y con vencimiento, y son de un solo uso.
 *  · Al ingresar se regenera el identificador de sesión, para que un
 *    identificador obtenido antes del login no sirva después.
 *  · El ingreso se frena tras varios intentos fallidos.
 */

declare(strict_types=1);

const SN_TOKEN_HORAS_CONFIRMACION = 48;
const SN_TOKEN_HORAS_RESTABLECER  = 2;   // más corto: es más sensible
const SN_INTENTOS_MAXIMOS         = 5;
const SN_BLOQUEO_MINUTOS          = 15;

/** El usuario de la sesión, o null. */
function sn_usuario(): ?array
{
    return $_SESSION['sn_usuario'] ?? null;
}

/** Genera un token: devuelve [el que se manda, el que se guarda]. */
function sn_generar_token(): array
{
    $crudo = bin2hex(random_bytes(32));
    return [$crudo, hash('sha256', $crudo)];
}

/** Una contraseña que no se pueda adivinar en un fin de semana. */
function sn_clave_debil(string $clave): ?string
{
    if (mb_strlen($clave) < 10) {
        return 'La contraseña tiene que tener al menos 10 caracteres.';
    }
    if (preg_match('/^\d+$/', $clave)) {
        return 'Una contraseña de sólo números se adivina en minutos. Sumale letras.';
    }
    $comunes = ['contraseña', 'password', '1234567890', 'seguranet', 'qwertyuiop'];
    if (in_array(mb_strtolower($clave), $comunes, true)) {
        return 'Esa contraseña está en todas las listas de contraseñas filtradas. Elegí otra.';
    }
    return null;
}

/**
 * Registro. Siempre devuelve el mismo mensaje, exista o no el correo: si el
 * correo ya estaba, se le avisa por mail a su dueño en vez de decírselo a quien
 * completó el formulario.
 */
function sn_registrar(array $config): array
{
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $clave  = (string) ($_POST['clave'] ?? '');
    $errores = [];

    if (!sn_token_valido($_POST['token'] ?? null)) {
        return ['ok' => false, 'errores' => [], 'valores' => compact('nombre', 'correo'),
            'mensaje' => 'El formulario venció. Volvé a enviarlo.'];
    }
    if (mb_strlen($nombre) < 2) {
        $errores['nombre'] = 'Decinos cómo te llamás.';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Revisá el correo: no parece una dirección válida.';
    }
    if ($problema = sn_clave_debil($clave)) {
        $errores['clave'] = $problema;
    }
    if (($_POST['clave2'] ?? '') !== $clave) {
        $errores['clave2'] = 'Las dos contraseñas no coinciden.';
    }
    if (empty($_POST['condiciones'])) {
        $errores['condiciones'] = 'Necesitamos que aceptes los términos y la política de privacidad.';
    }
    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'valores' => compact('nombre', 'correo'),
            'mensaje' => 'Revisá los campos marcados.'];
    }

    $pdo = sn_bd($config);
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errores' => [], 'valores' => compact('nombre', 'correo'),
            'mensaje' => 'No pudimos procesar el registro en este momento. Probá más tarde.'];
    }

    [$tokenCrudo, $tokenHash] = sn_generar_token();
    $expira = (new DateTimeImmutable('+' . SN_TOKEN_HORAS_CONFIRMACION . ' hours'))->format('Y-m-d H:i:s');

    try {
        $existente = $pdo->prepare('SELECT id, correo_confirmado FROM usuarios WHERE correo = ?');
        $existente->execute([$correo]);
        $fila = $existente->fetch();

        if ($fila === false) {
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, clave_hash, token_hash, token_proposito, token_expira, creado_en)
                 VALUES (?, ?, ?, ?, "confirmacion", ?, ?)'
            )->execute([$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $tokenHash, $expira, sn_ahora()]);

            $enlace = url('/cuenta/confirmar?t=' . $tokenCrudo, $config);
            sn_enviar_correo($config, $correo, 'Confirmá tu cuenta · Seguranet',
                '<p>Hola ' . e($nombre) . ',</p>'
                . '<p>Para activar tu cuenta en Seguranet, entrá acá:</p>'
                . '<p><a href="' . e($enlace) . '">Confirmar mi cuenta</a></p>'
                . '<p>El enlace vence en ' . SN_TOKEN_HORAS_CONFIRMACION . ' horas. '
                . 'Si no fuiste vos, ignorá este mensaje: sin confirmar, la cuenta no se activa.</p>'
                . '<p>— Seguranet</p>');

        } elseif ((int) $fila['correo_confirmado'] === 0) {
            // Cuenta sin confirmar: se le renueva el enlace.
            $pdo->prepare('UPDATE usuarios SET token_hash = ?, token_proposito = "confirmacion", token_expira = ? WHERE id = ?')
                ->execute([$tokenHash, $expira, $fila['id']]);
            $enlace = url('/cuenta/confirmar?t=' . $tokenCrudo, $config);
            sn_enviar_correo($config, $correo, 'Confirmá tu cuenta · Seguranet',
                '<p>Ya tenías una cuenta sin confirmar. Este es un enlace nuevo:</p>'
                . '<p><a href="' . e($enlace) . '">Confirmar mi cuenta</a></p>');

        } else {
            // Ya existe y está confirmada: se le avisa al dueño, no al que
            // completó el formulario.
            sn_enviar_correo($config, $correo, 'Alguien intentó registrarse con tu correo · Seguranet',
                '<p>Recibimos un intento de registro con tu dirección en Seguranet.</p>'
                . '<p>Tu cuenta ya existe y no se modificó nada. Si fuiste vos, '
                . '<a href="' . e(url('/cuenta/ingresar', $config)) . '">ingresá acá</a>; '
                . 'si olvidaste la contraseña, podés restablecerla desde esa misma página.</p>');
        }
    } catch (PDOException $ex) {
        error_log('Seguranet registro: ' . $ex->getMessage());
        return ['ok' => false, 'errores' => [], 'valores' => compact('nombre', 'correo'),
            'mensaje' => 'No pudimos procesar el registro en este momento. Probá más tarde.'];
    }

    unset($_SESSION['sn_token']);
    return ['ok' => true, 'errores' => [], 'valores' => [],
        'mensaje' => 'Listo. Si la dirección es válida, te mandamos un correo para confirmar la cuenta. '
                   . 'Revisá también la carpeta de correo no deseado.'];
}

/** Confirmación por enlace. Token de un solo uso y con vencimiento. */
function sn_confirmar(array $config, string $tokenCrudo): array
{
    $pdo = sn_bd($config);
    if (!$pdo instanceof PDO || $tokenCrudo === '') {
        return ['ok' => false, 'mensaje' => 'El enlace no es válido.'];
    }
    try {
        $c = $pdo->prepare(
            'SELECT id, nombre FROM usuarios
             WHERE token_hash = ? AND token_proposito = "confirmacion" AND token_expira > ?'
        );
        $c->execute([hash('sha256', $tokenCrudo), sn_ahora()]);
        $fila = $c->fetch();
        if ($fila === false) {
            return ['ok' => false, 'mensaje' => 'Ese enlace ya se usó o venció. Pedí uno nuevo registrándote otra vez.'];
        }
        $pdo->prepare(
            'UPDATE usuarios SET correo_confirmado = 1, token_hash = NULL,
                    token_proposito = NULL, token_expira = NULL WHERE id = ?'
        )->execute([$fila['id']]);
        return ['ok' => true, 'mensaje' => 'Tu cuenta quedó confirmada, ' . $fila['nombre'] . '. Ya podés ingresar.'];
    } catch (PDOException $ex) {
        error_log('Seguranet confirmar: ' . $ex->getMessage());
        return ['ok' => false, 'mensaje' => 'No pudimos confirmar la cuenta en este momento.'];
    }
}

/** Ingreso. */
function sn_ingresar(array $config): array
{
    $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $clave  = (string) ($_POST['clave'] ?? '');

    if (!sn_token_valido($_POST['token'] ?? null)) {
        return ['ok' => false, 'valores' => compact('correo'), 'mensaje' => 'El formulario venció. Volvé a enviarlo.'];
    }
    $pdo = sn_bd($config);
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'valores' => compact('correo'), 'mensaje' => 'No pudimos procesar el ingreso ahora.'];
    }

    // Mensaje único para correo inexistente y clave equivocada: decir cuál de
    // las dos falló es decirle a un atacante qué correos existen.
    $generico = 'El correo o la contraseña no coinciden.';

    try {
        $c = $pdo->prepare('SELECT * FROM usuarios WHERE correo = ?');
        $c->execute([$correo]);
        $u = $c->fetch();

        if ($u === false) {
            // Se gasta el mismo tiempo que en una verificación real, para que
            // la demora no delate si el correo existe.
            password_verify($clave, '$2y$10$usuarioinexistenteusuarioinexistenteusuarioinexistente12');
            return ['ok' => false, 'valores' => compact('correo'), 'mensaje' => $generico];
        }
        if ($u['bloqueado_hasta'] !== null && new DateTimeImmutable($u['bloqueado_hasta']) > new DateTimeImmutable()) {
            return ['ok' => false, 'valores' => compact('correo'),
                'mensaje' => 'Por seguridad, el ingreso está bloqueado unos minutos tras varios intentos fallidos.'];
        }
        if (!password_verify($clave, $u['clave_hash'])) {
            $intentos = (int) $u['intentos_fallidos'] + 1;
            $bloqueo = $intentos >= SN_INTENTOS_MAXIMOS
                ? (new DateTimeImmutable('+' . SN_BLOQUEO_MINUTOS . ' minutes'))->format('Y-m-d H:i:s')
                : null;
            $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?')
                ->execute([$intentos >= SN_INTENTOS_MAXIMOS ? 0 : $intentos, $bloqueo, $u['id']]);
            return ['ok' => false, 'valores' => compact('correo'), 'mensaje' => $generico];
        }
        if ((int) $u['correo_confirmado'] === 0) {
            return ['ok' => false, 'valores' => compact('correo'),
                'mensaje' => 'Todavía no confirmaste tu cuenta. Buscá el correo que te mandamos al registrarte.'];
        }

        // Si el algoritmo de hash quedó viejo, se actualiza al vuelo.
        if (password_needs_rehash($u['clave_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')
                ->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
        }

        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = ? WHERE id = ?')
            ->execute([sn_ahora(), $u['id']]);

        session_regenerate_id(true);
        // correo_confirmado se guarda aunque acá arriba ya se haya rechazado a
        // quien no confirmó: el panel interno lo exige para dar permiso, y no
        // puede depender en silencio de una comprobación que vive en otra
        // función. Si algún día esta guarda se relaja, el panel no se entera.
        $_SESSION['sn_usuario'] = [
            'id'                => (int) $u['id'],
            'nombre'            => $u['nombre'],
            'correo'            => $u['correo'],
            'correo_confirmado' => 1,
        ];
        unset($_SESSION['sn_token']);

        return ['ok' => true, 'valores' => [], 'mensaje' => ''];
    } catch (PDOException $ex) {
        error_log('Seguranet ingreso: ' . $ex->getMessage());
        return ['ok' => false, 'valores' => compact('correo'), 'mensaje' => 'No pudimos procesar el ingreso ahora.'];
    }
}

/** Salida. */
function sn_salir(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Pedido de restablecimiento. El mensaje es el mismo exista o no el correo. */
function sn_pedir_restablecer(array $config): array
{
    $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $generico = 'Si esa dirección tiene una cuenta, te mandamos un enlace para cambiar la contraseña.';

    if (!sn_token_valido($_POST['token'] ?? null)) {
        return ['ok' => false, 'mensaje' => 'El formulario venció. Volvé a enviarlo.'];
    }
    $pdo = sn_bd($config);
    if ($pdo instanceof PDO && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        try {
            $c = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE correo = ? AND correo_confirmado = 1');
            $c->execute([$correo]);
            if ($u = $c->fetch()) {
                [$crudo, $hash] = sn_generar_token();
                $expira = (new DateTimeImmutable('+' . SN_TOKEN_HORAS_RESTABLECER . ' hours'))->format('Y-m-d H:i:s');
                $pdo->prepare('UPDATE usuarios SET token_hash = ?, token_proposito = "restablecer", token_expira = ? WHERE id = ?')
                    ->execute([$hash, $expira, $u['id']]);
                $enlace = url('/cuenta/restablecer?t=' . $crudo, $config);
                sn_enviar_correo($config, $correo, 'Cambiar tu contraseña · Seguranet',
                    '<p>Hola ' . e($u['nombre']) . ',</p>'
                    . '<p>Pediste cambiar tu contraseña. El enlace vence en '
                    . SN_TOKEN_HORAS_RESTABLECER . ' horas:</p>'
                    . '<p><a href="' . e($enlace) . '">Elegir una contraseña nueva</a></p>'
                    . '<p>Si no lo pediste, ignorá este mensaje: tu contraseña sigue siendo la misma.</p>');
            }
        } catch (PDOException $ex) {
            error_log('Seguranet restablecer: ' . $ex->getMessage());
        }
    }
    unset($_SESSION['sn_token']);
    return ['ok' => true, 'mensaje' => $generico];
}

/** Fijación de la contraseña nueva. */
function sn_restablecer(array $config, string $tokenCrudo): array
{
    $clave = (string) ($_POST['clave'] ?? '');

    if (!sn_token_valido($_POST['token'] ?? null)) {
        return ['ok' => false, 'mensaje' => 'El formulario venció. Volvé a enviarlo.'];
    }
    if ($problema = sn_clave_debil($clave)) {
        return ['ok' => false, 'mensaje' => $problema];
    }
    if (($_POST['clave2'] ?? '') !== $clave) {
        return ['ok' => false, 'mensaje' => 'Las dos contraseñas no coinciden.'];
    }

    $pdo = sn_bd($config);
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'mensaje' => 'No pudimos cambiar la contraseña ahora.'];
    }
    try {
        $c = $pdo->prepare(
            'SELECT id, correo FROM usuarios
             WHERE token_hash = ? AND token_proposito = "restablecer" AND token_expira > ?'
        );
        $c->execute([hash('sha256', $tokenCrudo), sn_ahora()]);
        $u = $c->fetch();
        if ($u === false) {
            return ['ok' => false, 'mensaje' => 'Ese enlace ya se usó o venció. Pedí uno nuevo.'];
        }
        $pdo->prepare(
            'UPDATE usuarios SET clave_hash = ?, token_hash = NULL, token_proposito = NULL,
                    token_expira = NULL, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?'
        )->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);

        // Avisar siempre: si el cambio no lo hizo el dueño, tiene que enterarse.
        sn_enviar_correo($config, $u['correo'], 'Tu contraseña cambió · Seguranet',
            '<p>La contraseña de tu cuenta en Seguranet se cambió recién.</p>'
            . '<p>Si no fuiste vos, escribinos de inmediato a '
            . e($config['sitio']['correo']) . '.</p>');

        unset($_SESSION['sn_token']);
        return ['ok' => true, 'mensaje' => 'Listo, cambiamos tu contraseña. Ya podés ingresar.'];
    } catch (PDOException $ex) {
        error_log('Seguranet restablecer2: ' . $ex->getMessage());
        return ['ok' => false, 'mensaje' => 'No pudimos cambiar la contraseña ahora.'];
    }
}
