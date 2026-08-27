# Auditoría web · Seguranet

2026-08-27 · ASP.NET MVC 5 (.NET Framework 4.6.2) + SQL Server · modelo: **SaaS**
Rama `mejoras/auditoria-web-2026-08-27`

## Resumen

**21 de 60 puntos aplicables cumplidos al empezar. 17 arreglados en esta pasada.**
Quedan **6 críticos pendientes**, todos por decisión o por falta de entorno.

Seguranet es un CRM/cotizador de seguros hecho como proyecto final de cátedra
(ISTEA). Funcionaba, pero nunca había pasado por una capa de producción.

> ✅ **Compilado y ejecutado.** Se instaló Visual Studio Community 2022 con la
> carga de trabajo de ASP.NET y el targeting pack de .NET Framework 4.6.2.
> La solución **compila con 0 errores** (queda 1 advertencia, MSB3247 sobre
> redirecciones de ensamblado, anterior a esta pasada y originada en las
> dependencias de MailKit), y el sitio se levantó con IIS Express para
> comprobar página por página. Ejecutarlo encontró **dos errores que la
> revisión de sintaxis no podía ver** — están abajo, en «Encontrado al
> ejecutar».

## Arreglado en esta pasada

### `c48f39c` · El `.gitignore` no funcionaba
Se llamaba **`.gitignore.txt`**. Git no lo leía, y por eso el repositorio venía
versionando **590 MB de paquetes de NuGet**, 52 MB de `bin/` y el estado local de
Visual Studio, que cambia cada vez que se abre el proyecto y ensuciaba todos los
commits. Renombrado, completado para ASP.NET MVC 5, y sacado del índice lo que no
correspondía: **de 2478 archivos rastreados a 127**. Nada se borró del disco; los
paquetes se restauran con `nuget restore` desde `packages.config`.

Esto no achica el historial ya escrito. Limpiarlo (BFG, `git filter-repo`) es una
decisión aparte, y obliga a reescribir la rama remota.

### `67fd29c` · Las imágenes: de 109 MB a 1,2 MB
Las dieciséis imágenes estaban en JPG y sin redimensionar, tal como salieron de la
cámara. `post-vta.jpg` pesaba **26 MB a 8681×4883 px** para mostrarse a menos de mil.
Convertidas a WebP y bajadas a la medida en la que se ven: tarjetas de cobertura a
700 px, foto de la home a 1200, fondo a 1920, logo a 120 px de alto. Las ocho que no
usa ninguna vista se conservaron a 1600 px.

Se declaró además el **tipo MIME de WebP** en `Web.config` — IIS anterior al 10 no
lo conoce y devolvería 404 en vez de la imagen — y una semana de caché para los
estáticos.

### `14edf0a` · Diez scripts rotos y tres copias de Bootstrap
- **Diez `<script>` a archivos inexistentes**: `src/utils.js`, `src/renderhtml.js`,
  `src/storage.js`, `src/validations.js`, `src/quoter.js`, `src/customSelect.js`,
  `src/main.js`, `darkMode.js` y `main.js`. Diez 404 en cada carga de cada página, y
  con ruta relativa, así que se pedían a una dirección distinta según la ruta.
- **Bootstrap cargado hasta tres veces**: el paquete local, el CDN, y encima Ayuda,
  Preguntas y Siniestros traían 5.3.0 sobre el 5.2.0 del layout — dos versiones de la
  misma biblioteca peleando por los mismos componentes.
- **CSS duplicado**: `Content/bootstrap.css` es el mismo 5.2.0 sin minificar que ya
  bajaba del CDN. 248 KB de reglas repetidas por página.
- **El CDN apuntaba a `5.2.0-beta1`**: el sitio dependía en producción de una versión
  de prueba. Pasa a 5.2.0 estable, con el hash SRI calculado del archivo real.

Las páginas de `Views/Inicio` tienen `Layout = null` y su propio `<head>`, así que
nunca pasaron por el CDN: reciben Bootstrap del paquete local vía
`~/Content/css-completo`. Sin ese desdoblamiento se habrían quedado sin estilos.

### `841a753` · SEO
- **`<h1>` en las trece vistas.** Doce no tenían ninguno. Se promovió el encabezado
  que ya hacía de título, conservando la clase que le da el tamaño para que no cambie
  cómo se ve.
- **Descripción única por página.** Las trece compartían una, y decía *"Proyecto final
  para el curso de Integración de Sistemas de ISTEA"*: eso mostraba Google bajo el enlace.
- **Canonical, Open Graph, Twitter Card y JSON-LD** (`WebSite` + `Organization`).
- **`robots.txt` y `sitemap.xml`** generados por `SeoController`, no escritos a mano:
  las URL absolutas salen de la dirección real del pedido, así que funcionan en
  localhost, en una IP de pruebas y en el dominio definitivo sin tocar nada.
- **Alt reales en el carrusel de socios.** «Aseguradora 1» … «Aseguradora 20» pasan a
  nombrar cada compañía. Quince se identificaron por la URL del logo; **cinco quedaron
  con alt genérico y un `REVISAR` al lado** — un alt equivocado es peor que uno genérico.
- **`lang="es"` y `noindex`** en las páginas de cuenta, que no lo tenían.
- Bonus: en `Confirmar.cshtml`, `<div class="container" mt-3>` tenía `mt-3` como
  atributo suelto en vez de como clase.

### `a2b51e4` · Las rutas a los paquetes apuntaban fuera del repositorio
El `.csproj` buscaba los paquetes de NuGet en `..\packages` — un nivel **más
arriba** de la carpeta del repositorio. Esa es la ruta del esquema clásico (una
carpeta de solución con la del proyecto adentro), pero acá el `.sln` y el
`.csproj` están los dos en la raíz. Con eso, `nuget restore` dejaba los paquetes
en `Seguranet\packages` y la compilación los buscaba en `repos\packages`: 52
referencias que nunca se iban a resolver. **El proyecto no compilaba.** Ahora son
relativas a la raíz, que es donde están.

### `48219db` · Encontrado al ejecutar
Dos errores propios de esta pasada que sólo aparecieron con el sitio corriendo:

1. **`robots.txt` y `sitemap.xml` devolvían 404.** Los genera `SeoController`,
   pero como terminan en `.txt` y `.xml`, IIS los atendía con su manejador de
   archivos estáticos, no encontraba nada en el disco y cortaba antes de que MVC
   mirara las rutas. Se mandan esos dos al manejador administrado, uno por uno —
   no con `runAllManagedModulesForAllRequests`, que haría pasar por el pipeline
   administrado también a las imágenes y a las hojas de estilo.
2. **`CotizadorAuto.cshtml` salía con los acentos rotos**: "Calculá" se veía
   "CalculÃ¡". Lo escribí sin BOM, y sin BOM Razor lo lee con la codificación del
   sistema en vez de UTF-8. Todas las demás vistas del proyecto sí lo tienen.

### Lo que se comprobó con el sitio andando
- Las **trece páginas** responden 200, incluidas las de cuenta.
- `robots.txt` y `sitemap.xml` responden 200; el sitemap lista las **siete URL
  públicas**, sin las de cuenta ni el cotizador.
- Cada página: **un solo `<h1>`** y su **propia descripción**.
- Canonical, Open Graph y Twitter Card presentes; el **JSON-LD parsea**.
- Las **siete imágenes** se sirven como `image/webp`.
- Las páginas de cuenta llevan `noindex` y `lang="es"` — menos Login, que es la
  raíz del sitio y quedó sin él a propósito.
- **Ningún** `<script>` roto: los diez 404 por carga desaparecieron.
- Y lo que más riesgo tenía del desdoble de paquetes: `Registrar`, que no usa el
  layout, **sigue recibiendo** `Content/bootstrap.css`, y la home ya no lo repite.

## Pendiente: crítico

### La raíz del sitio es la pantalla de login
`RouteConfig` manda `/` a `Inicio/Login`. O sea que la dirección principal de un sitio
público es un formulario de acceso, y las páginas que venden viven colgadas de `/Home/`.

Por eso **Login quedó sin `noindex` a propósito**, aunque sus hermanas sí lo llevan:
marcarla sacaría el sitio entero de Google. Está explicado en la propia vista.

**Lo correcto sería que la ruta por defecto apunte a `Home/Index`**, pero eso cambia
cómo se comporta la aplicación y es una decisión, no una corrección.

### El cotizador no existe
`CotizadorAuto.cshtml` es un placeholder de tres líneas que dice *"Aqui va el
cotizador"* — y es la función central del sitio, y está en el menú principal. Se le
puso título, descripción y `<h1>`, y quedó fuera del sitemap y bloqueado en
`robots.txt` para no mandar tráfico a un callejón sin salida. Pero el trabajo real está
por hacerse.

### La cadena de conexión apunta a una máquina de desarrollo
`Server=MITO` en `Web.config`. No hay credenciales expuestas — usa `Integrated
Security` —, pero el proyecto no se puede publicar tal cual.

### Sin HTTPS forzado
No hay redirección HTTP→HTTPS. No se agregó porque sin saber dónde se va a publicar,
forzarla puede romper el entorno local. Es una línea en `Web.config` o una regla del
hosting, en cuanto haya hosting.

### Sin analítica ni Search Console
No hay ningún script de medición. A diferencia de Enricci, acá no se dejó preparado
porque primero hay que decidir si el proyecto sigue vivo.

### Responsividad móvil sin verificar
Hay `viewport` y Bootstrap, pero nada comprobado a 360 / 390 / 430 px. Ahora que las
imágenes pesan lo que tienen que pesar, tiene sentido revisarlo — pero hace falta
levantar el sitio.

## Pendiente: importante

- **Los veinte logos de aseguradoras están enlazados desde sitios ajenos** — Wikimedia,
  el WordPress de una concesionaria, LinkedIn, Behance, un CDN de CloudFront. Eso gasta
  el ancho de banda de otro y deja el sitio a merced de que muevan el archivo: el día
  que alguno cambie la URL, ahí queda un hueco. Habría que descargarlos a
  `~/Imagenes/aseguradoras/` y servirlos desde el sitio — **con permiso de uso de marca**.
- **Cinco alt sin identificar** en ese mismo carrusel, marcados con `REVISAR`.
- **Sin política de privacidad ni términos.** El sitio tiene registro de usuarios con
  email y datos personales. Aunque sea académico, el formulario recoge datos reales.
- **Sin banner de cookies.** Hoy no carga terceros de tracking, así que no es urgente —
  pero pasa a ser obligatorio en cuanto entre la analítica.
- **Accesibilidad sin verificar**: contraste AA, foco visible, navegación por teclado y
  `label` en los formularios de login y registro.
- **Cuatro CDNs distintos** siguen bloqueando el render (Font Awesome, Google Fonts,
  animate.css, jsDelivr). Se les puso `preconnect`, que ayuda, pero lo que de verdad
  serviría es servir esas hojas desde el propio sitio.

## Necesita decisión tuya

Este proyecto no tiene cliente: las decisiones son tuyas.

1. **¿Sigue siendo pieza de portafolio o se archiva?** Si se archiva, lo ya hecho
   (`.gitignore` e imágenes) alcanza y el resto no vale la pena.
2. **Compilar y probar en Visual Studio.** Es el paso que no pude dar acá.
3. **¿Se cambia la ruta por defecto a `Home/Index`?**
4. **¿Se implementa el cotizador o se saca del menú?**
5. **Permiso de uso de los logos** de aseguradoras, o reemplazo por genéricos.
6. **Dónde se publicaría**, para poder cerrar HTTPS, analítica y Search Console.
7. **¿Se limpia el historial de git** de los 590 MB de paquetes? Obliga a reescribir la
   rama remota.

## No aplica

- **T7** redirecciones 301, **T8** hreflang — sin versión previa y con un solo idioma.
- **T10** backups — sin entorno de producción todavía.
- **S7** blog, **S10** contenido duplicado — no hay catálogo ni contenido editorial.
- **C1–C6, C9, C11, C12** — bloque de confianza: sin empresa real detrás, no hay
  "sobre nosotros" con caras, ni testimonios, ni razón social que declarar.
- **Casi todo el bloque V** — asume un negocio real vendiendo. Aplican V2 (formulario),
  V3 (validación) y V7 (FAQs: la vista `Preguntas` ya existe).
- **M4–M7** — heatmaps, embudo, A/B y alertas de caída necesitan tráfico real.
