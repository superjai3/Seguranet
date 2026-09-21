<?php
/**
 * Resultado de una cotización: los planes, el detalle del cálculo y el aviso
 * legal. Lo comparten el cotizador de automotor y el de hogar.
 *
 * Está aparte porque son ochenta líneas idénticas en los dos. Duplicarlas
 * garantizaba que el día que haya que tocar el aviso del art. 4 —que es
 * justamente el que tiene que estar bien— se corrija en uno solo y nadie se
 * entere del otro.
 *
 * Espera en el ámbito:
 *   $cotizacion    lo que devuelve sn_cotizar() o sn_cotizar_hogar()
 *   $resumen       la línea que describe lo cotizado, en palabras
 *   $ramoCotizado  la clave del ramo, para el enlace a contacto
 *
 * Dos cosas son opcionales y sólo las usa consorcio:
 *   $cotizacion['avisos']             advertencias sobre la cobertura elegida
 *   $cotizacion['planes'][x]['por_unidad']  el costo prorrateado por unidad
 */
?>
        <h2 style="font-size:var(--sn-txt-xl)">Tu estimación</h2>
        <p style="color:var(--sn-gris-500);font-size:var(--sn-txt-sm)">
            <?= e($resumen) ?>
        </p>

        <?php foreach (($cotizacion['avisos'] ?? []) as $aviso): ?>
            <div class="sn-aviso sn-aviso--dato" style="margin-bottom:var(--sn-esp-3)">
                <?= e($aviso) ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($cotizacion['planes'] as $plan): ?>
            <div class="sn-tarjeta plan-fila<?= $plan['destacado'] ? ' plan-fila--destacado' : '' ?>"
                 style="margin-bottom:var(--sn-esp-3)">
                <div style="display:flex;justify-content:space-between;gap:var(--sn-esp-4);flex-wrap:wrap">
                    <div style="flex:1 1 55%">
                        <h3 style="font-size:var(--sn-txt-lg);margin-bottom:var(--sn-esp-1)">
                            <?= e($plan['nombre']) ?>
                            <?php if ($plan['destacado']): ?>
                                <span style="display:inline-block;background:var(--sn-verde-100);color:var(--sn-verde-600);font-size:var(--sn-txt-xs);padding:2px 8px;border-radius:99px;vertical-align:middle">más elegido</span>
                            <?php endif; ?>
                        </h3>
                        <p style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500)"><?= e($plan['resumen']) ?></p>
                        <ul style="font-size:var(--sn-txt-sm);margin:var(--sn-esp-2) 0 0;padding-left:1.1rem">
                            <?php foreach ($plan['incluye'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <div style="text-align:right;flex:0 0 auto">
                        <div style="font-size:1.6rem;font-weight:700;color:var(--sn-verde-600);line-height:1.1">
                            <?= e(sn_pesos($plan['mensual'])) ?>
                        </div>
                        <div style="font-size:var(--sn-txt-sm);color:var(--sn-gris-500)">por mes</div>
                        <div style="font-size:var(--sn-txt-xs);color:var(--sn-gris-500)"><?= e(sn_pesos($plan['anual'])) ?> al año</div>
                        <?php if (isset($plan['por_unidad'])): ?>
                            <div style="font-size:var(--sn-txt-sm);color:var(--sn-azul-900);margin-top:var(--sn-esp-2);font-weight:600">
                                <?= e(sn_pesos($plan['por_unidad'])) ?>
                            </div>
                            <div style="font-size:var(--sn-txt-xs);color:var(--sn-gris-500)">por unidad, por mes</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <details class="sn-tarjeta" style="margin-bottom:var(--sn-esp-4)">
            <summary style="cursor:pointer;font-weight:600;color:var(--sn-azul-900)">Cómo se calculó</summary>
            <table style="width:100%;border-collapse:collapse;margin-top:var(--sn-esp-4);font-size:var(--sn-txt-sm)">
                <tbody>
                    <?php foreach ($cotizacion['factores'] as [$nombre, $detalle, $factor]): ?>
                        <tr style="border-bottom:1px solid var(--sn-gris-100)">
                            <td style="padding:var(--sn-esp-2) 0"><strong><?= e($nombre) ?></strong></td>
                            <td style="padding:var(--sn-esp-2) 0;color:var(--sn-gris-500)"><?= e($detalle) ?></td>
                            <td style="padding:var(--sn-esp-2) 0;text-align:right">&times;<?= number_format($factor, 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2" style="padding:var(--sn-esp-3) 0"><strong>Recargo total</strong></td>
                        <td style="padding:var(--sn-esp-3) 0;text-align:right"><strong>&times;<?= number_format($cotizacion['recargo'], 2, ',', '.') ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </details>

        <a class="sn-boton sn-boton--secundario sn-boton--bloque"
           href="/contacto?ramo=<?= e($ramoCotizado) ?>">Quiero el precio en firme</a>
