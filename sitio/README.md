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
       'correo' => ['desde' => 'no-responder@seguranet.es'],
   ];
   ```

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

## Qué falta

- Área de cuenta (registro, confirmación por correo, ingreso). Las tablas ya
  están en `bd/esquema.sql`.
- Cotizador de automotor integrado al sitio, con catálogo argentino.
- Medición de audiencia, previo aviso de cookies.
