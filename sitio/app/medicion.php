<?php
/**
 * Medición de audiencia, con consentimiento previo.
 *
 * El orden importa y es el que exige la norma: primero se pide, después se
 * mide. Nada de analítica se carga hasta que la persona acepta, y rechazar
 * tiene que ser tan fácil como aceptar —un aviso donde "Aceptar" es un botón
 * grande y "Rechazar" un enlace gris escondido no es consentimiento libre—.
 *
 * Mientras SN_MEDICION_ID esté vacío no se muestra ningún aviso: no tiene
 * sentido pedir permiso para algo que no se hace.
 *
 * La elección se guarda en una cookie propia, no en la sesión, para que dure
 * entre visitas. Vence al año: pasado ese plazo se vuelve a preguntar, que es
 * lo que corresponde cuando el consentimiento es por tiempo indeterminado.
 */

declare(strict_types=1);

const SN_COOKIE_CONSENTIMIENTO = 'sn_medicion';
const SN_CONSENTIMIENTO_DIAS   = 365;

/** Qué decidió la persona: 'si', 'no' o '' si todavía no eligió. */
function sn_consentimiento(): string
{
    $valor = (string) ($_COOKIE[SN_COOKIE_CONSENTIMIENTO] ?? '');
    return in_array($valor, ['si', 'no'], true) ? $valor : '';
}

/** Guarda la elección. */
function sn_guardar_consentimiento(string $eleccion): void
{
    if (!in_array($eleccion, ['si', 'no'], true)) {
        return;
    }
    setcookie(SN_COOKIE_CONSENTIMIENTO, $eleccion, [
        'expires'  => time() + SN_CONSENTIMIENTO_DIAS * 86400,
        'path'     => '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => false,  // el JavaScript necesita leerla para no re-preguntar
        'samesite' => 'Lax',
    ]);
    $_COOKIE[SN_COOKIE_CONSENTIMIENTO] = $eleccion;
}

/** ¿Hay que mostrar el aviso? Sólo si se mide algo y todavía no eligió. */
function sn_mostrar_aviso_cookies(array $config): bool
{
    return ($config['medicion']['id'] ?? '') !== '' && sn_consentimiento() === '';
}

/** ¿Se puede cargar la analítica? */
function sn_medicion_activa(array $config): bool
{
    return ($config['medicion']['id'] ?? '') !== '' && sn_consentimiento() === 'si';
}
