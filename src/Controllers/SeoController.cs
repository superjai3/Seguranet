using System.Text;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Options;
using Seguranet.Servicios;

namespace Seguranet.Controllers;

/// <summary>
/// robots.txt y sitemap.xml.
///
/// Se generan en vez de escribirse a mano porque las dos cosas necesitan la
/// dirección absoluta del sitio. Armándolos desde la configuración —o, si no
/// hay dominio cargado, desde la URL del pedido— salen bien en localhost, en
/// una IP de pruebas y en seguranet.es, sin acordarse de venir a cambiar nada.
///
/// En MVC 5 esto necesitaba además dos manejadores declarados en Web.config:
/// como las direcciones terminan en .txt y .xml, IIS las atendía con su
/// manejador de archivos estáticos y devolvía 404 antes de que el enrutado
/// llegara a mirar. Kestrel no tiene ese problema.
/// </summary>
public class SeoController : Controller
{
    private readonly OpcionesSitio _sitio;

    public SeoController(IOptions<OpcionesSitio> sitio) => _sitio = sitio.Value;

    /// <summary>
    /// Las páginas públicas, con su prioridad relativa y cada cuánto suelen
    /// cambiar. No están las de cuenta ni el cotizador, que sigue siendo un
    /// placeholder: un sitemap que promete páginas vacías es peor que no tener
    /// sitemap.
    /// </summary>
    private static readonly (string Accion, string Prioridad, string Frecuencia)[] Paginas =
    [
        ("Index",      "1.0", "weekly"),
        ("Coberturas", "0.9", "monthly"),
        ("Preguntas",  "0.8", "monthly"),
        ("Siniestros", "0.8", "monthly"),
        ("Contacto",   "0.7", "monthly"),
        ("Nosotros",   "0.6", "yearly"),
        ("Ayuda",      "0.6", "monthly"),
    ];

    [HttpGet("robots.txt")]
    public IActionResult Robots()
    {
        var raiz = _sitio.UrlBase(Request);

        var texto = new StringBuilder();
        texto.AppendLine("User-agent: *");
        texto.AppendLine();
        texto.AppendLine("# Las páginas de cuenta no le aportan nada a quien busca.");
        texto.AppendLine("Disallow: /Inicio/Registrar");
        texto.AppendLine("Disallow: /Inicio/Restablecer");
        texto.AppendLine("Disallow: /Inicio/Actualizar");
        texto.AppendLine("Disallow: /Inicio/Confirmar");
        texto.AppendLine();
        texto.AppendLine("# La API no es contenido: no hay nada que indexar y sí cuota que gastar.");
        texto.AppendLine("Disallow: /api/");
        texto.AppendLine();
        texto.AppendLine("# El cotizador es todavía un placeholder: hasta que exista, mandar");
        texto.AppendLine("# tráfico ahí es mandarlo a un callejón sin salida.");
        texto.AppendLine("Disallow: /Home/CotizadorAuto");
        texto.AppendLine();
        texto.AppendLine("# El CSS y el JavaScript tienen que quedar accesibles: Google los");
        texto.AppendLine("# descarga para ver la página como la ve una persona, y si los");
        texto.AppendLine("# bloqueás la juzga rota.");
        texto.AppendLine("Allow: /css/");
        texto.AppendLine("Allow: /js/");
        texto.AppendLine("Allow: /Imagenes/");
        texto.AppendLine();
        texto.AppendLine($"Sitemap: {raiz}/sitemap.xml");

        return Content(texto.ToString(), "text/plain", Encoding.UTF8);
    }

    [HttpGet("sitemap.xml")]
    public IActionResult Sitemap()
    {
        var raiz = _sitio.UrlBase(Request);
        var hoy = DateTime.UtcNow.ToString("yyyy-MM-dd");

        var xml = new StringBuilder();
        xml.AppendLine("""<?xml version="1.0" encoding="UTF-8"?>""");
        xml.AppendLine("""<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">""");

        foreach (var (accion, prioridad, frecuencia) in Paginas)
        {
            // La home es "/" y no "/Home/Index": son la misma página, y
            // declarar la larga hace que el buscador indexe dos direcciones
            // para el mismo contenido.
            var ruta = accion == "Index" ? "/" : $"/Home/{accion}";

            xml.AppendLine("  <url>");
            xml.AppendLine($"    <loc>{raiz}{ruta}</loc>");
            xml.AppendLine($"    <lastmod>{hoy}</lastmod>");
            xml.AppendLine($"    <changefreq>{frecuencia}</changefreq>");
            xml.AppendLine($"    <priority>{prioridad}</priority>");
            xml.AppendLine("  </url>");
        }

        xml.AppendLine("</urlset>");

        return Content(xml.ToString(), "application/xml", Encoding.UTF8);
    }
}
