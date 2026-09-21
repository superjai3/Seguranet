<?php
$v = $valores ?? [];
$e = $errores ?? [];
$marcar = static fn(string $c): string => isset($e[$c]) ? ' aria-invalid="true"' : '';
?>
<section class="sn-seccion sn-seccion--clara" style="padding-bottom:var(--sn-esp-6)">
    <div class="sn-contenedor">
        <nav aria-label="Migas de pan" style="font-size:var(--sn-txt-sm);margin-bottom:var(--sn-esp-4)">
            <a href="/">Inicio</a> › <a href="/cotizar">Cotizar</a> › Consorcio
        </nav>
        <h1>Cotizador de consorcio</h1>
        <p class="sn-medido" style="font-size:var(--sn-txt-lg)">
            Tres planes comparados en el momento, con el costo por unidad y por mes
            calculado: es el número que se lleva a la asamblea.
        </p>
    </div>
</section>

<section class="sn-seccion" style="padding-top:var(--sn-esp-8)">
    <div class="sn-contenedor">
        <div class="sn-rejilla sn-rejilla--2" style="align-items:start">

            <form method="post" action="/cotizar/consorcio" class="sn-tarjeta" novalidate>
                <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

                <?php if ($e !== []): ?>
                    <div class="sn-aviso sn-aviso--error" role="alert">
                        Revisá los campos marcados.
                    </div>
                <?php endif; ?>

                <h2 style="font-size:var(--sn-txt-xl)">El edificio</h2>

                <div class="sn-campo">
                    <label for="unidades">Unidades funcionales</label>
                    <input type="number" id="unidades" name="unidades" required min="2" max="2000"
                           value="<?= e((string) ($v['unidades'] ?? '')) ?>" placeholder="24"<?= $marcar('unidades') ?>>
                    <p class="sn-campo__ayuda">Departamentos, cocheras y locales: todo lo que paga expensas.</p>
                    <?php if (isset($e['unidades'])): ?><p class="sn-campo__error"><?= e($e['unidades']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="pisos">Pisos</label>
                    <input type="number" id="pisos" name="pisos" required min="1" max="60"
                           value="<?= e((string) ($v['pisos'] ?? '')) ?>" placeholder="8"<?= $marcar('pisos') ?>>
                    <?php if (isset($e['pisos'])): ?><p class="sn-campo__error"><?= e($e['pisos']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="antiguedad">Antigüedad del edificio</label>
                    <input type="number" id="antiguedad" name="antiguedad" required min="0" max="150"
                           value="<?= e((string) ($v['antiguedad'] ?? '')) ?>" placeholder="25"<?= $marcar('antiguedad') ?>>
                    <p class="sn-campo__ayuda">
                        En años. Pesa por las instalaciones comunes: columnas de agua,
                        tableros, el ascensor. Cuando fallan, el reclamo es de todos.
                    </p>
                    <?php if (isset($e['antiguedad'])): ?><p class="sn-campo__error"><?= e($e['antiguedad']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="ascensores">Ascensores y montacargas</label>
                    <input type="number" id="ascensores" name="ascensores" min="0" max="12"
                           value="<?= e((string) ($v['ascensores'] ?? '0')) ?>" placeholder="0">
                    <p class="sn-campo__ayuda">
                        Sólo encarecen los planes que los cubren. En el Plan Básico no
                        suman nada, porque ahí no están cubiertos.
                    </p>
                </div>

                <div class="sn-campo">
                    <label style="display:flex;gap:var(--sn-esp-3);align-items:center;font-weight:400">
                        <input type="checkbox" name="amenities" value="1"
                               style="width:auto;min-height:auto"<?= !empty($v['amenities']) ? ' checked' : '' ?>>
                        <span style="font-size:var(--sn-txt-sm)">Tiene SUM, pileta o gimnasio</span>
                    </label>
                </div>

                <h2 style="font-size:var(--sn-txt-xl);margin-top:var(--sn-esp-8)">Qué se asegura</h2>

                <div class="sn-campo">
                    <label for="suma_edificio">¿Cuánto costaría reconstruir las partes comunes?</label>
                    <div style="display:flex;align-items:stretch">
                        <span style="display:grid;place-items:center;padding-inline:var(--sn-esp-3);border:1px solid var(--sn-gris-300);border-right:0;border-radius:var(--sn-radio-sm) 0 0 var(--sn-radio-sm);background:var(--sn-gris-100)">$</span>
                        <input type="number" id="suma_edificio" name="suma_edificio" required min="10000000" step="10000000"
                               style="border-radius:0 var(--sn-radio-sm) var(--sn-radio-sm) 0"
                               value="<?= e((string) ($v['suma_edificio'] ?? '')) ?>" placeholder="300000000"<?= $marcar('suma_edificio') ?>>
                    </div>
                    <p class="sn-campo__ayuda">
                        Estructura, palier, escaleras, sala de máquinas, terraza. No el valor
                        de venta de los departamentos: el terreno no se incendia.
                    </p>
                    <?php if (isset($e['suma_edificio'])): ?><p class="sn-campo__error"><?= e($e['suma_edificio']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="limite_rc">Límite de responsabilidad civil</label>
                    <select id="limite_rc" name="limite_rc" required<?= $marcar('limite_rc') ?>>
                        <?php foreach (SN_LIMITES_RC as $monto => $etiqueta): ?>
                            <option value="<?= (int) $monto ?>"<?= (int) ($v['limite_rc'] ?? 15000000) === (int) $monto ? ' selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sn-campo__ayuda">
                        <strong>Es la decisión de fondo del ramo.</strong> Un reclamo de un
                        tercero —alguien que se cae en el palier, algo que se desprende de
                        un balcón— no tiene techo salvo el que le ponga la póliza.
                    </p>
                    <?php if (isset($e['limite_rc'])): ?><p class="sn-campo__error"><?= e($e['limite_rc']) ?></p><?php endif; ?>
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

                <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Calcular</button>
            </form>

            <div id="resultado" aria-live="polite">
                <?php if (!isset($cotizacion)): ?>
                    <div class="sn-aviso sn-aviso--dato">
                        <p><strong>Completá los datos y apretá Calcular.</strong></p>
                        <p style="margin-bottom:0">
                            Vas a ver los tres planes con el costo total y el costo por
                            unidad, y debajo de dónde sale cada número.
                        </p>
                    </div>
                <?php else: ?>
                    <?php
                    $resumen = $cotizacion['datos']['unidades'] . ' unidades · '
                        . $cotizacion['datos']['pisos'] . ' pisos · '
                        . $cotizacion['datos']['provincia']
                        . ($cotizacion['datos']['localidad'] !== '' ? ', ' . $cotizacion['datos']['localidad'] : '')
                        . ' · partes comunes ' . sn_pesos($cotizacion['datos']['edificio'])
                        . ' · RC ' . sn_pesos($cotizacion['datos']['limite_rc']);
                    $ramoCotizado = 'consorcio';
                    require __DIR__ . '/../partes/resultado-cotizacion.php';
                    ?>
                <?php endif; ?>

                <div class="sn-aviso sn-aviso--legal" style="margin-top:var(--sn-esp-6)">
                    <strong>El seguro del edificio no es optativo.</strong>
                    El Código Civil y Comercial pone entre las obligaciones del administrador
                    contratar el seguro del edificio contra incendio (art. 2067, inc. h). Por
                    eso el incendio está en los tres planes: ningún plan de acá le sirve al
                    consorcio para gastar menos sacándolo. En la Ciudad de Buenos Aires,
                    además, la Ley 941 lo controla en el registro de administradores.
                </div>

                <div class="sn-aviso sn-aviso--legal" style="margin-top:var(--sn-esp-4)">
                    <strong>Esto es una estimación, no una oferta.</strong>
                    Los importes surgen de una tabla de ejemplo incluida en este sitio: no son
                    tarifas de ninguna aseguradora. Conforme el art. 4 de la Ley 17.418, el
                    contrato de seguro se celebra con la propuesta aceptada por el asegurador,
                    y hasta entonces no obliga a ninguna de las partes. El precio en firme lo
                    emite la aseguradora sobre los datos reales del consorcio.
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .plan-fila { border-left: 4px solid var(--sn-gris-300); }
    .plan-fila--destacado { border-left-color: var(--sn-verde-500); }
</style>
