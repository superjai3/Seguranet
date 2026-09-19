<section class="sn-seccion">
    <div class="sn-contenedor sn-medido">
        <h1>Política de privacidad</h1>
        <p><small>Última actualización: <?= date('d/m/Y') ?></small></p>

        <h2>Quién trata tus datos</h2>
        <p>
            Los datos que dejás en este sitio los trata <strong>Seguranet</strong>
            <?php if ($config['registro']['cuit'] !== ''): ?>(CUIT <?= e($config['registro']['cuit']) ?>)<?php endif; ?>,
            responsable de la base de datos. Podés contactarnos en
            <a href="mailto:<?= e($config['sitio']['correo']) ?>"><?= e($config['sitio']['correo']) ?></a>.
        </p>

        <h2>Qué datos recogemos y para qué</h2>
        <ul>
            <li><strong>Formulario de contacto:</strong> nombre, correo, teléfono (si lo dejás),
                el ramo que te interesa y tu mensaje. Los usamos únicamente para responder tu consulta
                y, si lo pedís, para acercarte una propuesta de seguro.</li>
            <li><strong>Datos técnicos:</strong> dirección IP y navegador, al enviar el formulario.
                Nos sirven para acreditar tu consentimiento y para frenar el envío automatizado de spam.</li>
            <li><strong>Cotizador:</strong> los datos del vehículo que cargás para estimar un precio
                se procesan en tu navegador y no se guardan en nuestros servidores, salvo que decidas
                enviarnos la cotización.</li>
        </ul>
        <p>
            No recogemos datos sensibles en los términos del art. 2 de la Ley 25.326, y no te pedimos
            DNI, domicilio ni datos de salud para una consulta: eso recién hace falta si avanzás en la
            contratación de una póliza, y en ese caso te lo pediremos explicando para qué.
        </p>

        <h2>Con qué base legal</h2>
        <p>
            Con tu <strong>consentimiento libre, expreso e informado</strong>, que prestás al tildar la
            casilla del formulario (arts. 5 y 6 de la Ley 25.326). Podés retirarlo cuando quieras.
        </p>

        <h2>A quién se los damos</h2>
        <p>
            A nadie, salvo una excepción: si pedís una propuesta de seguro, tus datos se comparten con
            la aseguradora o el productor matriculado necesario para cotizarla, y sólo con ese fin.
            No vendemos ni cedemos datos con fines publicitarios.
        </p>

        <h2>Cuánto los conservamos</h2>
        <p>
            Las consultas se conservan mientras dure la gestión y hasta 24 meses después, para poder
            retomar el contacto y acreditar lo actuado. Vencido ese plazo, se eliminan.
        </p>

        <h2>Tus derechos</h2>
        <p>
            Podés pedir el <strong>acceso, rectificación, actualización y supresión</strong> de tus datos
            escribiendo a <a href="mailto:<?= e($config['sitio']['correo']) ?>"><?= e($config['sitio']['correo']) ?></a>.
            Respondemos dentro de los plazos de los arts. 14 y 16 de la Ley 25.326: diez días corridos para
            el acceso y cinco días hábiles para la rectificación o supresión.
        </p>

        <div class="sn-aviso sn-aviso--legal">
            <p>
                El titular de los datos personales tiene la facultad de ejercer el derecho de acceso a los
                mismos en forma gratuita a intervalos no inferiores a seis meses, salvo que se acredite un
                interés legítimo al efecto, conforme lo establecido en el artículo 14, inciso 3 de la
                Ley N.º 25.326.
            </p>
            <p style="margin-bottom:0">
                La <strong>Agencia de Acceso a la Información Pública</strong>, órgano de control de la
                Ley N.º 25.326, tiene la atribución de atender las denuncias y reclamos que interpongan
                quienes resulten afectados en sus derechos por incumplimiento de las normas vigentes en
                materia de protección de datos personales.
            </p>
        </div>

        <h2>Seguridad</h2>
        <p>
            El sitio se sirve íntegramente por HTTPS, las contraseñas se guardan con funciones de hash
            de un solo sentido —nunca en texto plano— y el acceso a la base está restringido.
        </p>
    </div>
</section>
