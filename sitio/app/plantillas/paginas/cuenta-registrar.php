<?php
$r = $resultado ?? ['ok' => null, 'errores' => [], 'valores' => [], 'mensaje' => ''];
$v = $r['valores']; $errores = $r['errores'];
?>
<section class="sn-seccion">
    <div class="sn-contenedor" style="max-width:520px">
        <h1>Crear una cuenta</h1>
        <p>Guardá tus cotizaciones y seguí tus consultas desde un solo lugar.</p>

        <?php if ($r['ok'] === true): ?>
            <div class="sn-aviso sn-aviso--ok" role="status"><?= e($r['mensaje']) ?></div>
        <?php else: ?>
            <?php if ($r['mensaje'] !== ''): ?>
                <div class="sn-aviso sn-aviso--error" role="alert"><?= e($r['mensaje']) ?></div>
            <?php endif; ?>

            <form method="post" action="/cuenta/registrar" novalidate>
                <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

                <div class="sn-campo">
                    <label for="nombre">Nombre y apellido</label>
                    <input type="text" id="nombre" name="nombre" required autocomplete="name"
                           value="<?= e($v['nombre'] ?? '') ?>">
                    <?php if (isset($errores['nombre'])): ?><p class="sn-campo__error"><?= e($errores['nombre']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" required autocomplete="email"
                           value="<?= e($v['correo'] ?? '') ?>">
                    <p class="sn-campo__ayuda">Te vamos a mandar un enlace para confirmarlo.</p>
                    <?php if (isset($errores['correo'])): ?><p class="sn-campo__error"><?= e($errores['correo']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="clave">Contraseña</label>
                    <input type="password" id="clave" name="clave" required autocomplete="new-password" minlength="10">
                    <p class="sn-campo__ayuda">Al menos 10 caracteres. Una frase que recuerdes sirve más que un galimatías corto.</p>
                    <?php if (isset($errores['clave'])): ?><p class="sn-campo__error"><?= e($errores['clave']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label for="clave2">Repetir la contraseña</label>
                    <input type="password" id="clave2" name="clave2" required autocomplete="new-password">
                    <?php if (isset($errores['clave2'])): ?><p class="sn-campo__error"><?= e($errores['clave2']) ?></p><?php endif; ?>
                </div>

                <div class="sn-campo">
                    <label style="display:flex;gap:var(--sn-esp-3);align-items:flex-start;font-weight:400">
                        <input type="checkbox" name="condiciones" value="1" required
                               style="width:auto;min-height:auto;margin-top:.35rem">
                        <span style="font-size:var(--sn-txt-sm)">
                            Acepto los <a href="/legales/terminos">términos y condiciones</a> y la
                            <a href="/legales/privacidad">política de privacidad</a>.
                        </span>
                    </label>
                    <?php if (isset($errores['condiciones'])): ?><p class="sn-campo__error"><?= e($errores['condiciones']) ?></p><?php endif; ?>
                </div>

                <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Crear cuenta</button>
            </form>
        <?php endif; ?>

        <p class="sn-mt-6" style="text-align:center">
            ¿Ya tenés cuenta? <a href="/cuenta/ingresar">Ingresá</a>
        </p>
    </div>
</section>
