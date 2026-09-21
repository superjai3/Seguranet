<section class="sn-seccion">
    <div class="sn-contenedor">
        <div class="sn-medido">
            <h1>Cotizar un seguro</h1>
            <p style="font-size:var(--sn-txt-lg)">
                El cotizador en línea está disponible para automotor, hogar y consorcio.
                Para el resto de los ramos, contanos tu caso y te armamos una propuesta.
            </p>
        </div>

        <div class="sn-rejilla sn-rejilla--3 sn-mt-8">
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('auto') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Seguro de auto</h2>
                <p>Elegí marca, modelo y año, decinos dónde duerme el auto y sumá los
                   adicionales que tengas —GNC, rastreo, cláusula de ajuste—.
                   Compará los cuatro planes en el momento.</p>
                <div class="sn-mt-6">
                    <a class="sn-boton sn-boton--primario sn-boton--bloque"
                       href="/cotizar/auto">Cotizar mi auto</a>
                </div>
            </div>

            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('hogar') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Seguro de hogar</h2>
                <p>Decinos si sos propietario o inquilino, cuánto vale lo que hay adentro
                   y dónde está la vivienda. Compará los tres planes en el momento.
                   Si alquilás, no te cobramos el edificio.</p>
                <div class="sn-mt-6">
                    <a class="sn-boton sn-boton--primario sn-boton--bloque"
                       href="/cotizar/hogar">Cotizar mi hogar</a>
                </div>
            </div>

            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('edificio') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Seguro de consorcio</h2>
                <p>Decinos cuántas unidades tiene el edificio, qué antigüedad y cuántos
                   ascensores. Te damos el costo total y el costo por unidad, que es el
                   número que se lleva a la asamblea.</p>
                <div class="sn-mt-6">
                    <a class="sn-boton sn-boton--primario sn-boton--bloque"
                       href="/cotizar/consorcio">Cotizar mi consorcio</a>
                </div>
            </div>

        </div>

        <?php /* Los otros cinco ramos no son una tarjeta más: no se cotizan solos,
                  así que ponerlos a la par de los tres cotizadores confundiría. */ ?>
        <div class="sn-tarjeta sn-mt-6 sn-medido">
            <span class="sn-icono"><?= sn_icono('escudo') ?></span>
            <h2 style="font-size:var(--sn-txt-xl)">Los otros cinco ramos</h2>
            <p>Vida, accidentes personales, ART, comercio y caución se cotizan con datos
               que conviene conversar: dependen de la nómina, de la actividad o del
               contrato que hay que garantizar. Es más rápido que llenar veinte campos.</p>
            <div class="sn-mt-6">
                <a class="sn-boton sn-boton--secundario" href="/contacto">Pedir una propuesta</a>
            </div>
        </div>

        <div class="sn-aviso sn-aviso--legal sn-mt-8 sn-medido">
            <strong>Sobre las estimaciones.</strong>
            El cotizador calcula un valor orientativo con una tabla de ejemplo y no
            constituye una oferta: conforme el art. 4 de la Ley 17.418, el contrato de
            seguro se perfecciona con la propuesta aceptada por la aseguradora, y una
            propuesta no obliga a ninguna de las partes hasta ese momento. El precio en
            firme siempre lo emite la aseguradora sobre tus datos reales.
        </div>
    </div>
</section>
