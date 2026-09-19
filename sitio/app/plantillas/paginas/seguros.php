<section class="sn-seccion">
    <div class="sn-contenedor">
        <div class="sn-medido sn-mb-8">
            <h1>Nuestros seguros</h1>
            <p>
                Trabajamos los ramos que más se necesitan en Argentina, tanto para
                personas como para empresas y consorcios. Entrá en el que te interese
                para ver qué cubre y qué conviene mirar antes de contratar.
            </p>
        </div>
        <div class="sn-rejilla sn-rejilla--3">
            <?php foreach ($ramos as $clave => $ramo): ?>
                <a class="sn-tarjeta sn-tarjeta--enlace" href="/seguros/<?= e($clave) ?>">
                    <span class="sn-icono"><?= sn_icono($ramo['icono']) ?></span>
                    <h2 style="font-size:var(--sn-txt-xl)"><?= e($ramo['titulo']) ?></h2>
                    <p><?= e($ramo['resumen']) ?></p>
                    <span class="sn-tarjeta__pie">
                        <?= $ramo['cotizable'] ? 'Cotizar en línea &rarr;' : 'Pedir propuesta &rarr;' ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
