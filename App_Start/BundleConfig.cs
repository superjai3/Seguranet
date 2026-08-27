using System.Web;
using System.Web.Optimization;

namespace Seguranet
{
    public class BundleConfig
    {
        // Para obtener más información sobre las uniones, visite https://go.microsoft.com/fwlink/?LinkId=301862
        public static void RegisterBundles(BundleCollection bundles)
        {
            bundles.Add(new ScriptBundle("~/bundles/jquery").Include(
                        "~/Scripts/jquery-{version}.js"));

            bundles.Add(new ScriptBundle("~/bundles/jqueryval").Include(
                        "~/Scripts/jquery.validate*"));

            // Utilice la versión de desarrollo de Modernizr para desarrollar y obtener información sobre los formularios.  De esta manera estará
            // para la producción, use la herramienta de compilación disponible en https://modernizr.com para seleccionar solo las pruebas que necesite.
            bundles.Add(new ScriptBundle("~/bundles/modernizr").Include(
                        "~/Scripts/modernizr-*"));

            // Hay dos clases de página en este proyecto, y necesitan cosas
            // distintas:
            //
            //  · Las que usan _Layout.cshtml (todo Views/Home) reciben Bootstrap
            //    del CDN. Sumarles Content/bootstrap.css les hacía bajar 248 KB
            //    de exactamente las mismas reglas —es el mismo 5.2.0, sólo que
            //    sin minificar—, así que acá va únicamente la hoja del sitio.
            //
            //  · Las de Views/Inicio tienen Layout = null y su propio <head>:
            //    no pasan por el CDN, y si les sacás Content/bootstrap.css se
            //    quedan sin estilos. Esas piden el paquete completo.
            bundles.Add(new StyleBundle("~/Content/css").Include(
                      "~/Content/site.css"));

            bundles.Add(new StyleBundle("~/Content/css-completo").Include(
                      "~/Content/bootstrap.css",
                      "~/Content/site.css"));

            // Lo mismo con el JavaScript: sólo lo piden las páginas sin layout.
            bundles.Add(new Bundle("~/bundles/bootstrap").Include(
                      "~/Scripts/bootstrap.js"));
        }
    }
}
