<?php
$v = $valores ?? [];
$e = $errores ?? [];
?>
<section class="sn-seccion">
    <div class="sn-contenedor" style="max-width:640px">
        <nav aria-label="Migas de pan" style="font-size:var(--sn-txt-sm);margin-bottom:var(--sn-esp-4)">
            <a href="/cuenta">Mi cuenta</a> › Registrar una póliza
        </nav>

        <h1>Registrar una póliza</h1>
        <p>
            Aunque la hayas contratado en otro lado. Con la fecha de vencimiento nos alcanza
            para avisarte a tiempo: <strong>60, 30, 15 y 7 días antes</strong>.
        </p>

        <?php if ($e !== []): ?>
            <div class="sn-aviso sn-aviso--error" role="alert">Revisá los campos marcados.</div>
        <?php endif; ?>

        <form method="post" action="/cuenta/polizas/nueva" class="sn-tarjeta" novalidate>
            <input type="hidden" name="token" value="<?= e(sn_token()) ?>">

            <div class="sn-campo">
                <label for="ramo">¿Qué seguro es?</label>
                <select id="ramo" name="ramo" required<?= isset($e['ramo']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Elegí uno</option>
                    <?php foreach ($ramos as $clave => $ramo): ?>
                        <option value="<?= e($clave) ?>"<?= ($v['ramo'] ?? '') === $clave ? ' selected' : '' ?>>
                            <?= e($ramo['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($e['ramo'])): ?><p class="sn-campo__error"><?= e($e['ramo']) ?></p><?php endif; ?>
            </div>

            <div class="sn-campo">
                <label for="vigencia_hasta">¿Cuándo vence?</label>
                <input type="date" id="vigencia_hasta" name="vigencia_hasta" required
                       value="<?= e($v['vigencia_hasta'] ?? '') ?>"<?= isset($e['vigencia_hasta']) ? ' aria-invalid="true"' : '' ?>>
                <p class="sn-campo__ayuda">Es el único dato imprescindible: sin él no podemos avisarte.</p>
                <?php if (isset($e['vigencia_hasta'])): ?><p class="sn-campo__error"><?= e($e['vigencia_hasta']) ?></p><?php endif; ?>
            </div>

            <div class="sn-campo">
                <label for="aseguradora">Aseguradora <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                <input type="text" id="aseguradora" name="aseguradora" list="lista-aseguradoras"
                       value="<?= e($v['aseguradora'] ?? '') ?>" placeholder="Empezá a escribir">
                <datalist id="lista-aseguradoras">
                    <?php foreach (SN_ASEGURADORAS as $nombre): ?>
                        <option value="<?= e($nombre) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php if (isset($e['aseguradora'])): ?><p class="sn-campo__error"><?= e($e['aseguradora']) ?></p><?php endif; ?>
            </div>

            <div class="sn-campo">
                <label for="detalle">¿Qué asegura? <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                <input type="text" id="detalle" name="detalle" value="<?= e($v['detalle'] ?? '') ?>"
                       placeholder="Fiat Cronos 2021, o Departamento de Av. Rivadavia">
            </div>

            <div class="sn-rejilla sn-rejilla--2" style="gap:var(--sn-esp-4)">
                <div class="sn-campo">
                    <label for="numero">N.º de póliza <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                    <input type="text" id="numero" name="numero" value="<?= e($v['numero'] ?? '') ?>">
                </div>
                <div class="sn-campo">
                    <label for="prima_mensual">Prima mensual <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                    <input type="number" id="prima_mensual" name="prima_mensual" min="0" step="100"
                           value="<?= e((string) ($v['prima_mensual'] ?? '')) ?>">
                    <p class="sn-campo__ayuda">Sirve para comparar cuando toque renovar.</p>
                </div>
            </div>

            <div class="sn-campo">
                <label for="vigencia_desde">Vigencia desde <span style="font-weight:400;color:var(--sn-gris-500)">(opcional)</span></label>
                <input type="date" id="vigencia_desde" name="vigencia_desde" value="<?= e($v['vigencia_desde'] ?? '') ?>"
                       <?= isset($e['vigencia_desde']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($e['vigencia_desde'])): ?><p class="sn-campo__error"><?= e($e['vigencia_desde']) ?></p><?php endif; ?>
            </div>

            <button class="sn-boton sn-boton--primario sn-boton--bloque" type="submit">Guardar la póliza</button>
        </form>

        <div class="sn-aviso sn-aviso--legal sn-mt-6">
            Estos datos son tuyos y sólo se usan para avisarte del vencimiento y para poder
            asesorarte. Podés borrarlos cuando quieras: mirá la
            <a href="/legales/privacidad">política de privacidad</a>.
        </div>
    </div>
</section>
