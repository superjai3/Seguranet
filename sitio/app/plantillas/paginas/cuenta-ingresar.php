<?php $r = $resultado ?? ['ok' => null, 'valores' => [], 'mensaje' => '']; ?>
<section class="sn-seccion">
    <div class="sn-contenedor" style="max-width:460px">
        <h1>Ingresar</h1>

        <?php if (($r['mensaje'] ?? '') !== ''): ?>
            <div class="sn-aviso sn-aviso--error" role="alert"><?= e($r['mensaje']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['confirmada'])): ?>
            <div class="sn-aviso sn-aviso--ok" role="status">Tu cuenta quedó confirmada. Ya podés ingresar.</div>
        <?php endif; ?>

        <form method="post" action="/cuenta/ingresar" novalidate>
            <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

            <div class="sn-campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" required autocomplete="email"
                       value="<?= e($r['valores']['correo'] ?? '') ?>">
            </div>

            <div class="sn-campo">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" required autocomplete="current-password">
            </div>

            <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Ingresar</button>
        </form>

        <p class="sn-mt-6" style="text-align:center">
            <a href="/cuenta/olvide">Olvidé mi contraseña</a><br>
            ¿No tenés cuenta? <a href="/cuenta/registrar">Creá una</a>
        </p>
    </div>
</section>
