<section class="sn-seccion">
    <div class="sn-contenedor" style="max-width:520px">
        <h1><?= $resultado['ok'] ? 'Cuenta confirmada' : 'No pudimos confirmar' ?></h1>
        <div class="sn-aviso <?= $resultado['ok'] ? 'sn-aviso--ok' : 'sn-aviso--error' ?>" role="status">
            <?= e($resultado['mensaje']) ?>
        </div>
        <div class="sn-mt-6">
            <a class="sn-boton sn-boton--primario sn-boton--bloque"
               href="<?= $resultado['ok'] ? '/cuenta/ingresar?confirmada=1' : '/cuenta/registrar' ?>">
                <?= $resultado['ok'] ? 'Ingresar' : 'Volver a registrarme' ?>
            </a>
        </div>
    </div>
</section>
