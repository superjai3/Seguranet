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

            // La raíz del sitio es la home pública, no la pantalla de acceso.
            //
            // Antes esto apuntaba a Inicio/Login: la dirección principal de un
            // sitio que quiere que lo encuentren era un formulario de usuario y
            // contraseña, y todo lo que explica el negocio —coberturas,
            // siniestros, preguntas— colgaba de /Home/. Para un buscador, la
            // página más importante del sitio era la menos útil de todas, y no
            // se le podía poner noindex sin sacar el sitio entero del índice.
            //
            // Quien quiera entrar a su cuenta sigue teniendo /Inicio/Login, que
            // es donde estaba y ahora sí puede llevar noindex.
            routes.MapRoute(
                name: "Default",
                url: "{controller}/{action}/{id}",
                defaults: new { controller = "Home", action = "Index", id = UrlParameter.Optional }
            );
        }
    }
}
