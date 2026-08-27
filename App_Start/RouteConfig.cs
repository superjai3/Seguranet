using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.Mvc;
using System.Web.Routing;

namespace Seguranet
{
    public class RouteConfig
    {
        public static void RegisterRoutes(RouteCollection routes)
        {
            routes.IgnoreRoute("{resource}.axd/{*pathInfo}");

            // robots.txt y sitemap.xml los arma SeoController, para que las URL
            // absolutas salgan de la dirección real del sitio y no de un dominio
            // escrito a mano que hoy todavía no existe. Van antes de la ruta por
            // defecto: si no, "robots.txt" se leería como un nombre de
            // controlador.
            routes.MapRoute(
                name: "Robots",
                url: "robots.txt",
                defaults: new { controller = "Seo", action = "Robots" }
            );

            routes.MapRoute(
                name: "Sitemap",
                url: "sitemap.xml",
                defaults: new { controller = "Seo", action = "Sitemap" }
            );

            routes.MapRoute(
                name: "Default",
                url: "{controller}/{action}/{id}",
                defaults: new { controller = "Inicio", action = "Login", id = UrlParameter.Optional }
            );
        }
    }
}
