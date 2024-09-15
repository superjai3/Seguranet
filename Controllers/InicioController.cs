using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.Mvc;

using Seguranet.Models;
using Seguranet.Models.Datos;
using Seguranet.Servicios;


namespace Seguranet.Controllers
{
    public class InicioController : Controller
    {
        // GET: Inicio
        public ActionResult Login()
        {
            return View();
        }

        [HttpPost]
        public ActionResult Login(string correo, string clave)
        {
            UsuarioDTO usuario = DBUsuario.Validar(correo, UtilidadServicio.ConvertirSHA256(clave));

            if (usuario != null)
            {
                if (!usuario.Confirmado)
                {
                    ViewBag.Mensaje = $"Falta confirmar su cuenta. Se le envió un correo a {correo}";
                }
                else if (usuario.Restablecer)
                {
                    ViewBag.Mensaje = $"Se ha solicitado restablecer su cuenta, por favor revise su bandeja del correo {correo}";
                }
                else
                {
                    return RedirectToAction("Index", "Home"); // Redirige a la página de inicio
                }
            }
            else
            {
                ViewBag.Mensaje = "No se encontraron coincidencias"; // Mensaje de error si no se encuentran coincidencias
            }

            return View();
        }

        public ActionResult Registrar()
        {
            return View();
        }

        [HttpPost]
        public ActionResult Registrar(UsuarioDTO usuario)
        {
            // Verifica si las contraseñas coinciden
            if (usuario.Clave != usuario.ConfirmacionClave)
            {
                ViewBag.Nombre = usuario.Nombre;
                ViewBag.Apellido = usuario.Apellido;
                ViewBag.Dni = usuario.Dni;
                ViewBag.Correo = usuario.Correo;
                ViewBag.Mensaje = "Las contraseñas no coinciden";
                return View();
            }

            // Verifica si el correo ya está registrado
            if (DBUsuario.Obtener(usuario.Correo) == null)
            {
                // Prepara la información del usuario para ser registrado
                usuario.Clave = UtilidadServicio.ConvertirSHA256(usuario.Clave);
                usuario.Token = UtilidadServicio.GenerarToken();
                usuario.Restablecer = false;
                usuario.Confirmado = false;

                // Registra al usuario
                bool respuesta = DBUsuario.Registrar(usuario);

                if (respuesta)
                {
                    // Envía un correo de confirmación
                    string path = HttpContext.Server.MapPath("~/Plantilla/Confirmar.html");
                    string content = System.IO.File.ReadAllText(path);
                    string url = string.Format("{0}://{1}{2}", Request.Url.Scheme, Request.Headers["host"], "/Inicio/Confirmar?token=" + usuario.Token);

                    string htmlBody = string.Format(content, usuario.Nombre, usuario.Apellido, url);

                    CorreoDTO correoDTO = new CorreoDTO()
                    {
                        Para = usuario.Correo,
                        Asunto = "Correo confirmación",
                        Contenido = htmlBody
                    };

                    bool enviado = CorreoServicio.Enviar(correoDTO);
                    ViewBag.Creado = true;
                    ViewBag.Mensaje = $"Su cuenta ha sido creada. Hemos enviado un mensaje al correo {usuario.Correo} para confirmar su cuenta";
                }
                else
                {
                    ViewBag.Mensaje = "No se pudo crear su cuenta";
                }

            }
            else
            {
                ViewBag.Mensaje = "El correo ya se encuentra registrado";
            }
            return View();
        }

        public ActionResult Confirmar(string token)
        {
            ViewBag.Respuesta = DBUsuario.Confirmar(token); // Confirma la cuenta usando el token
            return View();
        }

        public ActionResult Restablecer()
        {
            return View();
        }
        
        [HttpPost]        
        public ActionResult Restablecer(string correo)
        {
            UsuarioDTO usuario = DBUsuario.Obtener(correo);
            ViewBag.Correo = correo;
            if (usuario != null)
            {
                // Genera un token para restablecer la contraseña
                bool respuesta = DBUsuario.RestablecerActualizar(1, usuario.Clave, usuario.Token);
                if (respuesta)
                {
                    // Envía un correo para restablecer la contraseña
                    string path = HttpContext.Server.MapPath("~/Plantilla/Restablecer.html");
                    string content = System.IO.File.ReadAllText(path);
                    string url = string.Format("{0}://{1}{2}", Request.Url.Scheme, Request.Headers["host"], "/Inicio/Actualizar?token=" + usuario.Token);

                    string htmlBody = string.Format(content, usuario.Nombre, usuario.Apellido, url);

                    CorreoDTO correoDTO = new CorreoDTO()
                    {
                        Para = correo,
                        Asunto = "Restablecer cuenta de Seguranet",
                        Contenido = htmlBody
                    };

                    bool enviado = CorreoServicio.Enviar(correoDTO);
                    ViewBag.Restablecido = true;
                }
                else
                {
                    ViewBag.Mensaje = "No se pudo restablecer su cuenta de Seguranet";
                }
            }
            else
            {
                ViewBag.Mensaje = "No se encontraron coincidencias con el correo";
            }
            return View();
        }

        public ActionResult Actualizar(string token)
        {
            ViewBag.Token = token; // Pasa el token a la vista para la actualización
            return View();
        }

        [HttpPost]
        public ActionResult Actualizar(string token, string clave, string confirmarClave)
        {
            ViewBag.Token = token;
            if (clave != confirmarClave)
            {
                ViewBag.Mensaje = "Las contraseñas no coinciden";
                return View();
            }

            // Actualiza la contraseña usando el token
            bool respuesta = DBUsuario.RestablecerActualizar(0, UtilidadServicio.ConvertirSHA256(clave), token);

            if (respuesta)
            {
                ViewBag.Restablecido = true;
            }
            else
            {
                ViewBag.Mensaje = "No se pudo actualizar";
            }
            return View();
        }
    }
}