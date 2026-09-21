# Seguranet — sitio web

Sitio institucional y de captación, en **PHP 8.1+ y MySQL**, pensado para correr
en el plan **IONOS Hosting Plus** que ya está contratado.

No usa framework ni dependencias de terceros: no hay `composer install` ni
`npm run build`. Se sube y anda.

## Por qué PHP y no el proyecto .NET

El sitio original es ASP.NET MVC 5 sobre .NET Framework, que necesita Windows e
IIS. IONOS Hosting Plus es hosting compartido con PHP y MySQL: **no ejecuta
.NET**. Antes que pagar un servidor aparte para sostener un sitio institucional,
se reescribió sobre lo que el plan ya ofrece. El código .NET queda en el
repositorio como historia del proyecto.

## Estructura

```
sitio/
├── publico/              ← raíz del dominio (document root)
│   ├── index.php         ← controlador frontal: todo entra por acá
│   ├── .htaccess         ← reescritura, HTTPS, cabeceras de seguridad, caché
│   └── recursos/         ← css, js, imágenes
├── app/                  ← fuera de la raíz pública
│   ├── config.php        ← valores por defecto (sin secretos)
│   ├── config.local.php  ← credenciales reales, NO versionado
│   ├── ramos.php         ← los ocho ramos, en un solo lugar
│   ├── ayudas.php
│   ├── consultas.php     ← alta de consultas del formulario
│   └── plantillas/
└── bd/esquema.sql        ← tablas
```

## Puesta en marcha en IONOS

1. **Base de datos.** En el panel, *Bases de datos → Crear*. Anotá host, nombre,
   usuario y contraseña. Importá `bd/esquema.sql` desde phpMyAdmin.

2. **Archivos.** Subí por SFTP la carpeta `sitio/` completa fuera de la raíz
   web, o subí `publico/` a la raíz y `app/` y `bd/` un nivel más arriba. Lo que
   **no** puede pasar es que `app/` quede accesible por HTTP: ahí están las
   credenciales.

3. **Dominio.** En *Dominios*, apuntá `seguranet.es` a la carpeta `publico/`.
   IONOS permite asignar un dominio a un subdirectorio.

4. **Configuración.** Creá `app/config.local.php` con los valores reales:

   ```php
   <?php
   return [
       'sitio'  => ['url' => 'https://seguranet.es'],
       'bd'     => [
           'host' => 'db5xxxxxxx.hosting-data.io',
           'nombre' => 'dbsxxxxxxx',
           'usuario' => 'dboxxxxxxx',
           'clave' => '...',
       ],
       'correo' => [
           'host'    => 'smtp.ionos.es',
           'puerto'  => 587,
           'usuario' => 'no-responder@seguranet.es',
           'clave'   => '...',
           'desde'   => 'no-responder@seguranet.es',
       ],
   ];
   ```

   El buzón `no-responder@` se crea antes en *Correo → Crear dirección*: el
   usuario de SMTP es la dirección completa y la clave es la del buzón, no la
   de la cuenta de IONOS.

   Sin `host` el sitio se cae a `mail()`, que sirve para probar en local pero
   no para producción: el mensaje sale con el dominio del servidor compartido,
   así que no lo respaldan ni SPF ni DKIM y termina en spam.

5. **PHP 8.1 o superior.** En *PHP → Gestionar versiones*, asignale 8.1+ al
   dominio.

6. **SSL.** IONOS emite el certificado gratuito. El `.htaccess` ya fuerza HTTPS
   y quita el `www`.

## Probarlo local

```
php -S localhost:8000 -t sitio/publico sitio/publico/index.php
```

Sin base de datos configurada el sitio funciona igual: las consultas se envían
por correo y se registra el fallo de guardado en el log. Que falte la base no
tira abajo el sitio.

## Avisos de vencimiento (tarea programada)

El diferencial del proyecto es avisarle al cliente antes de que venza su
póliza, aunque sea de otra compañía. Eso lo hace un script que se corre una vez
por día desde *IONOS → Tareas cron*:

```
/usr/bin/php /ruta/al/sitio/tareas/avisar-vencimientos.php
```

Conviene programarlo a la mañana temprano, hora argentina. Manda un aviso 60,
30, 15 y 7 días antes del vencimiento, y **uno solo por póliza por corrida**:
si el cron estuvo caído una semana, al volver no vomita los avisos atrasados.
Cada aviso queda registrado con una clave única, así que ejecutarlo dos veces
el mismo día no duplica correos.

## Qué hay

- Sitio institucional con los ocho ramos y las cinco páginas legales.
- Formulario de consulta que guarda en base, avisa por correo y manda acuse.
- Área de cuenta: registro, confirmación por correo, ingreso, salida y
  restablecimiento de contraseña.
- Cotizador de automotor con catálogo argentino y tarifación por zona.
- Cotizador de hogar (combinado familiar) con tres planes. Distingue propietario
  de inquilino: al inquilino no se le cobra el edificio, que no es suyo.
- Cotizador de consorcio con tres planes, el costo por unidad y por mes —el
  número que se lleva a la asamblea— y avisos cuando el límite de
  responsabilidad civil elegido queda corto para el edificio.
- Seguimiento de pólizas con avisos de vencimiento a 60, 30, 15 y 7 días.
- Correo saliente por SMTP autenticado con STARTTLS. Si el servidor no ofrece
  cifrado, el envío se cancela antes de mandar la clave.

- Aviso de cookies con consentimiento previo: nada de analítica se carga hasta
  que la persona acepta, y rechazar cuesta lo mismo que aceptar. Se activa
  poniendo `SN_MEDICION_ID` en la configuración; vacío, no se mide ni se
  pregunta nada.

Todo con pruebas: 251 aserciones en `sitio/pruebas/`. Las corre también el
workflow de GitHub Actions en cada push.

## Qué falta

- Panel interno para ver y responder las consultas sin entrar a phpMyAdmin.
- Tarifas reales: la tabla del cotizador es de ejemplo hasta que haya acuerdo
  con aseguradoras.
- Registros SPF, DKIM y DMARC del dominio en Hostalia, que es donde está el
  DNS de `seguranet.es`. El envío ya sale por SMTP autenticado de IONOS, pero
  sin esos tres registros el buzón del destinatario no puede comprobar que el
  correo sea nuestro.
