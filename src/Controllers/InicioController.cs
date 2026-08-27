using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Options;
using Seguranet.Datos;
using Seguranet.Models;
using Seguranet.Servicios;

namespace Seguranet.Controllers;

/// <summary>
/// Cuentas: entrar, salir, darse de alta, confirmar y restablecer la clave.
/// </summary>
public class InicioController : Controller
{
    private readonly DBUsuario _usuarios;
    private readonly CorreoServicio _correo;
    private readonly OpcionesSitio _sitio;
    private readonly IWebHostEnvironment _entorno;

    public InicioController(
        DBUsuario usuarios,
        CorreoServicio correo,
        IOptions<OpcionesSitio> sitio,
        IWebHostEnvironment entorno)
    {
        _usuarios = usuarios;
        _correo = correo;
        _sitio = sitio.Value;
        _entorno = entorno;
    }

    // ---------------------------------------------------------------- entrar

    public IActionResult Login() => View();

    [HttpPost]
    [ValidateAntiForgeryToken]
    public IActionResult Login(string correo, string clave)
    {
        var usuario = _usuarios.Validar(correo, UtilidadServicio.ConvertirSHA256(clave ?? ""));

        if (usuario is null)
        {
            // El mismo mensaje para «no existe» y para «la clave está mal», a
            // propósito: distinguirlos le dice a cualquiera qué correos están
            // registrados en el sitio.
            ViewBag.Mensaje = "No se encontraron coincidencias";
            return View();
        }

        if (!usuario.Confirmado)
        {
            ViewBag.Mensaje = $"Falta confirmar su cuenta. Se le envió un correo a {correo}";
            return View();
        }

        if (usuario.Restablecer)
        {
            ViewBag.Mensaje = $"Se ha solicitado restablecer su cuenta, por favor revise su bandeja del correo {correo}";
            return View();
        }

        SesionServicio.Iniciar(HttpContext.Session, usuario);
        return RedirectToAction("Index", "Home");
    }

    /// <summary>
    /// Cierra la sesión.
    ///
    /// Por POST y con token antiforgery a propósito: si fuera un GET, bastaría
    /// con inducir al navegador a pedir /Inicio/Salir —una imagen, un enlace en
    /// otro sitio, el prefetch del propio navegador— para dejar afuera a la
    /// persona sin que lo haya pedido.
    /// </summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public IActionResult Salir()
    {
        SesionServicio.Cerrar(HttpContext.Session);
        return RedirectToAction("Index", "Home");
    }

    // ------------------------------------------------------------------ alta

    public IActionResult Registrar() => View();

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Registrar(UsuarioDTO usuario)
    {
        // Se devuelven los datos a la vista para que quien se equivocó en un
        // campo no tenga que escribir todo de nuevo.
        ViewBag.Nombre = usuario.Nombre;
        ViewBag.Apellido = usuario.Apellido;
        ViewBag.Dni = usuario.Dni;
        ViewBag.Correo = usuario.Correo;

        if (usuario.Clave != usuario.ConfirmacionClave)
        {
            ViewBag.Mensaje = "Las contraseñas no coinciden";
            return View();
        }

        if (_usuarios.Obtener(usuario.Correo) is not null)
        {
            ViewBag.Mensaje = "El correo ya se encuentra registrado";
            return View();
        }

        usuario.Clave = UtilidadServicio.ConvertirSHA256(usuario.Clave);
        usuario.Token = UtilidadServicio.GenerarToken();
        usuario.Restablecer = false;
        usuario.Confirmado = false;

        if (!_usuarios.Registrar(usuario))
        {
            // Puede ser la restricción única de correo, si dos altas llegaron a
            // la vez. El mensaje sirve para las dos.
            ViewBag.Mensaje = "No se pudo crear su cuenta";
            return View();
        }

        await EnviarPlantillaAsync(
            plantilla: "Confirmar.html",
            para: usuario.Correo,
            asunto: "Confirmá tu cuenta de Seguranet",
            nombre: usuario.Nombre,
            apellido: usuario.Apellido,
            url: Url.Action(nameof(Confirmar), "Inicio", new { token = usuario.Token })!);

        ViewBag.Creado = true;
        ViewBag.Mensaje = $"Su cuenta ha sido creada. Le enviamos un mensaje a {usuario.Correo} para confirmarla.";
        return View();
    }

    public IActionResult Confirmar(string? token)
    {
        ViewBag.Respuesta = !string.IsNullOrWhiteSpace(token) && _usuarios.Confirmar(token);
        return View();
    }

    // --------------------------------------------------------- restablecer

    public IActionResult Restablecer() => View();

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Restablecer(string correo)
    {
        ViewBag.Correo = correo;

        var usuario = _usuarios.Obtener(correo);

        // Se responde lo mismo exista o no la cuenta. Decir «no encontramos ese
        // correo» convierte este formulario en una forma cómoda de averiguar
        // quién está registrado en el sitio.
        ViewBag.Restablecido = true;

        if (usuario is null)
        {
            return View();
        }

        // Token NUEVO en cada pedido. El proyecto viejo reutilizaba el que ya
        // tuviera el usuario, que después de confirmar la cuenta quedaba vacío:
        // el enlace salía con token en blanco y servía para cualquiera.
        var token = UtilidadServicio.GenerarToken();

        if (!_usuarios.MarcarRestablecer(correo, token))
        {
            return View();
        }

        await EnviarPlantillaAsync(
            plantilla: "Restablecer.html",
            para: correo,
            asunto: "Restablecer tu cuenta de Seguranet",
            nombre: usuario.Nombre,
            apellido: usuario.Apellido,
            url: Url.Action(nameof(Actualizar), "Inicio", new { token })!);

        return View();
    }

    public IActionResult Actualizar(string? token)
    {
        ViewBag.Token = token;
        return View();
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public IActionResult Actualizar(string token, string clave, string confirmarClave)
    {
        ViewBag.Token = token;

        if (clave != confirmarClave)
        {
            ViewBag.Mensaje = "Las contraseñas no coinciden";
            return View();
        }

        if (string.IsNullOrWhiteSpace(token))
        {
            ViewBag.Mensaje = "El enlace no es válido. Pedí uno nuevo desde «Restablecer».";
            return View();
        }

        if (!_usuarios.RestablecerClave(token, UtilidadServicio.ConvertirSHA256(clave)))
        {
            // El token no existe, ya se usó o la cuenta no tenía pedido de
            // restablecimiento. Un enlace sirve una sola vez.
            ViewBag.Mensaje = "El enlace ya se usó o venció. Pedí uno nuevo desde «Restablecer».";
            return View();
        }

        ViewBag.Restablecido = true;
        return View();
    }

    // ----------------------------------------------------------------- común

    /// <summary>
    /// Arma un correo a partir de una de las plantillas de <c>Plantilla/</c> y
    /// lo manda.
    ///
    /// La URL se construye absoluta y con el dominio configurado, no con el
    /// host del pedido: si alguien entrara por la IP, el enlace del correo
    /// llevaría a la IP —sin certificado— en vez de a seguranet.es.
    /// </summary>
    private async Task EnviarPlantillaAsync(
        string plantilla, string para, string asunto, string nombre, string apellido, string url)
    {
        var ruta = Path.Combine(_entorno.ContentRootPath, "Plantilla", plantilla);
        if (!System.IO.File.Exists(ruta))
        {
            return;
        }

        var contenido = await System.IO.File.ReadAllTextAsync(ruta);
        var absoluta = _sitio.UrlBase(Request) + url;

        await _correo.EnviarAsync(new CorreoDTO
        {
            Para = para,
            Asunto = asunto,
            Contenido = string.Format(contenido, nombre, apellido, absoluta),
        });
    }
}
