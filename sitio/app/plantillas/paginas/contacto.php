<?php
$resultado = $resultado ?? ['ok' => null, 'errores' => [], 'valores' => [], 'mensaje' => ''];
$errores   = $resultado['errores'];
$v         = $resultado['valores'];
$ramoPrevio = $v['ramo'] ?? (string) ($_GET['ramo'] ?? '');

/** Atributos de error para un campo, en un solo lugar. */
$marcar = static function (string $campo) use ($errores): string {
    return isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="err-' . $campo . '"' : '';
};
?>
<section class="sn-seccion">
    <div class="sn-contenedor">
        <div class="sn-medido sn-mb-8">
            <h1>Contacto</h1>
            <p style="font-size:var(--sn-txt-lg)">
                Escribinos por donde te quede más cómodo. Respondemos dentro del horario
                de atención: <?= e($config['sitio']['horario']) ?>.
            </p>
        </div>

        <div class="sn-rejilla sn-rejilla--2">

            <div>
                <h2 style="font-size:var(--sn-txt-xl)">Dejanos tu consulta</h2>

                <?php if ($resultado['ok'] === true): ?>
                    <div class="sn-aviso sn-aviso--ok" role="status">
                        <?= e($resultado['mensaje']) ?>
                    </div>
                <?php elseif ($resultado['ok'] === false && $resultado['mensaje'] !== ''): ?>
                    <div class="sn-aviso sn-aviso--error" role="alert">
                        <?= e($resultado['mensaje']) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/contacto" novalidate>
                    <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

                    <?php /* Trampa para robots. Invisible y fuera del recorrido del
                             teclado; una persona no la ve ni la completa. */ ?>
                    <div style="position:absolute;left:-9999px" aria-hidden="true">
                        <label for="sitio_web">No completar</label>
                        <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="sn-campo">
                        <label for="nombre">Nombre y apellido</label>
                        <input type="text" id="nombre" name="nombre" required autocomplete="name"
                               value="<?= e($v['nombre'] ?? '') ?>"<?= $marcar('nombre') ?>>
                        <?php if (isset($errores['nombre'])): ?>
                            <p class="sn-campo__error" id="err-nombre"><?= e($errores['nombre']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="sn-campo">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" required autocomplete="email"
                               value="<?= e($v['correo'] ?? '') ?>"<?= $marcar('correo') ?>>
                        <?php if (isset($errores['correo'])): ?>
                            <p class="sn-campo__error" id="err-correo"><?= e($errores['correo']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="sn-campo">
                        <label for="telefono">Teléfono <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                        <input type="tel" id="telefono" name="telefono" autocomplete="tel"
                               value="<?= e($v['telefono'] ?? '') ?>"<?= $marcar('telefono') ?>>
                        <p class="sn-campo__ayuda">Si preferís que te llamemos o te escribamos por WhatsApp.</p>
                        <?php if (isset($errores['telefono'])): ?>
                            <p class="sn-campo__error" id="err-telefono"><?= e($errores['telefono']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="sn-campo">
                        <label for="ramo">¿Sobre qué seguro?</label>
                        <select id="ramo" name="ramo"<?= $marcar('ramo') ?>>
                            <option value="">Elegí uno</option>
                            <?php foreach ($ramos as $clave => $ramo): ?>
                                <option value="<?= e($clave) ?>"<?= $ramoPrevio === $clave ? ' selected' : '' ?>>
                                    <?= e($ramo['titulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php /* Zona del riesgo. Opcional en una consulta, pero es lo que
                             más ajusta un precio: la aseguradora tarifa por dónde
                             duerme el auto o dónde está el inmueble. */ ?>
                    <fieldset style="border:1px solid var(--sn-gris-300);border-radius:var(--sn-radio);padding:var(--sn-esp-4);margin-bottom:var(--sn-esp-4)">
                        <legend style="font-size:var(--sn-txt-sm);font-weight:600;color:var(--sn-azul-900);padding-inline:var(--sn-esp-2)">
                            ¿Dónde? <span style="font-weight:400;color:var(--sn-gris-500)">(opcional, pero ajusta el precio)</span>
                        </legend>

                        <div class="sn-campo">
                            <label for="provincia">Provincia</label>
                            <select id="provincia" name="provincia" data-provincia>
                                <option value="">Elegí una</option>
                                <?php foreach (($provincias ?? []) as $id => $nombre): ?>
                                    <option value="<?= e((string) $id) ?>"<?= ($v['provincia'] ?? '') === (string) $id ? ' selected' : '' ?>>
                                        <?= e($nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errores['provincia'])): ?>
                                <p class="sn-campo__error"><?= e($errores['provincia']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="sn-campo">
                            <label for="localidad">Localidad</label>
                            <input type="text" id="localidad" name="localidad" list="lista-localidades"
                                   autocomplete="address-level2" value="<?= e($v['localidad'] ?? '') ?>"
                                   placeholder="Elegí la provincia primero" data-localidad>
                            <datalist id="lista-localidades"></datalist>
                            <p class="sn-campo__ayuda" data-localidad-ayuda>
                                Las localidades salen del servicio oficial de datos geográficos del Estado.
                            </p>
                        </div>

                        <div class="sn-campo" style="margin-bottom:0">
                            <label for="cp">Código postal</label>
                            <input type="text" id="cp" name="cp" inputmode="text" maxlength="8"
                                   autocomplete="postal-code" value="<?= e($v['cp'] ?? '') ?>"
                                   placeholder="1636 o B1636FDA"
                                   <?= isset($errores['cp']) ? 'aria-invalid="true"' : '' ?>>
                            <p class="sn-campo__ayuda">
                                Sirven los cuatro dígitos de siempre o el CPA completo.
                            </p>
                            <?php if (isset($errores['cp'])): ?>
                                <p class="sn-campo__error"><?= e($errores['cp']) ?></p>
                            <?php endif; ?>
                        </div>
                    </fieldset>

                    <div class="sn-campo">
                        <label for="mensaje">Tu consulta</label>
                        <textarea id="mensaje" name="mensaje" rows="5" required<?= $marcar('mensaje') ?>><?= e($v['mensaje'] ?? '') ?></textarea>
                        <?php if (isset($errores['mensaje'])): ?>
                            <p class="sn-campo__error" id="err-mensaje"><?= e($errores['mensaje']) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php /* Consentimiento expreso: sin esto no se pueden tratar los
                             datos (Ley 25.326). No viene tildado por defecto. */ ?>
                    <div class="sn-campo">
                        <label style="display:flex;gap:var(--sn-esp-3);align-items:flex-start;font-weight:400">
                            <input type="checkbox" name="consentimiento" value="1" required
                                   style="width:auto;min-height:auto;margin-top:.35rem"
                                   <?= isset($errores['consentimiento']) ? 'aria-invalid="true"' : '' ?>>
                            <span style="font-size:var(--sn-txt-sm)">
                                Presto mi conformidad para que Seguranet trate mis datos con el fin de
                                responder esta consulta, según la
                                <a href="/legales/privacidad">política de privacidad</a>.
                            </span>
                        </label>
                        <?php if (isset($errores['consentimiento'])): ?>
                            <p class="sn-campo__error"><?= e($errores['consentimiento']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">
                        Enviar consulta
                    </button>
                </form>
            </div>

            <div>
                <h2 style="font-size:var(--sn-txt-xl)">Otras formas</h2>

                <div class="sn-tarjeta sn-mb-8">
                    <h3 style="font-size:var(--sn-txt-lg)">Correo</h3>
                    <p><a href="mailto:<?= e($config['sitio']['correo']) ?>"><?= e($config['sitio']['correo']) ?></a></p>

                    <h3 style="font-size:var(--sn-txt-lg);margin-top:var(--sn-esp-6)">WhatsApp</h3>
                    <p>
                        <a href="<?= e(sn_whatsapp($config, 'Hola, quiero consultar por un seguro.')) ?>"
                           target="_blank" rel="noopener">Abrir conversación</a>
                    </p>

                    <h3 style="font-size:var(--sn-txt-lg);margin-top:var(--sn-esp-6)">Horario de atención</h3>
                    <p><?= e($config['sitio']['horario']) ?></p>
                </div>

                <div class="sn-aviso sn-aviso--dato">
                    <strong>¿Tuviste un siniestro?</strong>
                    No lo dejes para el correo: hay plazos que corren.
                    Mirá <a href="/siniestros">qué hacer y en cuánto tiempo</a>.
                </div>
            </div>
        </div>
    </div>
</section>
