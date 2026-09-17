<?php
$u = sn_usuario();
$polizas = $polizas ?? [];
$vigentes = array_filter($polizas, static fn($p) => $p['estado'] === 'vigente');
$porVencer = array_filter($vigentes, static fn($p) => sn_dias_para_vencer($p['vigencia_hasta']) <= 60
                                                  && sn_dias_para_vencer($p['vigencia_hasta']) >= 0);
?>
<section class="sn-seccion">
    <div class="sn-contenedor">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--sn-esp-4);flex-wrap:wrap">
            <div>
                <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
                <p class="sn-medido">
                    Acá seguís tus pólizas y te avisamos antes de que venzan, sean nuestras o de
                    otra compañía.
                </p>
            </div>
            <a class="sn-boton sn-boton--primario" href="/cuenta/polizas/nueva">Registrar una póliza</a>
        </div>

        <?php if (isset($_GET['guardada'])): ?>
            <div class="sn-aviso sn-aviso--ok sn-mt-6" role="status">
                Póliza registrada. Te vamos a avisar 60, 30, 15 y 7 días antes del vencimiento.
            </div>
        <?php endif; ?>

        <?php if ($porVencer !== []): ?>
            <div class="sn-aviso sn-aviso--dato sn-mt-6">
                <strong>Tenés <?= count($porVencer) ?>
                <?= count($porVencer) === 1 ? 'póliza que vence' : 'pólizas que vencen' ?> dentro de los próximos 60 días.</strong>
                Es el mejor momento para comparar sin apuro.
                <a href="/cotizar/auto">Cotizar ahora</a>.
            </div>
        <?php endif; ?>

        <?php if ($polizas === []): ?>
            <div class="sn-tarjeta sn-mt-8" style="text-align:center;padding:var(--sn-esp-12)">
                <span class="sn-icono" style="margin-inline:auto"><?= sn_icono('escudo') ?></span>
                <h2 style="font-size:var(--sn-txt-xl)">Todavía no registraste ninguna póliza</h2>
                <p class="sn-medido--centro">
                    Registrá la que ya tenés —aunque la hayas contratado en otro lado— y te avisamos
                    antes de que venza. No necesitás subir ningún papel: alcanza con el ramo y la
                    fecha de vencimiento.
                </p>
                <div class="sn-mt-6">
                    <a class="sn-boton sn-boton--primario" href="/cuenta/polizas/nueva">Registrar mi primera póliza</a>
                </div>
            </div>
        <?php else: ?>
            <div class="sn-mt-8">
                <?php foreach ($polizas as $p):
                    $estado = sn_estado_poliza($p);
                    $ramoNombre = $ramos[$p['ramo']]['titulo'] ?? $p['ramo'];
                ?>
                    <div class="sn-tarjeta" style="margin-bottom:var(--sn-esp-4)">
                        <div style="display:flex;justify-content:space-between;gap:var(--sn-esp-4);flex-wrap:wrap">
                            <div style="flex:1 1 60%">
                                <h2 style="font-size:var(--sn-txt-lg);margin-bottom:var(--sn-esp-1)">
                                    <?= e($ramoNombre) ?>
                                    <?php if ($p['aseguradora'] !== ''): ?>
                                        <span style="font-weight:400;color:var(--sn-gris-500)">· <?= e($p['aseguradora']) ?></span>
                                    <?php endif; ?>
                                </h2>
                                <?php if ($p['detalle'] !== ''): ?>
                                    <p style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500)"><?= e($p['detalle']) ?></p>
                                <?php endif; ?>
                                <p style="font-size:var(--sn-txt-sm);margin-top:var(--sn-esp-2)">
                                    Vence el <strong><?= e(date('d/m/Y', strtotime($p['vigencia_hasta']))) ?></strong>
                                    <?php if ($p['numero'] !== ''): ?>
                                        · póliza N.º <?= e($p['numero']) ?>
                                    <?php endif; ?>
                                    <?php if ($p['prima_mensual'] !== null): ?>
                                        · <?= e(sn_pesos((int) $p['prima_mensual'])) ?> por mes
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div style="flex:0 0 auto;text-align:right">
                                <span class="sn-aviso sn-aviso--<?= e($estado['tono']) ?>"
                                      style="display:inline-block;margin:0;padding:var(--sn-esp-2) var(--sn-esp-3);font-size:var(--sn-txt-sm)">
                                    <?= e($estado['texto']) ?>
                                </span>
                                <?php if ($p['estado'] === 'vigente'): ?>
                                    <div style="margin-top:var(--sn-esp-3);display:flex;gap:var(--sn-esp-2);justify-content:flex-end;flex-wrap:wrap">
                                        <a class="sn-boton sn-boton--linea" style="padding:var(--sn-esp-2) var(--sn-esp-3);min-height:auto"
                                           href="/cotizar/auto">Comparar</a>
                                        <form method="post" action="/cuenta/polizas/estado" style="display:inline">
                                            <input type="hidden" name="token" value="<?= e(sn_token()) ?>">
                                            <input type="hidden" name="poliza" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="estado" value="dada_de_baja">
                                            <button class="sn-boton sn-boton--linea" type="submit"
                                                    style="padding:var(--sn-esp-2) var(--sn-esp-3);min-height:auto">Dar de baja</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="sn-rejilla sn-rejilla--3 sn-mt-8">
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('auto') ?></span>
                <h2 style="font-size:var(--sn-txt-lg)">Cotizar</h2>
                <p>Compará los cuatro planes antes de renovar.</p>
                <div class="sn-mt-6"><a class="sn-boton sn-boton--linea sn-boton--bloque" href="/cotizar/auto">Ir al cotizador</a></div>
            </div>
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('salud') ?></span>
                <h2 style="font-size:var(--sn-txt-lg)">Tuve un siniestro</h2>
                <p>Qué hacer y en cuánto tiempo. Hay plazos que corren.</p>
                <div class="sn-mt-6"><a class="sn-boton sn-boton--linea sn-boton--bloque" href="/siniestros">Ver los pasos</a></div>
            </div>
            <div class="sn-tarjeta">
                <span class="sn-icono"><?= sn_icono('usuario') ?></span>
                <h2 style="font-size:var(--sn-txt-lg)">Mis datos</h2>
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
