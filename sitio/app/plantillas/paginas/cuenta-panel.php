<?php $u = sn_usuario(); ?>
<section class="sn-seccion">
    <div class="sn-contenedor">
        <h1>Hola, <?= e($u['nombre']) ?></h1>
        <p>Desde acá vas a poder seguir tus cotizaciones y consultas.</p>

        <div class="sn-rejilla sn-rejilla--3 sn-mt-8">
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('auto') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Cotizar</h2>
                <p>Calculá una estimación para tu auto o pedí una propuesta para otro ramo.</p>
                <div class="sn-mt-6"><a class="sn-boton sn-boton--primario sn-boton--bloque" href="/cotizar">Ir al cotizador</a></div>
            </div>
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('escudo') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Mis cotizaciones</h2>
                <p>Todavía no guardaste ninguna. Cuando cotices desde tu cuenta, van a aparecer acá.</p>
            </div>
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('usuario') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Mis datos</h2>
                <p><?= e($u['correo']) ?></p>
                <div class="sn-mt-6">
                    <form method="post" action="/cuenta/salir">
                        <input type="hidden" name="token" value="<?= e(sn_token()) ?>">
                        <button class="sn-boton sn-boton--linea sn-boton--bloque" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
