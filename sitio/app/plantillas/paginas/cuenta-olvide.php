<?php $r = $resultado ?? ['ok' => null, 'mensaje' => '']; ?>
<section class="sn-seccion">
    <div class="sn-contenedor" style="max-width:460px">
        <h1>Olvidé mi contraseña</h1>
        <p>Dejanos tu correo y te mandamos un enlace para elegir una nueva.</p>

        <?php if ($r['ok'] === true): ?>
            <div class="sn-aviso sn-aviso--ok" role="status"><?= e($r['mensaje']) ?></div>
        <?php else: ?>
            <?php if (($r['mensaje'] ?? '') !== ''): ?>
                <div class="sn-aviso sn-aviso--error" role="alert"><?= e($r['mensaje']) ?></div>
            <?php endif; ?>
            <form method="post" action="/cuenta/olvide" novalidate>
                <input type="hidden" name="token" value="<?= e(sn_token()) ?>">
                <div class="sn-campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" required autocomplete="email">
                </div>
                <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Enviarme el enlace</button>
            </form>
        <?php endif; ?>

        <p class="sn-mt-6" style="text-align:center"><a href="/cuenta/ingresar">Volver a ingresar</a></p>
    </div>
</section>
