<section class="sn-seccion">
    <div class="sn-contenedor sn-medido">
        <h1>Quiénes somos</h1>
        <p style="font-size:var(--sn-txt-lg)">
            Seguranet nació para que contratar un seguro en Argentina deje de ser
            un trámite a ciegas.
        </p>

        <h2>Qué hacemos</h2>
        <p>
            Te ayudamos a entender qué cubre cada póliza, qué deja afuera y qué
            conviene según tu situación. Trabajamos ocho ramos, de automotor a caución,
            para personas, comercios y consorcios.
        </p>

        <h2>Cómo cobramos</h2>
        <p>
            El asesoramiento no te cuesta nada: en seguros, el productor cobra una
            comisión que paga la aseguradora sobre la prima. Vos pagás la misma prima
            con o sin asesor. La diferencia es tener a quién llamar cuando pasa algo.
        </p>

        <h2>En qué situación estamos</h2>
        <?php if ($config['registro']['matriculado']): ?>
            <p>
                Operamos como productor asesor de seguros inscripto en el registro de la
                Superintendencia de Seguros de la Nación, bajo la matrícula
                N.º <?= e($config['registro']['matricula_ssn']) ?>.
            </p>
        <?php else: ?>
            <div class="sn-aviso sn-aviso--legal">
                <p>
                    <strong>Somos honestos con esto:</strong> Seguranet está en formación y
                    todavía no cuenta con matrícula propia ante la Superintendencia de
                    Seguros de la Nación.
                </p>
                <p>
                    En Argentina, intermediar en la contratación de seguros requiere estar
                    inscripto en el registro de productores asesores (Ley 22.400). Hasta
                    tenerla, este sitio informa, orienta y recibe consultas —que derivamos a
                    productores matriculados—, pero no comercializa pólizas ni cobra primas.
                </p>
            </div>
        <?php endif; ?>

        <h2>Dónde reclamar</h2>
        <p>
            El organismo que controla la actividad aseguradora es la Superintendencia de
            Seguros de la Nación. Atiende al 0800-666-8400 y en
            <a href="https://www.argentina.gob.ar/ssn" target="_blank" rel="noopener">argentina.gob.ar/ssn</a>.
        </p>
    </div>
</section>
