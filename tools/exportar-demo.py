#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Exporta las páginas públicas del sitio a HTML plano, para publicarlo en GitHub Pages.

Seguranet es ASP.NET MVC 5: las vistas son Razor y para verlas hace falta IIS y
Windows. GitHub Pages sólo sirve archivos estáticos, así que no puede ejecutar
nada de eso. Lo que sale de acá es una demo de sólo lectura —lo que el sitio se
ve, no lo que el sitio hace— para poder mostrar el proyecto en un enlace sin
levantar un servidor.

Queda afuera todo lo que necesita el servidor:

  · El área de cuenta (Views/Inicio: login, registro, restablecer). Además de
    postear contra el servidor, consulta SQL Server.
  · El POST del formulario de contacto (HomeController.Enviar).

El formulario de la página de contacto es un iframe de forms.app, servicio
externo, así que en la demo sigue funcionando igual que en el sitio real.

Esto no es un motor Razor: es un traductor para estas ocho vistas y este layout,
con las construcciones que usan hoy (@Url.Action, @Html.ActionLink, @section,
comentarios y el @if de sesión). Si alguien agrega Razor de otra clase, hay que
enseñárselo acá; si no, va a aparecer crudo en la salida. Por eso al final
verifica que en el HTML generado no haya quedado ninguna @ sin traducir, y falla
si encuentra alguna.

Uso:
    python3 tools/exportar-demo.py [--base URL] [--salida docs]
"""

import argparse
import datetime
import html
import re
import shutil
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

# Cada acción del HomeController, con el archivo que le toca en la demo. El
# nombre sale de la ruta MVC en minúscula, para que las direcciones se parezcan
# a las del sitio real (/Home/Coberturas -> coberturas.html).
PAGINAS = [
    ("Index",         "index.html"),
    ("Nosotros",      "nosotros.html"),
    ("Coberturas",    "coberturas.html"),
    ("CotizadorAuto", "cotizadorauto.html"),
    ("Preguntas",     "preguntas.html"),
    ("Ayuda",         "ayuda.html"),
    ("Siniestros",    "siniestros.html"),
    ("Contacto",      "contacto.html"),
]
ARCHIVO_DE = dict(PAGINAS)

# Los estáticos que el sitio sirve desde su propia raíz. Bootstrap, las fuentes
# y Font Awesome vienen de CDN y no hay que copiar nada.
ASSETS = ["Imagenes", "Style/styles.css", "Content/Site.css", "favicon.ico"]

AVISO = """<div class="demo-aviso" role="note">
      <strong>Demo estática.</strong>
      Reproduce las páginas públicas de Seguranet, que es una aplicación
      ASP.NET&nbsp;MVC&nbsp;5. El área de cuenta y el envío de formularios
      necesitan el servidor y no están en esta versión.
      <a href="https://github.com/superjai3/Seguranet">Ver el código en GitHub</a>
    </div>"""

# Con qué se da por sentado que quedó Razor crudo en la salida. No alcanza con
# buscar arrobas: las hay legítimas en el HTML final —las claves @context y
# @type del JSON-LD, y las versiones de los CDN (bootstrap@5.3.3, aos@2.3.4)—.
# Van entonces las construcciones de Razor por nombre, más los marcadores
# {{...}} de la plantilla por si alguno se quedó sin reemplazar.
RASTROS_DE_RAZOR = re.compile(
    r"@(?:Html|Url|Styles|Scripts|ViewBag|Model|DateTime|Session|Render\w*|section|using|model|if|foreach|for|while|switch|functions|helper)\b"
    r"|@\{|@\*|\{\{\w+\}\}"
)

ESTILO_AVISO = """
    <!-- Sólo de la demo: no existe en el sitio real. -->
    <style>
        .demo-aviso {
            background: #0b3d2c;
            color: #fff;
            padding: .75rem 1rem;
            font-size: .95rem;
            line-height: 1.5;
            text-align: center;
        }
        .demo-aviso a { color: #9fe8c4; }
    </style>"""


def sin_bom(texto):
    return texto.lstrip("﻿")


def sin_comentarios(texto):
    """Saca los comentarios de Razor (@* ... *@), que no van al HTML."""
    return re.sub(r"@\*.*?\*@", "", texto, flags=re.S)


def fin_de_bloque(texto, apertura):
    """Devuelve el índice siguiente al } que cierra el { que está en `apertura`."""
    nivel = 0
    for i in range(apertura, len(texto)):
        if texto[i] == "{":
            nivel += 1
        elif texto[i] == "}":
            nivel -= 1
            if nivel == 0:
                return i + 1
    raise ValueError("Hay una llave sin cerrar a partir de la posición %d" % apertura)


def sacar_bloque(texto, marcador, reemplazo=""):
    """Reemplaza `marcador` y el bloque { ... } que le sigue."""
    inicio = texto.find(marcador)
    if inicio == -1:
        return texto
    apertura = texto.index("{", inicio + len(marcador) - 1)
    return texto[:inicio] + reemplazo + texto[fin_de_bloque(texto, apertura):]


def sacar_if_else(texto, marcador, reemplazo):
    """Reemplaza un @if (...) { ... } else { ... } entero por HTML fijo.

    En la demo no hay sesión, así que de las dos ramas siempre gana la misma;
    en vez de quedarnos con el `else` tal cual —que ofrece un enlace a una
    página que no exportamos— ponemos lo que le pasamos por `reemplazo`.
    """
    inicio = texto.find(marcador)
    if inicio == -1:
        return texto
    fin_if = fin_de_bloque(texto, texto.index("{", inicio + len(marcador)))
    resto = texto[fin_if:]
    if resto.lstrip().startswith("else"):
        salto = len(resto) - len(resto.lstrip())
        fin_if = fin_de_bloque(texto, texto.index("{", fin_if + salto))
    return texto[:inicio] + reemplazo + texto[fin_if:]


def traducir_enlaces(texto):
    """@Url.Action y @Html.ActionLink -> direcciones y <a> de la demo."""

    def url(m):
        accion = m.group(1)
        return ARCHIVO_DE.get(accion, accion.lower() + ".html")

    texto = re.sub(r'@Url\.Action\(\s*"(\w+)"\s*,\s*"Home"\s*\)', url, texto)

    def enlace(m):
        etiqueta, accion, atributos = m.group(1), m.group(2), m.group(3) or ""
        clase = re.search(r'@class\s*=\s*"([^"]*)"', atributos)
        destino = ARCHIVO_DE.get(accion, accion.lower() + ".html")
        if clase:
            return '<a href="%s" class="%s">%s</a>' % (destino, clase.group(1), etiqueta)
        return '<a href="%s">%s</a>' % (destino, etiqueta)

    return re.sub(
        r'@Html\.ActionLink\(\s*"([^"]*)"\s*,\s*"(\w+)"\s*,\s*"Home"\s*((?:,\s*new\s*\{[^}]*\}\s*)*)\)',
        enlace,
        texto,
    )


def rutas_relativas(texto):
    """~/algo -> algo. Las páginas quedan todas en la misma carpeta."""
    return re.sub(r'(src|href)="~/', r'\1="', texto)


def leer_vista(accion):
    """Devuelve (título, descripción, cuerpo HTML) de una vista de Views/Home."""
    texto = sin_bom((RAIZ / "Views" / "Home" / (accion + ".cshtml")).read_text(encoding="utf-8"))

    titulo = re.search(r'ViewBag\.Title\s*=\s*"([^"]*)"', texto)
    descripcion = re.search(r'ViewBag\.Descripcion\s*=\s*"([^"]*)"', texto)

    texto = sin_comentarios(texto)
    texto = re.sub(r"^@using [^\n]*\n", "", texto, flags=re.M)
    texto = re.sub(r"^@model [^\n]*\n", "", texto, flags=re.M)
    # El bloque de arriba (ViewBag) ya lo leímos; al HTML no va.
    texto = sacar_bloque(texto, "@{")
    # Las secciones de scripts de estas vistas hoy sólo tienen comentarios
    # —quedaron vacías al sacar las bibliotecas duplicadas—, así que no se
    # pierde nada. Si alguna vez llevan código, hay que renderizarlas.
    texto = sacar_bloque(texto, "@section Scripts")
    texto = traducir_enlaces(texto)
    texto = rutas_relativas(texto)

    return (
        titulo.group(1) if titulo else "Seguranet",
        descripcion.group(1) if descripcion else "",
        texto.strip(),
    )


def armar_layout():
    """Convierte _Layout.cshtml en una plantilla con marcadores {{...}}."""
    texto = sin_bom((RAIZ / "Views" / "Shared" / "_Layout.cshtml").read_text(encoding="utf-8"))
    texto = sin_comentarios(texto)
    texto = sacar_bloque(texto, "@{")

    # En la demo nunca hay nadie con la sesión iniciada, y las páginas de cuenta
    # no se exportan: el enlace "Ingresar" llevaría a un 404. Queda a la vista,
    # inutilizado y diciendo por qué.
    texto = sacar_if_else(
        texto,
        "@if (Seguranet.Servicios.SesionServicio.HayUsuario(Session))",
        '<li class="nav-item">\n'
        '                                        <span class="nav-link disabled" aria-disabled="true"\n'
        '                                              title="El área de cuenta necesita el servidor y la base de datos: no está en la demo.">Ingresar</span>\n'
        '                                    </li>',
    )

    # Los paquetes de System.Web.Optimization no existen fuera de ASP.NET. El
    # de estilos es un archivo que sí copiamos; los de scripts (modernizr,
    # jQuery) no los usa ninguna de estas páginas —Bootstrap 5 viene del CDN y
    # no necesita jQuery—, así que se van.
    texto = texto.replace(
        '@Styles.Render("~/Content/css")',
        '<link rel="stylesheet" type="text/css" href="Content/Site.css">',
    )
    texto = re.sub(r'@Scripts\.Render\("[^"]*"\)', "", texto)
    texto = re.sub(r'@RenderSection\([^)]*\)', "", texto)

    # El año del pie se calcula en cada pedido en el sitio real; acá queda
    # congelado al momento de exportar, así que conviene volver a correr esto
    # cuando cambie el año.
    texto = texto.replace("@DateTime.Now.Year", str(datetime.date.today().year))
    # En Razor, @@ es una arroba literal: en el JSON-LD son las claves de
    # schema.org (@context, @type).
    texto = texto.replace("@@", "@")

    texto = traducir_enlaces(texto)
    texto = rutas_relativas(texto)

    texto = texto.replace("@tituloPagina", "{{titulo}}")
    texto = texto.replace("@descripcionPagina", "{{descripcion}}")
    texto = texto.replace("@urlCanonica", "{{canonica}}")
    texto = texto.replace("@raizDelSitio", "{{raiz}}")
    texto = texto.replace("@urlImagenSocial", "{{imagen_social}}")
    texto = texto.replace("@urlLogo", "{{logo}}")
    texto = texto.replace("@RenderBody()", "{{cuerpo}}")

    # La demo no tiene que estar en Google: es un trabajo de cátedra que se
    # presenta como una aseguradora, y un buscador no distingue una cosa de la
    # otra. El resto de las etiquetas (canónica, Open Graph, JSON-LD) queda tal
    # como está en el sitio, que es justamente lo que se quiere mostrar.
    texto = texto.replace(
        '<meta name="robots" content="index, follow, max-image-preview:large" />',
        '<meta name="robots" content="noindex, nofollow" />\n'
        '    <!-- El sitio real declara index, follow, max-image-preview:large.\n'
        '         Acá va noindex porque esto es una demo, no la aseguradora. -->',
    )
    texto = texto.replace("</head>", ESTILO_AVISO + "\n</head>")
    texto = texto.replace("<body>", "<body>\n    " + AVISO)

    return texto


def escribir(destino, base):
    layout = armar_layout()

    for accion, archivo in PAGINAS:
        titulo, descripcion, cuerpo = leer_vista(accion)
        canonica = base + "/" + ("" if archivo == "index.html" else archivo)
        pagina = (layout
                  .replace("{{titulo}}", html.escape(titulo, quote=True))
                  .replace("{{descripcion}}", html.escape(descripcion, quote=True))
                  .replace("{{canonica}}", canonica)
                  .replace("{{raiz}}", base)
                  .replace("{{imagen_social}}", base + "/Imagenes/hero-image.png.webp")
                  .replace("{{logo}}", base + "/Imagenes/Logo%20Seguranet.webp")
                  .replace("{{cuerpo}}", cuerpo))

        sobrante = re.findall(RASTROS_DE_RAZOR, pagina)
        if sobrante:
            raise SystemExit(
                "%s: quedó Razor sin traducir (%s). Hay que enseñarle a "
                "tools/exportar-demo.py a manejarlo." % (archivo, ", ".join(sorted(set(sobrante))))
            )

        (destino / archivo).write_text(pagina, encoding="utf-8")
        print("  %-20s <- Views/Home/%s.cshtml" % (archivo, accion))

    for asset in ASSETS:
        origen = RAIZ / asset
        copia = destino / asset
        copia.parent.mkdir(parents=True, exist_ok=True)
        if origen.is_dir():
            shutil.copytree(origen, copia, dirs_exist_ok=True)
        else:
            shutil.copy2(origen, copia)
        print("  %-20s <- %s" % (asset, asset))

    # Site.css pide el fondo a /Imagenes/..., desde la raíz del dominio. En
    # GitHub Pages el sitio cuelga de /Seguranet/, así que esa dirección no
    # existe: la hacemos relativa en la copia, sin tocar el original.
    css = destino / "Content" / "Site.css"
    css.write_text(css.read_text(encoding="utf-8").replace("url('/Imagenes/", "url('../Imagenes/"),
                   encoding="utf-8")

    # Sin esto GitHub Pages pasa todo por Jekyll, que se saltea los archivos y
    # carpetas que empiezan con guión bajo.
    (destino / ".nojekyll").write_text("", encoding="utf-8")


def main():
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--base", default="https://superjai3.github.io/Seguranet",
                        help="Raíz donde se va a publicar (sin barra final).")
    parser.add_argument("--salida", default="docs", help="Carpeta de salida.")
    args = parser.parse_args()

    destino = RAIZ / args.salida
    if destino.exists():
        shutil.rmtree(destino)
    destino.mkdir(parents=True)

    print("Exportando a %s/ con base %s" % (args.salida, args.base))
    escribir(destino, args.base.rstrip("/"))
    print("Listo.")


if __name__ == "__main__":
    sys.exit(main())
