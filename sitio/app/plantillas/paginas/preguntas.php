<?php
$faq = [
    ['¿Qué es la franquicia y por qué me conviene o no?',
     'La franquicia es la parte del daño que pagás vos antes de que entre la aseguradora. Una franquicia más alta baja la prima, pero te deja expuesto a poner más plata el día del siniestro. Tiene sentido si el auto vale mucho y podés afrontar ese monto; no la tiene si un choque chico te descalabra las cuentas.'],
    ['¿Cuánto tiempo tengo para denunciar un siniestro?',
     'Tres días corridos desde que lo conociste, según el art. 46 de la Ley 17.418. Es un plazo corto y se cumple: denunciar tarde sin causa justificada puede hacerte perder la cobertura.'],
    ['¿Cuánto tarda la aseguradora en responder?',
     'Tiene 30 días para pronunciarse sobre el derecho del asegurado, contados desde que recibe la denuncia y la información complementaria que pida (art. 56, Ley 17.418). Si no dice nada en ese plazo, la ley entiende que aceptó.'],
    ['¿Es obligatorio tener seguro de auto en Argentina?',
     'Sí. El art. 68 de la Ley 24.449 de Tránsito exige que todo automotor tenga cubierta la responsabilidad civil hacia terceros. Sin eso no podés circular legalmente. El resto de las coberturas —robo, incendio, daños propios— son optativas.'],
    ['¿Qué es la carta de no siniestro y para qué sirve?',
     'Es la constancia que emite tu aseguradora diciendo cuántos años estuviste asegurado sin denunciar siniestros. Sirve para pedir descuento al cambiar de compañía: cuanto más limpio el historial, mejor la tarifa.'],
    ['¿Puedo cambiar de cobertura sin esperar a la renovación?',
     'Sí. Se puede pedir el cambio en cualquier momento y la aseguradora emite un endoso con la diferencia de prima a favor o en contra. Conviene revisarlo cuando cambia el valor del auto o tu forma de usarlo.'],
    ['¿Qué mira una aseguradora para fijar el precio?',
     'El vehículo y su valor, el año, la zona donde duerme —el código postal pesa, y mucho—, el uso (particular o comercial), la edad y el historial del conductor, y los adicionales como GNC o rastreo satelital.'],
    ['¿El asesoramiento tiene costo?',
     'No. El productor cobra una comisión que paga la aseguradora sobre la prima; vos pagás lo mismo con asesor o sin él. Lo que ganás es tener a quién llamar cuando pasa algo.'],
];
?>
<section class="sn-seccion">
    <div class="sn-contenedor sn-medido">
        <h1>Preguntas frecuentes</h1>
        <p style="font-size:var(--sn-txt-lg)">
            Las dudas que más nos llegan, respondidas sin vueltas.
        </p>

        <div class="sn-mt-8">
            <?php foreach ($faq as $i => [$pregunta, $respuesta]): ?>
                <details class="sn-tarjeta" style="margin-bottom:var(--sn-esp-3)"<?= $i === 0 ? ' open' : '' ?>>
                    <summary style="cursor:pointer;font-weight:600;color:var(--sn-azul-900);font-size:var(--sn-txt-lg)">
                        <?= e($pregunta) ?>
                    </summary>
                    <p style="margin-top:var(--sn-esp-4)"><?= e($respuesta) ?></p>
                </details>
            <?php endforeach; ?>
        </div>

        <div class="sn-aviso sn-aviso--dato sn-mt-8">
            ¿Tu duda no está acá? <a href="/contacto">Escribinos</a> y la respondemos.
        </div>
    </div>
</section>

<?php /* FAQPage: es lo que hace que estas respuestas puedan aparecer en el
         buscador y que un motor generativo las cite con la fuente. */ ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn($f) => [
        '@type' => 'Question',
        'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faq),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
