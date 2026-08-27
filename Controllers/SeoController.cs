using System;
using System.Collections.Generic;
using System.Text;
using System.Web.Mvc;

namespace Seguranet.Controllers
{
    /// <summary>
    /// robots.txt y sitemap.xml.
    ///
    /// Se generan en vez de escribirse a mano porque las dos cosas necesitan la
    /// dirección absoluta del sitio, y esa dirección todavía no existe: el
    /// proyecto no está publicado. Armándolos desde la URL del pedido salen
    /// bien en localhost, en una IP de pruebas y en el dominio definitivo, sin
    /// que haya que acordarse de venir a cambiar nada.
    /// </summary>
    public class SeoController : Controller
    {
        /// <summary>
        /// Las páginas públicas, con la prioridad relativa y cada cuánto suelen
        /// cambiar. No están ni las de cuenta (/Inicio) ni el cotizador, que
        /// hoy es un placeholder: un sitemap que promete páginas vacías es peor
        /// que no tener sitemap.
        /// </summary>
        private static readonly List<Tuple<string, string, string, string>> Paginas =
            new List<Tuple<string, string, string, string>>
            {
                Tuple.Create("Index",      "Home", "1.0", "weekly"),
                Tuple.Create("Coberturas", "Home", "0.9", "monthly"),
                Tuple.Create("Preguntas",  "Home", "0.8", "monthly"),
                Tuple.Create("Siniestros", "Home", "0.8", "monthly"),
                Tuple.Create("Contacto",   "Home", "0.7", "monthly"),
                Tuple.Create("Nosotros",   "Home", "0.6", "yearly"),
                Tuple.Create("Ayuda",      "Home", "0.6", "monthly"),
            };

        private string RaizDelSitio()
        {
            var pedido = Request.Url;
            if (pedido == null)
            {
                return string.Empty;
            }

            return pedido.GetLeftPart(UriPartial.Authority);
        }

        public ActionResult Robots()
        {
            var texto = new StringBuilder();
            texto.AppendLine("User-agent: *");
            texto.AppendLine();
            texto.AppendLine("# Las páginas de cuenta no le aportan nada a quien busca.");
            texto.AppendLine("Disallow: /Inicio/Registrar");
            texto.AppendLine("Disallow: /Inicio/Restablecer");
            texto.AppendLine("Disallow: /Inicio/Actualizar");
            texto.AppendLine("Disallow: /Inicio/Confirmar");
            texto.AppendLine();
            texto.AppendLine("# El cotizador es todavía un placeholder: hasta que exista, mandar");
            texto.AppendLine("# tráfico ahí es mandarlo a un callejón sin salida.");
            texto.AppendLine("Disallow: /Home/CotizadorAuto");
            texto.AppendLine();
            texto.AppendLine("# El CSS y el JavaScript tienen que quedar accesibles: Google los");
            texto.AppendLine("# descarga para ver la página como la ve una persona, y si los bloqueás");
            texto.AppendLine("# la juzga rota.");
            texto.AppendLine("Allow: /Content/");
            texto.AppendLine("Allow: /Scripts/");
            texto.AppendLine("Allow: /Style/");
            texto.AppendLine("Allow: /Imagenes/");
            texto.AppendLine();
            texto.AppendLine("Sitemap: " + RaizDelSitio() + "/sitemap.xml");

            return Content(texto.ToString(), "text/plain", Encoding.UTF8);
        }

        public ActionResult Sitemap()
        {
            var raiz = RaizDelSitio();
            var hoy = DateTime.UtcNow.ToString("yyyy-MM-dd");

            var xml = new StringBuilder();
            xml.AppendLine(@"<?xml version=""1.0"" encoding=""UTF-8""?>");
            xml.AppendLine(@"<urlset xmlns=""http://www.sitemaps.org/schemas/sitemap/0.9"">");

            foreach (var pagina in Paginas)
            {
                var ruta = Url.Action(pagina.Item1, pagina.Item2);

                xml.AppendLine("  <url>");
                xml.AppendLine("    <loc>" + raiz + ruta + "</loc>");
                xml.AppendLine("    <lastmod>" + hoy + "</lastmod>");
                xml.AppendLine("    <changefreq>" + pagina.Item4 + "</changefreq>");
                xml.AppendLine("    <priority>" + pagina.Item3 + "</priority>");
                xml.AppendLine("  </url>");
            }

            xml.AppendLine("</urlset>");

            return Content(xml.ToString(), "application/xml", Encoding.UTF8);
        }
    }
}
