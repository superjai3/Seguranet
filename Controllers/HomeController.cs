using Seguranet.Models;
using Seguranet.Servicios;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Net;
using System.Net.Mail;
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







        // Acción para mostrar el formulario
        public ActionResult Contacto()
        {
            return View();
        }

        // Acción para manejar el envío del formulario
        [HttpPost]
        [ValidateAntiForgeryToken]
        public ActionResult Enviar(ContactoViewModel model)
        {
            if (ModelState.IsValid)
            {
                // Procesa el formulario (enviar el correo, guardar en base de datos, etc.)
                TempData["Mensaje"] = "Gracias por tu consulta, nos pondremos en contacto contigo pronto.";
                return RedirectToAction("Contacto");
            }

            // Si el formulario no es válido, vuelve a mostrar la vista con los mensajes de error
            return View("Contacto", model);
        }
    }
}