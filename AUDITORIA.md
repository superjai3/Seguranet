# Auditoría web · Seguranet

2026-08-27 · ASP.NET MVC 5 (.NET Framework 4.6.2) + SQL Server · modelo: **SaaS**
Rama `mejoras/auditoria-web-2026-08-27`

## Resumen

De los **57 puntos aplicables** al modelo SaaS:

| Estado | Puntos |
|---|---|
| **`ok`** — cumplido y verificado | **19** |
| `parcial` — está pero a medias | 6 |
| `cliente` — depende de una decisión tuya | 1 |
| `falta` — no está y se puede hacer | 31 |

**22 arreglados en esta pasada**, casi todos técnicos: el repositorio, las
imágenes, los scripts rotos, el SEO y el arranque de la aplicación.

Seguranet es un CRM/cotizador de seguros hecho como proyecto final de cátedra
(ISTEA). Funcionaba, pero nunca había pasado por una capa de producción — y la
rama principal llevaba desde noviembre de 2024 sin compilar.

> **Corrección, dos veces.** Una versión anterior decía «43 de 60 cumplidos». Ese
> número estaba mal por dos motivos a la vez. Primero, sumaba como cumplidos los
> `parcial` y los `cliente`. Y segundo, y más de fondo: había **35 puntos marcados
> `na`**, cuando el modelo SaaS tiene 60 aplicables — el propio checklist avisa que
> una diferencia así significa que el modelo se identificó mal. Se descartaron de
> más los bloques de **confianza** y **conversión** con el argumento de que «no hay
> empresa real detrás»; pero el sitio dice vender seguros, así que esos puntos
> aplican y lo que corresponde es marcarlos como lo que son: **faltantes**.
>
> Reevaluados los 21 puntos mal descartados, el número honesto es **19 de 57**. El
> salto de `falta` de 14 a 31 no es que el sitio haya empeorado: es que antes esos
> puntos estaban escondidos debajo de un «no aplica» que no correspondía.

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

### `673ea8a` · La rama de la presentación final no compilaba
Al ir a subir esto apareció que `origin/main` tenía un commit de dos semanas
después de la base sobre la que se hizo la auditoría: **«Cambios realizados para
la presentación final»**. Trae cosas buenas —el formulario de contacto con envío
de correo, Bootstrap local a 5.3.3, jQuery a 3.7.1— pero dejó la rama sin
compilar. Tres motivos:

1. **El `.csproj` referenciaba seis versiones de paquetes que su propio
   `packages.config` no declaraba** (CodeDom 2.0.1 y 4.1.0, MimeKit 4.0.0,
   SweetAlert2 1.0.0, Components.Analyzers 3.1.0 y 8.0.10). El restore no las
   bajaba y la compilación cortaba antes de empezar.
2. **`Controllers/CotizadorController.cs` estaba escrito con Razor Pages** —
   hereda de `PageModel`, usa `BindProperty` y devuelve un valor desde un método
   `void`. En MVC 5 nada de eso existe. Encima duplicaba la acción `CotizadorAuto`
   que ya tiene `HomeController`. Por lo mismo se fueron
   `Views/_ViewImports.cshtml` y `Views/_ViewImports.cshtml.cshtml`, que son de
   ASP.NET Core y acá no los lee nadie.
3. **Dos desajustes que compilaban y reventaban recién al abrir una página**: la
   referencia a CodeDom declaraba `Version=2.0.1.0` apuntando al DLL 4.1.0, y
   `Views/Web.config` pedía `System.Web.Mvc` 5.2.9 cuando `packages.config`
   declara 5.2.7 y la redirección de enlace sólo llega hasta ahí.

Y algo que estaba mal aunque compilara: **tres enlaces apuntaban a
`http://127.0.0.1:5500`** —el menú de todas las páginas, el botón principal de la
home y el de Coberturas—, que es la dirección de Live Server en la máquina de
quien lo programó. Y **«Cerrar sesión» apuntaba a `https://localhost:44395`**, el
puerto de IIS Express. Ninguno llevaba a ningún lado para un visitante.

El CDN de Bootstrap pasa a 5.3.3 para coincidir con el local: antes el CDN servía
5.2.0-beta1 y el paquete local 5.3.3, así que una página se veía con una versión u
otra según pasara o no por el layout.

### `a8cef74` · La raíz del sitio y el cierre de sesión
Los dos puntos que este informe había dejado como decisión, ya resueltos.

**La raíz del sitio es ahora la home pública.** `RouteConfig` mandaba `/` a
`Inicio/Login`: la dirección principal de un sitio que quiere que lo encuentren era
un formulario de usuario y contraseña, y todo lo que explica el negocio colgaba de
`/Home/`. Para un buscador, la página más importante del sitio era la menos útil de
todas — y no se le podía poner `noindex` sin sacar el sitio entero del índice. Ahora
`/` es `Home/Index`; `/Inicio/Login` sigue donde estaba y **ya lleva su `noindex`**
como sus hermanas.

**«Cerrar sesión» ahora cierra la sesión.** Al ir a implementarlo apareció algo más
gordo: **no había ninguna sesión que cerrar.** El login validaba correo y clave
contra la base y, si daban bien, hacía un `RedirectToAction` y nada más. Ni
`Session`, ni `FormsAuthentication`, ni un solo `[Authorize]` en todo el proyecto. El
botón del menú era decorativo.

Se agregó `Servicios/SesionServicio.cs`; el login deja registrado quién entró —sin
guardar la clave ni el token, que ahí adentro no hacen falta— e `InicioController`
tiene una acción `Salir` que limpia y abandona la sesión. El botón es un **POST con
token antiforgery**, no un enlace: con un GET alcanzaría que alguien indujera al
navegador a pedir `/Inicio/Salir` para dejar afuera a la persona sin que lo pidiera.
Y el menú cambia solo: «Ingresar» si no hay nadie, «Cerrar sesión» si hay alguien.

Verificado de punta a punta contra la base real, con un usuario de prueba creado y
borrado después (la tabla quedó con los 5 usuarios que tenía): sin sesión el menú
ofrece Ingresar; el login redirige y el menú pasa a Cerrar sesión; un `GET` a
`/Inicio/Salir` da **404**; un `POST` sin token da **500** por antifalsificación **y
la sesión sobrevive**; el `POST` con token redirige a la home y el menú vuelve a
Ingresar.

## Pendiente: crítico

### «Conoce a Nuestro Equipo» es gente que no existe
En `Views/Home/Nosotros.cshtml` hay seis personas con nombre y apellido —María
Rodríguez, Carlos Pérez, Lucía Gómez, Silvana Teran, Jose Viñas, Marta Seguias— y
foto. **Ninguna es real: las fotos son de iStock**, y no están descargadas y
licenciadas sino **enlazadas directamente a `media.istockphoto.com`**, que es el
servidor de vistas previas de una agencia de stock paga.

Son dos problemas de distinta gravedad en el mismo bloque:

1. **Un equipo inventado presentado como el equipo real.** Es el punto C1 del
   checklist, y es de los que no se arreglan solos: o son personas de verdad, o la
   sección se saca. **No lo toqué**: inventar o sustituir gente es exactamente lo
   que una auditoría no debe hacer por su cuenta.
2. **Uso de imágenes de una agencia paga sin licencia.** Aunque el proyecto sea
   académico, si esto está publicado es un problema legal, no estético.

**Qué hace falta:** decidir. O van las caras y los nombres del equipo real —que en
un proyecto de cátedra serían los del grupo—, o se elimina la sección entera.

### 34 imágenes enlazadas desde sitios ajenos
Ninguna imagen que no sea de la carpeta `Imagenes/` está alojada en el sitio. Están
tomadas de veintiún dominios distintos:

| De dónde | Cuántas | Qué son |
|---|---|---|
| `media.istockphoto.com` | 7 | el «equipo» y fotos de sección |
| `www.todoriesgo.com.ar` | 3 | logos de aseguradoras |
| `upload.wikimedia.org`, `media.licdn.com`, `cloudfront` … | 19 | el resto de los logos |
| `2u2yqkbs.forms.app` | 1 | el formulario de contacto entero, en un iframe |

Eso gasta el ancho de banda de otros y deja el sitio a merced de que muevan un
archivo: el día que alguno cambie la URL, acá queda un hueco y nadie se entera.
**Qué hace falta:** descargarlas a `~/Imagenes/`, con permiso de uso donde
corresponda, y servirlas desde el sitio.

### Nada del sitio está protegido
Esto salió a la luz al implementar la sesión, y es el punto más serio que queda.
**Ninguna página exige haber entrado.** No hay un solo `[Authorize]` en el proyecto,
ni sección `<authentication>` en `Web.config`. La sesión que se agregó identifica
quién entró, pero no protege nada: cualquiera llega a cualquier URL sin credenciales.

Hoy eso no expone datos, porque todas las páginas que existen son públicas a
propósito —son las que explican el negocio—. Pero **el sitio tiene registro de
usuarios, confirmación por correo y restablecimiento de clave**: toda esa maquinaria
está construida para algo que todavía no existe.

**Qué hace falta:** decidir qué pantallas debería ver sólo un usuario registrado —el
cotizador con sus cotizaciones guardadas, un panel de pólizas— y protegerlas. Sin esa
decisión, el login es un trámite que no da acceso a nada.

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
