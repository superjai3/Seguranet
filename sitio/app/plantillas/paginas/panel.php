<?php
$filtro   = $filtro ?? '';
$resumen  = $resumen ?? [];
$listado  = $listado ?? ['consultas' => [], 'total' => 0, 'pagina' => 1, 'paginas' => 1];
// Arma la URL del panel sin parámetros vacíos: "/panel" y no "/panel?estado=".
$enlace = static function (string $estado, int $pagina = 1): string {
    $partes = [];
    if ($estado !== '') { $partes['estado'] = $estado; }
    if ($pagina > 1)    { $partes['pagina'] = $pagina; }
    return '/panel' . ($partes === [] ? '' : '?' . http_build_query($partes));
};
?>
<section class="sn-seccion sn-seccion--clara" style="padding-bottom:var(--sn-esp-6)">
    <div class="sn-contenedor">
        <h1>Consultas</h1>
        <p class="sn-medido" style="font-size:var(--sn-txt-lg)">
            Las más viejas arriba: una consulta de hace tres días es más urgente que
            la de hace una hora.
        </p>
    </div>
</section>

<section class="sn-seccion" style="padding-top:var(--sn-esp-6)">
    <div class="sn-contenedor">

        <?php if (isset($mensaje) && $mensaje !== ''): ?>
            <div class="sn-aviso sn-aviso--dato" role="status"><?= e($mensaje) ?></div>
        <?php endif; ?>

        <?php if (isset($error) && $error !== ''): ?>
            <div class="sn-aviso sn-aviso--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!($hayBase ?? true)): ?>
            <div class="sn-aviso sn-aviso--error">
                No hay conexión con la base de datos, así que no se pueden mostrar las
                consultas. Revisá <code>app/config.local.php</code>.
            </div>
        <?php else: ?>

        <nav aria-label="Filtrar por estado" style="display:flex;flex-wrap:wrap;gap:var(--sn-esp-2);margin-bottom:var(--sn-esp-6)">
            <a class="sn-boton <?= $filtro === '' ? 'sn-boton--primario' : 'sn-boton--linea' ?>"
               href="/panel">Todas (<?= (int) array_sum($resumen) ?>)</a>
            <?php foreach (SN_ESTADOS_CONSULTA as $clave => $etiqueta): ?>
                <a class="sn-boton <?= $filtro === $clave ? 'sn-boton--primario' : 'sn-boton--linea' ?>"
                   href="<?= e($enlace($clave)) ?>">
                    <?= e($etiqueta) ?> (<?= (int) ($resumen[$clave] ?? 0) ?>)
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($listado['consultas'] === []): ?>
            <div class="sn-aviso sn-aviso--dato">
                <?= $filtro === ''
                    ? 'Todavía no llegó ninguna consulta.'
                    : 'No hay consultas en este estado.' ?>
            </div>
        <?php endif; ?>

        <?php foreach ($listado['consultas'] as $c): ?>
            <article class="sn-tarjeta" style="margin-bottom:var(--sn-esp-4);border-left:4px solid <?=
                $c['estado'] === 'nueva' ? 'var(--sn-verde-500)' :
                ($c['estado'] === 'en_curso' ? 'var(--sn-turquesa)' : 'var(--sn-gris-300)') ?>">

                <header style="display:flex;justify-content:space-between;gap:var(--sn-esp-4);flex-wrap:wrap;align-items:baseline">
                    <h2 style="font-size:var(--sn-txt-lg);margin:0"><?= e($c['nombre']) ?></h2>
                    <span style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500)">
                        <?= e(SN_ESTADOS_CONSULTA[$c['estado']] ?? $c['estado']) ?>
                        · <?= e(sn_hace_cuanto((string) $c['creada_en'])) ?>
                    </span>
                </header>

                <p style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500);margin-top:var(--sn-esp-1)">
                    <a href="mailto:<?= e($c['correo']) ?>"><?= e($c['correo']) ?></a>
                    <?php if (($c['telefono'] ?? '') !== ''): ?>
                        <?php /* tel: y no un enlace de WhatsApp: la gente escribe el
                               teléfono como quiere y armar un wa.me exige adivinar el
                               código de país. Adivinar mal abre un chat con otra
                               persona, así que mejor que el sistema operativo decida. */ ?>
                        · <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $c['telefono'])) ?>"><?= e($c['telefono']) ?></a>
                    <?php endif; ?>
                    <?php if (($c['ramo'] ?? '') !== '' && isset($ramos[$c['ramo']])): ?>
                        · <?= e($ramos[$c['ramo']]['titulo']) ?>
                    <?php endif; ?>
                    <?php if (($c['localidad'] ?? '') !== '' || ($c['provincia'] ?? '') !== ''): ?>
                        · <?= e(trim(($c['localidad'] ?? '') . (isset(SN_PROVINCIAS[$c['provincia']])
                                ? ', ' . SN_PROVINCIAS[$c['provincia']]['nombre'] : ''), ', ')) ?>
                    <?php endif; ?>
                </p>

                <p style="white-space:pre-line;margin-top:var(--sn-esp-3)"><?= e($c['mensaje']) ?></p>

                <form method="post" action="/panel" style="margin-top:var(--sn-esp-4);display:flex;gap:var(--sn-esp-3);flex-wrap:wrap;align-items:flex-end">
                    <input type="hidden" name="token" value="<?= e(sn_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="hidden" name="volver" value="<?= e($enlace($filtro, (int) $listado['pagina'])) ?>">

                    <div class="sn-campo" style="flex:1 1 12rem;margin-bottom:0">
                        <label for="estado-<?= (int) $c['id'] ?>">Estado</label>
                        <select id="estado-<?= (int) $c['id'] ?>" name="estado">
                            <?php foreach (SN_ESTADOS_CONSULTA as $clave => $etiqueta): ?>
                                <option value="<?= e($clave) ?>"<?= $c['estado'] === $clave ? ' selected' : '' ?>>
                                    <?= e($etiqueta) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sn-campo" style="flex:3 1 18rem;margin-bottom:0">
                        <label for="nota-<?= (int) $c['id'] ?>">Nota interna</label>
                        <input type="text" id="nota-<?= (int) $c['id'] ?>" name="nota" maxlength="1000"
                               value="<?= e((string) ($c['nota'] ?? '')) ?>"
                               placeholder="Llamé, no atendió. Reintentar el lunes.">
                    </div>

                    <button class="sn-boton sn-boton--secundario" type="submit">Guardar</button>
                </form>
            </article>
        <?php endforeach; ?>

        <?php if ((int) $listado['paginas'] > 1): ?>
            <nav aria-label="Páginas" style="display:flex;gap:var(--sn-esp-2);flex-wrap:wrap;margin-top:var(--sn-esp-6)">
                <?php for ($p = 1; $p <= (int) $listado['paginas']; $p++): ?>
                    <a class="sn-boton <?= $p === (int) $listado['pagina'] ? 'sn-boton--primario' : 'sn-boton--linea' ?>"
                       href="<?= e($enlace($filtro, $p)) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>

        <?php endif; ?>

        <div class="sn-aviso sn-aviso--legal sn-mt-8">
            <strong>Datos personales.</strong>
            Acá se ven nombre, correo, teléfono y zona de personas que escribieron
            confiando en que se usarían para responderles. No se usan para otra cosa,
            no se ceden, y se borran según el plazo de la política de privacidad
            (Ley 25.326). Si alguien pide que se borren sus datos, hay que hacerlo.
        </div>
    </div>
</section>
