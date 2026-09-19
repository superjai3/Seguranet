<?php $r = $resultado ?? ['ok' => null, 'mensaje' => '']; ?>
<section class="sn-seccion">
    <div class="sn-contenedor" style="max-width:460px">
        <h1>Elegí una contraseña nueva</h1>

        <?php if ($r['ok'] === true): ?>
            <div class="sn-aviso sn-aviso--ok" role="status"><?= e($r['mensaje']) ?></div>
            <div class="sn-mt-6">
                <a class="sn-boton sn-boton--primario sn-boton--bloque" href="/cuenta/ingresar">Ingresar</a>
            </div>
        <?php else: ?>
            <?php if (($r['mensaje'] ?? '') !== ''): ?>
                <div class="sn-aviso sn-aviso--error" role="alert"><?= e($r['mensaje']) ?></div>
            <?php endif; ?>
            <form method="post" action="/cuenta/restablecer?t=<?= e($tokenCrudo) ?>" novalidate>
                <input type="hidden" name="token" value="<?= e(sn_token()) ?>">
                <div class="sn-campo">
                    <label for="clave">Contraseña nueva</label>
                    <input type="password" id="clave" name="clave" required autocomplete="new-password" minlength="10">
                    <p class="sn-campo__ayuda">Al menos 10 caracteres.</p>
                </div>
                <div class="sn-campo">
                    <label for="clave2">Repetirla</label>
                    <input type="password" id="clave2" name="clave2" required autocomplete="new-password">
                </div>
                <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Cambiar la contraseña</button>
            </form>
        <?php endif; ?>
    </div>
</section>
