<?php
$v = $valores ?? [];
$e = $errores ?? [];
$anioActual = (int) date('Y');
$marcar = static fn(string $c): string => isset($e[$c]) ? ' aria-invalid="true"' : '';
?>
<section class="sn-seccion sn-seccion--clara" style="padding-bottom:var(--sn-esp-6)">
    <div class="sn-contenedor">
        <nav aria-label="Migas de pan" style="font-size:var(--sn-txt-sm);margin-bottom:var(--sn-esp-4)">
            <a href="/">Inicio</a> › <a href="/cotizar">Cotizar</a> › Auto
        </nav>
        <h1>Cotizador de auto</h1>
        <p class="sn-medido" style="font-size:var(--sn-txt-lg)">
            Cuatro planes comparados en el momento. No pedimos DNI ni domicilio:
            para una estimación no hacen falta.
        </p>
    </div>
</section>

<section class="sn-seccion" style="padding-top:var(--sn-esp-8)">
    <div class="sn-contenedor">
        <div class="sn-rejilla sn-rejilla--2" style="align-items:start">

            <form method="post" action="/cotizar/auto" class="sn-tarjeta" novalidate>
                <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

                <?php if ($e !== []): ?>
                    <div class="sn-aviso sn-aviso--error" role="alert">
                        Revisá los campos marcados.
                    </div>
                <?php endif; ?>

                <h2 style="font-size:var(--sn-txt-xl)">El vehículo</h2>

                <div class="sn-campo">
                    <label for="marca">Marca</label>
                    <select id="marca" name="marca" required data-marca<?= $marcar('marca') ?>>
                        <option value="">Elegí una</option>
                        <?php foreach (array_keys(sn_catalogo()) as $marca): ?>
                            <option value="<?= e($marca) ?>"<?= ($v['marca'] ?? '') === $marca ? ' selected' : '' ?>>
                                <?= e($marca) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($e['marca'])): ?><p class="sn-campo__error"><?= e($e['marca']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="modelo">Modelo</label>
                    <input type="text" id="modelo" name="modelo" required list="lista-modelos"
                           value="<?= e($v['modelo'] ?? '') ?>" placeholder="Elegí la marca primero"
                           data-modelo<?= $marcar('modelo') ?>>
                    <datalist id="lista-modelos"></datalist>
                    <p class="sn-campo__ayuda" data-modelo-ayuda>Si no está en la lista, escribilo igual.</p>
                    <?php if (isset($e['modelo'])): ?><p class="sn-campo__error"><?= e($e['modelo']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="anio">Año del modelo</label>
                    <select id="anio" name="anio" required<?= $marcar('anio') ?>>
                        <option value="">Elegí uno</option>
                        <?php for ($a = $anioActual + 1; $a >= 1980; $a--): ?>
                            <option value="<?= $a ?>"<?= (int) ($v['anio'] ?? 0) === $a ? ' selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                    <?php if (isset($e['anio'])): ?><p class="sn-campo__error"><?= e($e['anio']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="valor">¿Cuánto vale hoy un auto como el tuyo?</label>
                    <div class="input-group" style="display:flex;align-items:stretch">
                        <span style="display:grid;place-items:center;padding-inline:var(--sn-esp-3);border:1px solid var(--sn-gris-300);border-right:0;border-radius:var(--sn-radio-sm) 0 0 var(--sn-radio-sm);background:var(--sn-gris-100)">$</span>
                        <input type="number" id="valor" name="valor" required min="500000" step="100000"
                               style="border-radius:0 var(--sn-radio-sm) var(--sn-radio-sm) 0"
                               value="<?= e((string) ($v['valor'] ?? '')) ?>" placeholder="15000000"<?= $marcar('valor') ?>>
                    </div>
                    <p class="sn-campo__ayuda">
                        Es la suma asegurada. Mirá publicaciones de tu mismo modelo y año.
                        Cuando conectemos InfoAuto, este dato se va a completar solo.
                    </p>
                    <?php if (isset($e['valor'])): ?><p class="sn-campo__error"><?= e($e['valor']) ?></p><?php endif; ?>
                </div>

                <h2 style="font-size:var(--sn-txt-xl);margin-top:var(--sn-esp-8)">Dónde duerme</h2>

                <div class="sn-campo">
                    <label for="provincia">Provincia</label>
                    <select id="provincia" name="provincia" required data-provincia<?= $marcar('provincia') ?>>
                        <option value="">Elegí una</option>
                        <?php foreach (($provincias ?? []) as $id => $nombre): ?>
                            <option value="<?= e((string) $id) ?>"<?= ($v['provincia'] ?? '') === (string) $id ? ' selected' : '' ?>>
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sn-campo__ayuda">Dónde pasa la noche el auto, no dónde está tu trabajo.</p>
                    <?php if (isset($e['provincia'])): ?><p class="sn-campo__error"><?= e($e['provincia']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="localidad">Localidad</label>
                    <input type="text" id="localidad" name="localidad" list="lista-localidades"
                           value="<?= e($v['localidad'] ?? '') ?>" placeholder="Elegí la provincia primero" data-localidad>
                    <datalist id="lista-localidades"></datalist>
                    <p class="sn-campo__ayuda" data-localidad-ayuda>Salen del servicio oficial de datos geográficos.</p>
                </div>

                <div class="sn-campo">
                    <label for="cp">Código postal <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                    <input type="text" id="cp" name="cp" maxlength="8" value="<?= e($v['cp'] ?? '') ?>"
                           placeholder="1636 o B1636FDA"<?= $marcar('cp') ?>>
                    <?php if (isset($e['cp'])): ?><p class="sn-campo__error"><?= e($e['cp']) ?></p><?php endif; ?>
                </div>

                <h2 style="font-size:var(--sn-txt-xl);margin-top:var(--sn-esp-8)">Vos y el uso</h2>

                <div class="sn-campo">
                    <label for="edad">Edad del conductor</label>
                    <input type="number" id="edad" name="edad" required min="18" max="99"
                           value="<?= e((string) ($v['edad'] ?? '')) ?>" placeholder="35"<?= $marcar('edad') ?>>
                    <?php if (isset($e['edad'])): ?><p class="sn-campo__error"><?= e($e['edad']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="uso">Uso</label>
                    <select id="uso" name="uso">
                        <option value="particular"<?= ($v['uso'] ?? '') !== 'comercial' ? ' selected' : '' ?>>Particular</option>
                        <option value="comercial"<?= ($v['uso'] ?? '') === 'comercial' ? ' selected' : '' ?>>Comercial (reparto, aplicaciones)</option>
                    </select>
                </div>

                <fieldset style="border:1px solid var(--sn-gris-300);border-radius:var(--sn-radio);padding:var(--sn-esp-4);margin-bottom:var(--sn-esp-6)">
                    <legend style="font-size:var(--sn-txt-sm);font-weight:600;color:var(--sn-azul-900);padding-inline:var(--sn-esp-2)">Adicionales</legend>
                    <?php
                    $adicionales = [
                        'gnc'     => ['Tiene equipo de GNC', 'Sube la prima: más riesgo de incendio.'],
                        'rastreo' => ['Tiene rastreo satelital', 'Descuenta en los planes que cubren robo.'],
                        'ajuste'  => ['Con cláusula de ajuste automático', 'La suma asegurada se actualiza sola.'],
                    ];
                    foreach ($adicionales as $campo => [$etiqueta, $ayuda]): ?>
                        <label style="display:flex;gap:var(--sn-esp-3);align-items:flex-start;font-weight:400;margin-bottom:var(--sn-esp-3)">
                            <input type="checkbox" name="<?= e($campo) ?>" value="1"
                                   style="width:auto;min-height:auto;margin-top:.35rem"
                                   <?= !empty($v[$campo]) ? 'checked' : '' ?>>
                            <span style="font-size:var(--sn-txt-sm)">
                                <strong><?= e($etiqueta) ?></strong><br>
                                <span style="color:var(--sn-gris-500)"><?= e($ayuda) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Calcular</button>
            </form>

            <div id="resultado" aria-live="polite">
                <?php if (!isset($cotizacion)): ?>
                    <div class="sn-aviso sn-aviso--dato">
                        <p><strong>Completá los datos y apretá Calcular.</strong></p>
                        <p style="margin-bottom:0">
                            Vas a ver los cuatro planes con su precio y, debajo, exactamente
                            de dónde sale cada número.
                        </p>
                    </div>
                <?php else: ?>
                    <h2 style="font-size:var(--sn-txt-xl)">Tu estimación</h2>
                    <p style="color:var(--sn-gris-500);font-size:var(--sn-txt-sm)">
                        <?= e($cotizacion['datos']['vehiculo']) ?> ·
                        <?= e($cotizacion['datos']['provincia']) ?>
                        <?= $cotizacion['datos']['localidad'] !== '' ? ', ' . e($cotizacion['datos']['localidad']) : '' ?>
                        · suma asegurada <?= e(sn_pesos($cotizacion['datos']['valor'])) ?>
                    </p>

                    <?php foreach ($cotizacion['planes'] as $plan): ?>
                        <div class="sn-tarjeta plan-fila<?= $plan['destacado'] ? ' plan-fila--destacado' : '' ?>"
                             style="margin-bottom:var(--sn-esp-3)">
                            <div style="display:flex;justify-content:space-between;gap:var(--sn-esp-4);flex-wrap:wrap">
                                <div style="flex:1 1 55%">
                                    <h3 style="font-size:var(--sn-txt-lg);margin-bottom:var(--sn-esp-1)">
                                        <?= e($plan['nombre']) ?>
                                        <?php if ($plan['destacado']): ?>
                                            <span style="display:inline-block;background:var(--sn-verde-100);color:var(--sn-verde-600);font-size:var(--sn-txt-xs);padding:2px 8px;border-radius:99px;vertical-align:middle">más elegido</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500)"><?= e($plan['resumen']) ?></p>
                                    <ul style="font-size:var(--sn-txt-sm);margin:var(--sn-esp-2) 0 0;padding-left:1.1rem">
                                        <?php foreach ($plan['incluye'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                                    </ul>
                                </div>
                                <div style="text-align:right;flex:0 0 auto">
                                    <div style="font-size:1.6rem;font-weight:700;color:var(--sn-verde-600);line-height:1.1">
                                        <?= e(sn_pesos($plan['mensual'])) ?>
                                    </div>
                                    <div style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500)">por mes</div>
                                    <div style="font-size:var(--sn-txt-xs);color:var(--sn-gris-500)"><?= e(sn_pesos($plan['anual'])) ?> al año</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <details class="sn-tarjeta" style="margin-bottom:var(--sn-esp-4)">
                        <summary style="cursor:pointer;font-weight:600;color:var(--sn-azul-900)">Cómo se calculó</summary>
                        <table style="width:100%;border-collapse:collapse;margin-top:var(--sn-esp-4);font-size:var(--sn-txt-sm)">
                            <tbody>
                                <?php foreach ($cotizacion['factores'] as [$nombre, $detalle, $factor]): ?>
                                    <tr style="border-bottom:1px solid var(--sn-gris-100)">
                                        <td style="padding:var(--sn-esp-2) 0"><strong><?= e($nombre) ?></strong></td>
                                        <td style="padding:var(--sn-esp-2) 0;color:var(--sn-gris-500)"><?= e($detalle) ?></td>
                                        <td style="padding:var(--sn-esp-2) 0;text-align:right">&times;<?= number_format($factor, 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="2" style="padding:var(--sn-esp-3) 0"><strong>Recargo total</strong></td>
                                    <td style="padding:var(--sn-esp-3) 0;text-align:right"><strong>&times;<?= number_format($cotizacion['recargo'], 2, ',', '.') ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </details>

                    <a class="sn-boton sn-boton--secundario sn-boton--bloque"
                       href="/contacto?ramo=automotor">Quiero el precio en firme</a>
                <?php endif; ?>

                <div class="sn-aviso sn-aviso--legal" style="margin-top:var(--sn-esp-6)">
                    <strong>Esto es una estimación, no una oferta.</strong>
                    Los importes surgen de una tabla de ejemplo incluida en este sitio: no son
                    tarifas de ninguna aseguradora. Conforme el art. 4 de la Ley 17.418, el
                    contrato de seguro se celebra con la propuesta aceptada por el asegurador,
                    y hasta entonces no obliga a ninguna de las partes. El precio en firme lo
                    emite la aseguradora sobre tus datos reales.
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .plan-fila { border-left: 4px solid var(--sn-gris-300); }
    .plan-fila--destacado { border-left-color: var(--sn-verde-500); }
</style>

<script>
    /* Modelos por marca: el catálogo viaja con la página, así que cambiar de
       marca no pide nada a la red. */
    window.SN_CATALOGO = <?= json_encode(sn_catalogo(), JSON_UNESCAPED_UNICODE) ?>;
</script>
