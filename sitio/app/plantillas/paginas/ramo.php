<section class="sn-seccion">
    <div class="sn-contenedor">
        <nav aria-label="Migas de pan" style="font-size:var(--sn-txt-sm);margin-bottom:var(--sn-esp-6)">
            <a href="/">Inicio</a> › <a href="/seguros">Seguros</a> › <?= e($ramo['nombre']) ?>
        </nav>

        <div class="sn-medido">
            <span class="sn-icono"><?= sn_icono($ramo['icono']) ?></span>
            <h1><?= e($ramo['titulo']) ?></h1>
            <p style="font-size:var(--sn-txt-lg)"><?= e($ramo['resumen']) ?></p>
        </div>

        <div class="sn-rejilla sn-rejilla--2 sn-mt-8">
            <div class="sn-tarjeta">
                <h2 style="font-size:var(--sn-txt-xl)">Qué cubre</h2>
                <ul>
                    <?php foreach ($ramo['detalle'] as $punto): ?>
                        <li><?= e($punto) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="sn-tarjeta__pie" style="color:var(--sn-gris-500);font-weight:400;font-size:var(--sn-txt-sm)">
                    El alcance exacto lo fijan las condiciones generales y particulares
                    de la póliza de cada aseguradora.
                </p>
            </div>

            <div class="sn-tarjeta">
                <h2 style="font-size:var(--sn-txt-xl)">Cómo seguimos</h2>
                <?php if ($ramo['cotizador'] !== ''): ?>
                    <p>Podés calcular una estimación en el momento, sin dejar datos personales,
                       y después pedirnos la propuesta en firme.</p>
                    <div class="sn-mt-6">
                        <a class="sn-boton sn-boton--primario sn-boton--bloque" href="<?= e($ramo['cotizador']) ?>">Cotizar ahora</a>
                    </div>
                <?php else: ?>
                    <p>Este ramo se arma a medida: el precio depende de datos que conviene
                       conversar. Contanos tu caso y te preparamos una propuesta.</p>
                <?php endif; ?>
                <div class="sn-mt-6">
                    <a class="sn-boton sn-boton--linea sn-boton--bloque"
                       href="/contacto?ramo=<?= e($clave) ?>">Pedir una propuesta</a>
                </div>
            </div>
        </div>
    </div>
</section>
