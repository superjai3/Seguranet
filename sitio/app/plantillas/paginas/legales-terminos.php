<section class="sn-seccion">
    <div class="sn-contenedor sn-medido">
        <h1>Términos y condiciones</h1>
        <p><small>Última actualización: <?= date('d/m/Y') ?></small></p>

        <h2>1. Qué es este sitio</h2>
        <?php if ($config['registro']['matriculado']): ?>
            <p>
                Seguranet es el sitio de <?= e($config['registro']['titular']) ?>, productor asesor de
                seguros inscripto ante la Superintendencia de Seguros de la Nación bajo la matrícula
                N.º <?= e($config['registro']['matricula_ssn']) ?>.
            </p>
        <?php else: ?>
            <p>
                Seguranet es un sitio de información y orientación en materia de seguros.
                <strong>No intermedia en la contratación</strong>: en la República Argentina esa actividad
                está reservada a los productores asesores inscriptos en el registro de la Superintendencia
                de Seguros de la Nación (Ley 22.400, arts. 2 y 4). Las consultas que recibimos se derivan
                a productores matriculados.
            </p>
        <?php endif; ?>

        <h2>2. La información no es una oferta</h2>
        <p>
            Las coberturas descriptas son un resumen con fines informativos. El alcance, las exclusiones
            y las franquicias los fijan las condiciones generales y particulares de la póliza de cada
            aseguradora. Las estimaciones del cotizador son orientativas y no obligan a ninguna
            aseguradora: conforme el art. 4 de la Ley 17.418, el contrato de seguro se celebra con la
            propuesta aceptada por el asegurador.
        </p>

        <h2>3. Uso del sitio</h2>
        <p>
            Podés usar el sitio para informarte y consultarnos. No está permitido usarlo para enviar
            contenido ilícito, suplantar identidades, extraer datos de forma automatizada ni interferir
            con su funcionamiento.
        </p>

        <h2>4. Datos que nos das</h2>
        <p>
            Te comprometés a que los datos que cargás sean veraces y propios. En seguros esto importa
            más que en otros rubros: la reticencia o la falsa declaración pueden anular el contrato
            (art. 5, Ley 17.418).
        </p>

        <h2>5. Responsabilidad</h2>
        <p>
            Procuramos que la información esté actualizada, pero la normativa y las condiciones de las
            aseguradoras cambian. Ante cualquier diferencia, prevalece el texto de la póliza y la norma
            aplicable. No respondemos por la disponibilidad de servicios de terceros enlazados desde
            este sitio.
        </p>

        <h2>6. Propiedad intelectual</h2>
        <p>
            Los textos, el diseño y la marca Seguranet son de su titular. Las marcas de terceros que
            pudieran mencionarse pertenecen a sus respectivos titulares y se usan sólo a título
            identificatorio.
        </p>

        <h2>7. Ley aplicable y jurisdicción</h2>
        <p>
            Estos términos se rigen por las leyes de la República Argentina. Para toda controversia con
            un consumidor resulta competente el tribunal de su domicilio, conforme el art. 36 de la
            Ley 24.240.
        </p>
    </div>
</section>
