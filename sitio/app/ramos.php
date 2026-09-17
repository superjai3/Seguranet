<?php
/**
 * Los ramos que Seguranet asesora.
 *
 * Están acá y no repartidos por las plantillas porque cada ramo alimenta tres
 * lugares a la vez: el menú, su propia página y el desplegable del formulario
 * de contacto. Con una sola fuente, agregar un ramo es agregar una entrada.
 *
 * 'cotizable' marca los que tienen cotizador propio. Hoy sólo automotor; el
 * resto se asesora por consulta, que es como se venden de verdad.
 */

declare(strict_types=1);

return [
    'automotor' => [
        'nombre'    => 'Automotor',
        'titulo'    => 'Seguro de auto',
        'resumen'   => 'Desde responsabilidad civil obligatoria hasta todo riesgo con franquicia.',
        'cotizable' => true,
        'icono'     => 'auto',
        'detalle'   => [
            'Responsabilidad civil hacia terceros, que es lo que exige la ley para circular.',
            'Robo e incendio, total y parcial.',
            'Daños por accidente, granizo e inundación según la cobertura.',
            'Asistencia al vehículo y al conductor las 24 horas.',
        ],
    ],
    'hogar' => [
        'nombre'    => 'Hogar',
        'titulo'    => 'Combinado familiar',
        'resumen'   => 'Incendio, robo, daños por agua y responsabilidad civil de la vivienda.',
        'cotizable' => false,
        'icono'     => 'hogar',
        'detalle'   => [
            'Incendio del edificio y del contenido.',
            'Robo y daños por intento de robo.',
            'Daños por agua, incluidos los que le causes a un vecino.',
            'Responsabilidad civil hacia terceros y cristales.',
        ],
    ],
    'consorcio' => [
        'nombre'    => 'Consorcio',
        'titulo'    => 'Seguro integral de consorcio',
        'resumen'   => 'La cobertura que la ley exige a todo edificio en propiedad horizontal.',
        'cotizable' => false,
        'icono'     => 'edificio',
        'detalle'   => [
            'Incendio del edificio, obligatorio por el Código Civil y Comercial.',
            'Responsabilidad civil del consorcio hacia terceros y hacia los propietarios.',
            'Ascensores, cristales, tanques y daños por agua en espacios comunes.',
            'Accidentes personales del personal y visitantes en áreas comunes.',
        ],
    ],
    'vida' => [
        'nombre'    => 'Vida',
        'titulo'    => 'Vida individual y colectivo',
        'resumen'   => 'Protección económica para tu familia o para el personal de tu empresa.',
        'cotizable' => false,
        'icono'     => 'vida',
        'detalle'   => [
            'Vida individual, con capital asegurado a tu medida.',
            'Vida colectivo obligatorio, el que exige el convenio para el personal en relación de dependencia.',
            'Vida colectivo optativo, como beneficio adicional.',
            'Cobertura por invalidez total y permanente.',
        ],
    ],
    'accidentes-personales' => [
        'nombre'    => 'Accidentes personales',
        'titulo'    => 'Accidentes personales',
        'resumen'   => 'Para actividades, eventos y personas que no encuadran en ART.',
        'cotizable' => false,
        'icono'     => 'salud',
        'detalle'   => [
            'Muerte accidental e invalidez permanente.',
            'Asistencia médica y farmacéutica por accidente.',
            'Coberturas para monotributistas y trabajadores autónomos.',
            'Eventos, actividades deportivas y voluntariado.',
        ],
    ],
    'art' => [
        'nombre'    => 'ART',
        'titulo'    => 'Riesgos del trabajo',
        'resumen'   => 'Obligatoria para todo empleador con personal en relación de dependencia.',
        'cotizable' => false,
        'icono'     => 'trabajo',
        'detalle'   => [
            'Prestaciones médicas y dinerarias según la Ley 24.557 y sus modificatorias.',
            'Prevención y capacitación en higiene y seguridad.',
            'Gestión de altas, bajas y nómina ante la aseguradora.',
            'Acompañamiento en denuncias de siniestros laborales.',
        ],
    ],
    'comercio' => [
        'nombre'    => 'Comercio',
        'titulo'    => 'Integral de comercio',
        'resumen'   => 'El local, la mercadería y la responsabilidad frente a tus clientes.',
        'cotizable' => false,
        'icono'     => 'comercio',
        'detalle'   => [
            'Incendio del local y de las existencias.',
            'Robo de mercadería y de valores en caja.',
            'Responsabilidad civil hacia clientes y linderos.',
            'Cristales, carteles y equipos electrónicos.',
        ],
    ],
    'caucion' => [
        'nombre'    => 'Caución',
        'titulo'    => 'Seguro de caución',
        'resumen'   => 'La garantía que te piden para contratar con el Estado o alquilar.',
        'cotizable' => false,
        'icono'     => 'garantia',
        'detalle'   => [
            'Garantía de mantenimiento de oferta y de ejecución de contrato.',
            'Caución de alquiler, en reemplazo del garante propietario.',
            'Garantías aduaneras y ambientales.',
            'Anticipos y fondos de reparo en obra.',
        ],
    ],
];
