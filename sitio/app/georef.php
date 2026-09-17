<?php
/**
 * Zona del riesgo: provincia, localidad y código postal.
 *
 * Por qué importa: en seguros la zona mueve la prima tanto como el vehículo.
 * Hasta ahora el cotizador pedía el código postal como texto libre, sin
 * validarlo contra nada.
 *
 * QUÉ HACE GEOREF Y QUÉ NO. georef (apis.datos.gob.ar/georef) es el servicio
 * oficial de datos geográficos del Estado argentino: da provincias,
 * departamentos, municipios, localidades, calles y direcciones normalizadas.
 * **No da códigos postales**: la base del CPA es de Correo Argentino y no tiene
 * API pública gratuita. Por eso acá se hacen dos cosas distintas:
 *
 *   1. La provincia y la localidad se toman de georef, normalizadas. Eso es lo
 *      que de verdad identifica la zona, y es dato oficial.
 *   2. El código postal lo escribe la persona, y se valida por su estructura:
 *      el CPA tiene forma letra + 4 dígitos + 3 letras (B1636FDA), y **la letra
 *      inicial identifica la provincia**. Con eso se detecta el error más común
 *      —el CP de otra provincia— sin depender de ninguna base cerrada.
 *
 * Si georef no responde, el sitio no se rompe: las provincias salen de la lista
 * incluida acá (son 24 y no cambian) y la localidad pasa a texto libre.
 */

declare(strict_types=1);

const SN_GEOREF_BASE   = 'https://apis.datos.gob.ar/georef/api';
const SN_GEOREF_ESPERA = 4;        // segundos; si tarda más, no vale la pena
const SN_GEOREF_CACHE  = 2592000;  // 30 días: esto cambia una vez por década

/**
 * Letra inicial del CPA por provincia, con el código INDEC que usa georef.
 * La relación está fijada por Correo Argentino y no depende de ningún servicio.
 */
const SN_PROVINCIAS = [
    '02' => ['nombre' => 'Ciudad Autónoma de Buenos Aires', 'letra' => 'C'],
    '06' => ['nombre' => 'Buenos Aires',                    'letra' => 'B'],
    '10' => ['nombre' => 'Catamarca',                       'letra' => 'K'],
    '14' => ['nombre' => 'Córdoba',                         'letra' => 'X'],
    '18' => ['nombre' => 'Corrientes',                      'letra' => 'W'],
    '22' => ['nombre' => 'Chaco',                           'letra' => 'H'],
    '26' => ['nombre' => 'Chubut',                          'letra' => 'U'],
    '30' => ['nombre' => 'Entre Ríos',                      'letra' => 'E'],
    '34' => ['nombre' => 'Formosa',                         'letra' => 'P'],
    '38' => ['nombre' => 'Jujuy',                           'letra' => 'Y'],
    '42' => ['nombre' => 'La Pampa',                        'letra' => 'L'],
    '46' => ['nombre' => 'La Rioja',                        'letra' => 'F'],
    '50' => ['nombre' => 'Mendoza',                         'letra' => 'M'],
    '54' => ['nombre' => 'Misiones',                        'letra' => 'N'],
    '58' => ['nombre' => 'Neuquén',                         'letra' => 'Q'],
    '62' => ['nombre' => 'Río Negro',                       'letra' => 'R'],
    '66' => ['nombre' => 'Salta',                           'letra' => 'A'],
    '70' => ['nombre' => 'San Juan',                        'letra' => 'J'],
    '74' => ['nombre' => 'San Luis',                        'letra' => 'D'],
    '78' => ['nombre' => 'Santa Cruz',                      'letra' => 'Z'],
    '82' => ['nombre' => 'Santa Fe',                        'letra' => 'S'],
    '86' => ['nombre' => 'Santiago del Estero',             'letra' => 'G'],
    '90' => ['nombre' => 'Tucumán',                         'letra' => 'T'],
    '94' => ['nombre' => 'Tierra del Fuego',                'letra' => 'V'],
];

/** Carpeta de caché. Si no se puede crear, se sigue sin caché. */
function sn_georef_carpeta(): ?string
{
    $carpeta = dirname(__DIR__) . '/app/cache';
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        return null;
    }
    return is_writable($carpeta) ? $carpeta : null;
}

/**
 * Pide algo a georef. Devuelve null ante cualquier problema: quien llama
 * siempre tiene que tener un plan B.
 *
 * @param callable|null $traer Sólo para las pruebas: reemplaza la llamada real.
 */
function sn_georef_pedir(string $recurso, array $parametros, ?callable $traer = null): ?array
{
    $consulta = http_build_query($parametros);
    $clave = $recurso . '-' . md5($consulta);
    // Con un traedor inyectado no se toca el caché: si no, una prueba
    // contaminaría a la siguiente, y peor, no se estaría probando el camino
    // real sino lo que quedó guardado.
    $carpeta = $traer === null ? sn_georef_carpeta() : null;
    $archivo = $carpeta !== null ? $carpeta . '/georef-' . $clave . '.json' : null;

    if ($archivo !== null && is_readable($archivo) && (time() - (int) filemtime($archivo)) < SN_GEOREF_CACHE) {
        $guardado = json_decode((string) file_get_contents($archivo), true);
        if (is_array($guardado)) {
            return $guardado;
        }
    }

    $url = SN_GEOREF_BASE . '/' . $recurso . '?' . $consulta;
    if ($traer !== null) {
        $crudo = $traer($url);
    } else {
        $contexto = stream_context_create(['http' => [
            'timeout' => SN_GEOREF_ESPERA,
            'header'  => "Accept: application/json\r\nUser-Agent: Seguranet\r\n",
            'ignore_errors' => true,
        ]]);
        $crudo = @file_get_contents($url, false, $contexto);
    }
    if (!is_string($crudo) || $crudo === '') {
        error_log('Seguranet georef: sin respuesta de ' . $url);
        return null;
    }

    $datos = json_decode($crudo, true);
    if (!is_array($datos)) {
        return null;
    }
    // Sólo se guarda lo que trae contenido. Cachear una respuesta vacía o
    // truncada significaría servirla durante treinta días.
    $traeAlgo = !empty($datos[$recurso]) && is_array($datos[$recurso]);
    if ($archivo !== null && $traeAlgo) {
        @file_put_contents($archivo, $crudo, LOCK_EX);
    }
    return $datos;
}

/**
 * Provincias. Siempre devuelve las 24: si georef no está, salen de la lista
 * incluida. Un formulario sin provincias no es una opción.
 */
function sn_provincias(?callable $traer = null): array
{
    $respuesta = sn_georef_pedir('provincias', ['campos' => 'id,nombre', 'max' => 30], $traer);
    $lista = [];
    foreach ($respuesta['provincias'] ?? [] as $p) {
        if (isset($p['id'], $p['nombre']) && isset(SN_PROVINCIAS[$p['id']])) {
            $lista[$p['id']] = $p['nombre'];
        }
    }
    if ($lista === []) {
        foreach (SN_PROVINCIAS as $id => $datos) {
            $lista[$id] = $datos['nombre'];
        }
    }
    asort($lista, SORT_LOCALE_STRING);
    return $lista;
}

/**
 * Localidades de una provincia. Devuelve [] si georef no responde: en ese caso
 * la pantalla ofrece escribir la localidad a mano.
 */
function sn_localidades(string $provinciaId, ?callable $traer = null): array
{
    if (!isset(SN_PROVINCIAS[$provinciaId])) {
        return [];
    }
    $respuesta = sn_georef_pedir('localidades', [
        'provincia' => $provinciaId,
        'campos'    => 'nombre',
        'max'       => 5000,
        'orden'     => 'nombre',
    ], $traer);

    $lista = [];
    foreach ($respuesta['localidades'] ?? [] as $l) {
        if (isset($l['nombre'])) {
            $lista[] = $l['nombre'];
        }
    }
    return array_values(array_unique($lista));
}

/** Deja el código postal en forma comparable. */
function sn_cp_normalizar(string $cp): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cp) ?? '');
}

/**
 * Valida el código postal contra la provincia elegida.
 *
 * Acepta las dos formas que usa la gente:
 *   · CPA completo: B1636FDA (letra + 4 dígitos + 3 letras).
 *   · El viejo de 4 dígitos: 1636. Válido, pero sin provincia adentro, así que
 *     no se puede contrastar.
 *
 * @return array{ok:bool, error:string, cpa:string, sugerencia:string}
 */
function sn_cp_validar(string $cp, string $provinciaId = ''): array
{
    $cp = sn_cp_normalizar($cp);

    // Ojo con el operador + entre arrays: conserva el valor de la IZQUIERDA en
    // las claves repetidas. Con `$vacio + ['error' => ...]` el mensaje se perdía
    // y la validación rechazaba sin decir por qué. Por eso se arma entero.
    $rechazo = static fn(string $error, string $sugerencia = ''): array =>
        ['ok' => false, 'error' => $error, 'cpa' => '', 'sugerencia' => $sugerencia];

    if ($cp === '') {
        return $rechazo('Escribí el código postal.');
    }

    // Formato viejo: 4 dígitos.
    if (preg_match('/^\d{4}$/', $cp)) {
        $sugerencia = '';
        if (isset(SN_PROVINCIAS[$provinciaId])) {
            $sugerencia = SN_PROVINCIAS[$provinciaId]['letra'] . $cp;
        }
        return ['ok' => true, 'error' => '', 'cpa' => $cp, 'sugerencia' => $sugerencia];
    }

    // CPA: letra + 4 dígitos + 3 letras.
    if (!preg_match('/^([A-Z])(\d{4})([A-Z]{3})$/', $cp, $partes)) {
        return $rechazo('El código postal tiene que ser de 4 dígitos (1636) '
                      . 'o un CPA completo (B1636FDA).');
    }

    if ($provinciaId !== '' && isset(SN_PROVINCIAS[$provinciaId])) {
        $esperada = SN_PROVINCIAS[$provinciaId]['letra'];
        if ($partes[1] !== $esperada) {
            $deQuien = '';
            foreach (SN_PROVINCIAS as $datos) {
                if ($datos['letra'] === $partes[1]) {
                    $deQuien = $datos['nombre'];
                    break;
                }
            }
            return $rechazo(
                'Ese código postal empieza con "' . $partes[1] . '"'
                . ($deQuien !== '' ? ', que corresponde a ' . $deQuien : '')
                . ', y elegiste ' . SN_PROVINCIAS[$provinciaId]['nombre']
                . '. Revisá cuál de los dos está mal.',
                $esperada . $partes[2] . $partes[3]
            );
        }
    }

    return ['ok' => true, 'error' => '', 'cpa' => $cp, 'sugerencia' => ''];
}
