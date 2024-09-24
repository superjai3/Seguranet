using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.Mvc;

namespace Seguranet.Controllers
{
    public class HomeController : Controller
    {
        public ActionResult Index()
        {
            return View();
        }
        public ActionResult Nosotros()
        {
            return View();
        }
        public ActionResult Coberturas()
        {
            return View();
        }
        public ActionResult CotizadorAuto()
        {
            return View();
        }
        public ActionResult Preguntas()
        {
            return View();
        }
        public ActionResult Ayuda()
        {
            return View();
        }
        public ActionResult Siniestros()
        {
            return View();
        }
        public ActionResult Contacto()
        {
            ViewBag.TipoConsulta = new List<SelectListItem>
                {
                    new SelectListItem { Value = "1", Text = "Consulta General" },
                    new SelectListItem { Value = "2", Text = "Soporte Técnico" },
                    new SelectListItem { Value = "3", Text = "Ventas" },
                    new SelectListItem { Value = "4", Text = "Sugerencia" }
                };
            return View();
        }
    }
}


