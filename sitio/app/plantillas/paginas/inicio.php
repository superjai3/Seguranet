<section class="sn-portada">
    <div class="sn-contenedor sn-portada__interior">
        <div>
            <h1>Un seguro que entendés, con alguien que te atiende</h1>
            <p class="sn-portada__texto">
                Te ayudamos a elegir entre las coberturas que hay en el mercado argentino
                y a entender qué cubre cada una antes de firmar. Sin letra chica escondida.
            </p>
            <div class="sn-portada__acciones">
                <a class="sn-boton sn-boton--primario" href="/cotizar">Cotizar mi seguro</a>
                <a class="sn-boton sn-boton--claro" href="/seguros">Ver coberturas</a>
            </div>
        </div>
        <div>
            <img src="/recursos/img/al-volante.webp" width="640" height="427"
                 alt="Persona al volante de un auto en ruta" loading="eager" fetchpriority="high">
        </div>
    </div>
</section>

<section class="sn-seccion">
    <div class="sn-contenedor">
        <div class="sn-centrado sn-mb-8">
            <h2>Qué asegurás con nosotros</h2>
            <p class="sn-medido--centro">
                Ocho ramos, desde lo que la ley te obliga a tener hasta lo que te conviene sumar.
            </p>
        </div>
        <div class="sn-rejilla sn-rejilla--4">
            <?php foreach ($ramos as $clave => $ramo): ?>
                <a class="sn-tarjeta sn-tarjeta--enlace" href="/seguros/<?= e($clave) ?>">
                    <span class="sn-icono"><?= sn_icono($ramo['icono']) ?></span>
                    <h3><?= e($ramo['nombre']) ?></h3>
                    <p><?= e($ramo['resumen']) ?></p>
                    <span class="sn-tarjeta__pie">Ver cobertura &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="sn-seccion sn-seccion--clara">
    <div class="sn-contenedor">
        <div class="sn-centrado sn-mb-8"><h2>Cómo trabajamos</h2></div>
        <div class="sn-rejilla sn-rejilla--3">
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('escudo') ?></span>
                <h3>Primero entender, después cotizar</h3>
                <p>Un precio sin saber qué cubre no sirve. Empezamos por tu situación
                   y recién ahí buscamos la cobertura que corresponde.</p>
            </div>
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('salud') ?></span>
                <h3>Te acompañamos en el siniestro</h3>
                <p>Es el momento en que un seguro se prueba. Te decimos qué reunir,
                   en qué plazo y cómo hacer la denuncia.</p>
            </div>
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('usuario') ?></span>
                <h3>Una persona, no un formulario</h3>
                <p>Respondemos por correo o WhatsApp dentro del horario de atención,
                   y la misma persona sigue tu caso.</p>
            </div>
        </div>
    </div>
</section>

<section class="sn-seccion sn-seccion--marca">
    <div class="sn-contenedor sn-centrado">
        <h2>¿Empezamos por tu auto?</h2>
        <p class="sn-medido--centro">
            Calculá una estimación en el momento, sin dejar datos personales.
        </p>
        <div class="sn-mt-6">
            <a class="sn-boton sn-boton--primario" href="/cotizar">Ir al cotizador</a>
        </div>
    </div>
</section>
