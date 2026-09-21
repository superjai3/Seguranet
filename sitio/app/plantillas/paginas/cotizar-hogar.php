<?php
$v = $valores ?? [];
$e = $errores ?? [];
$marcar = static fn(string $c): string => isset($e[$c]) ? ' aria-invalid="true"' : '';
$condicion = $v['condicion'] ?? 'propietario';
?>
<section class="sn-seccion sn-seccion--clara" style="padding-bottom:var(--sn-esp-6)">
    <div class="sn-contenedor">
        <nav aria-label="Migas de pan" style="font-size:var(--sn-txt-sm);margin-bottom:var(--sn-esp-4)">
            <a href="/">Inicio</a> › <a href="/cotizar">Cotizar</a> › Hogar
        </nav>
        <h1>Cotizador de hogar</h1>
        <p class="sn-medido" style="font-size:var(--sn-txt-lg)">
            Tres planes comparados en el momento. Si alquilás, no te cobramos el
            edificio: no es tuyo.
        </p>
    </div>
</section>

<section class="sn-seccion" style="padding-top:var(--sn-esp-8)">
    <div class="sn-contenedor">
        <div class="sn-rejilla sn-rejilla--2" style="align-items:start">

            <form method="post" action="/cotizar/hogar" class="sn-tarjeta" novalidate>
                <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

                <?php if ($e !== []): ?>
                    <div class="sn-aviso sn-aviso--error" role="alert">
                        Revisá los campos marcados.
                    </div>
                <?php endif; ?>

                <h2 style="font-size:var(--sn-txt-xl)">La vivienda</h2>

                <div class="sn-campo">
                    <label for="condicion">¿Sos propietario o inquilino?</label>
                    <select id="condicion" name="condicion" required data-condicion<?= $marcar('condicion') ?>>
                        <option value="propietario"<?= $condicion !== 'inquilino' ? ' selected' : '' ?>>Propietario</option>
                        <option value="inquilino"<?= $condicion === 'inquilino' ? ' selected' : '' ?>>Inquilino</option>
                    </select>
                    <p class="sn-campo__ayuda">
                        Cambia qué se asegura: el inquilino cubre lo suyo y su
                        responsabilidad, no el edificio del dueño.
                    </p>
                    <?php if (isset($e['condicion'])): ?><p class="sn-campo__error"><?= e($e['condicion']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="tipo">Tipo de vivienda</label>
                    <select id="tipo" name="tipo" required data-tipo<?= $marcar('tipo') ?>>
                        <?php foreach (SN_VIVIENDAS as $clave => $etiqueta): ?>
                            <option value="<?= e($clave) ?>"<?= ($v['tipo'] ?? 'departamento') === $clave ? ' selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($e['tipo'])): ?><p class="sn-campo__error"><?= e($e['tipo']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo" data-planta-baja<?= ($v['tipo'] ?? 'departamento') === 'departamento' ? '' : ' hidden' ?>>
                    <label style="display:flex;gap:var(--sn-esp-3);align-items:center;font-weight:400">
                        <input type="checkbox" name="planta_baja" value="1"
                               style="width:auto;min-height:auto"<?= !empty($v['planta_baja']) ? ' checked' : '' ?>>
                        <span style="font-size:var(--sn-txt-sm)">Está en planta baja</span>
                    </label>
                    <p class="sn-campo__ayuda">Se entra desde la calle o el patio, así que sube el riesgo de robo.</p>
                </div>

                <div class="sn-campo">
                    <label for="antiguedad">Antigüedad de la vivienda</label>
                    <input type="number" id="antiguedad" name="antiguedad" required min="0" max="150"
                           value="<?= e((string) ($v['antiguedad'] ?? '')) ?>" placeholder="15"<?= $marcar('antiguedad') ?>>
                    <p class="sn-campo__ayuda">
                        En años. Pesa por las instalaciones: la eléctrica y la de agua son
                        las que terminan en incendio o en una pérdida.
                    </p>
                    <?php if (isset($e['antiguedad'])): ?><p class="sn-campo__error"><?= e($e['antiguedad']) ?></p><?php endif; ?>
                </div>

                <h2 style="font-size:var(--sn-txt-xl);margin-top:var(--sn-esp-8)">Qué asegurás</h2>

                <div class="sn-campo" data-campo-edificio<?= $condicion === 'inquilino' ? ' hidden' : '' ?>>
                    <label for="suma_edificio">¿Cuánto costaría reconstruir la vivienda?</label>
                    <div style="display:flex;align-items:stretch">
                        <span style="display:grid;place-items:center;padding-inline:var(--sn-esp-3);border:1px solid var(--sn-gris-300);border-right:0;border-radius:var(--sn-radio-sm) 0 0 var(--sn-radio-sm);background:var(--sn-gris-100)">$</span>
                        <input type="number" id="suma_edificio" name="suma_edificio" min="5000000" step="1000000"
                               style="border-radius:0 var(--sn-radio-sm) var(--sn-radio-sm) 0"
                               value="<?= e((string) ($v['suma_edificio'] ?? '')) ?>" placeholder="40000000"<?= $marcar('suma_edificio') ?>>
                    </div>
                    <p class="sn-campo__ayuda">
                        No es lo que vale la propiedad en el mercado: el terreno no se
                        incendia. Es lo que costaría levantarla de nuevo.
                    </p>
                    <?php if (isset($e['suma_edificio'])): ?><p class="sn-campo__error"><?= e($e['suma_edificio']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="suma_contenido">¿Cuánto vale todo lo que hay adentro?</label>
                    <div style="display:flex;align-items:stretch">
                        <span style="display:grid;place-items:center;padding-inline:var(--sn-esp-3);border:1px solid var(--sn-gris-300);border-right:0;border-radius:var(--sn-radio-sm) 0 0 var(--sn-radio-sm);background:var(--sn-gris-100)">$</span>
                        <input type="number" id="suma_contenido" name="suma_contenido" required min="500000" step="500000"
                               style="border-radius:0 var(--sn-radio-sm) var(--sn-radio-sm) 0"
                               value="<?= e((string) ($v['suma_contenido'] ?? '')) ?>" placeholder="8000000"<?= $marcar('suma_contenido') ?>>
                    </div>
                    <p class="sn-campo__ayuda">
                        Muebles, electrodomésticos, ropa, herramientas. La cuenta rápida:
                        qué habría que volver a comprar si mañana no queda nada.
                    </p>
                    <?php if (isset($e['suma_contenido'])): ?><p class="sn-campo__error"><?= e($e['suma_contenido']) ?></p><?php endif; ?>
                </div>

                <h2 style="font-size:var(--sn-txt-xl);margin-top:var(--sn-esp-8)">Dónde está</h2>

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

                <div class="sn-campo">
                    <label for="seguridad">Medidas de seguridad</label>
                    <select id="seguridad" name="seguridad"<?= $marcar('seguridad') ?>>
                        <?php foreach (SN_SEGURIDAD_HOGAR as $clave => [$factor, $etiqueta]): ?>
                            <option value="<?= e((string) $clave) ?>"<?= ($v['seguridad'] ?? '') === (string) $clave ? ' selected' : '' ?>>
                                <?= e(ucfirst($etiqueta)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sn-campo__ayuda">
                        Descuentan sólo en los planes que cubren robo. En el Plan Básico no
                        cambian nada, y fingir un descuento ahí sería mentirte.
                    </p>
                    <?php if (isset($e['seguridad'])): ?><p class="sn-campo__error"><?= e($e['seguridad']) ?></p><?php endif; ?>
                </div>

                <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Calcular</button>
            </form>

            <div id="resultado" aria-live="polite">
                <?php if (!isset($cotizacion)): ?>
                    <div class="sn-aviso sn-aviso--dato">
                        <p><strong>Completá los datos y apretá Calcular.</strong></p>
                        <p style="margin-bottom:0">
                            Vas a ver los tres planes con su precio y, debajo, exactamente
                            de dónde sale cada número.
                        </p>
                    </div>
                <?php else: ?>
                    <?php
                    $resumen = ucfirst($cotizacion['datos']['vivienda']) . ' · ' . $cotizacion['datos']['condicion']
                        . ' · ' . $cotizacion['datos']['provincia']
                        . ($cotizacion['datos']['localidad'] !== '' ? ', ' . $cotizacion['datos']['localidad'] : '')
                        . ' · contenido ' . sn_pesos($cotizacion['datos']['contenido'])
                        . ($cotizacion['datos']['edificio'] > 0
                            ? ' · edificio ' . sn_pesos($cotizacion['datos']['edificio'])
                            : ' · sin edificio (alquiler)');
                    $ramoCotizado = 'hogar';
                    require __DIR__ . '/../partes/resultado-cotizacion.php';
                    ?>
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
