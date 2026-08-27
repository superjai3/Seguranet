using Microsoft.AspNetCore.Mvc;
using Seguranet.Datos;
using Seguranet.Models;
using Seguranet.Servicios;

namespace Seguranet.Controllers;

/// <summary>
/// Las páginas públicas: la home, las coberturas, las preguntas y el contacto.
/// </summary>
public class HomeController : Controller
{
    private readonly DBConsulta _consultas;
    private readonly CorreoServicio _correo;
    private readonly ILogger<HomeController> _registro;

    public HomeController(DBConsulta consultas, CorreoServicio correo, ILogger<HomeController> registro)
    {
        _consultas = consultas;
        _correo = correo;
        _registro = registro;
    }

    public IActionResult Index() => View();
    public IActionResult Nosotros() => View();
    public IActionResult Coberturas() => View();
    public IActionResult CotizadorAuto() => View();
    public IActionResult Preguntas() => View();
    public IActionResult Ayuda() => View();
    public IActionResult Siniestros() => View();
    public IActionResult Contacto() => View();

    /// <summary>
    /// Recibe el formulario de contacto.
    ///
    /// En el proyecto viejo esta acción no hacía nada: validaba el modelo y
    /// ponía un TempData con «gracias, nos pondremos en contacto». El mensaje
    /// no se guardaba en ningún lado ni se mandaba a ninguna casilla, así que
    /// cada consulta que llegaba por acá se perdía y el visitante se iba
    /// convencido de haber escrito.
    ///
    /// Ahora se guarda primero en la base y después se intenta el aviso por
    /// correo. Ese orden importa: si el SMTP está mal configurado o se cae, la
    /// consulta ya está a salvo. Al revés se perderían igual que antes.
    /// </summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Enviar(ContactoViewModel modelo)
    {
        if (!ModelState.IsValid)
        {
            return View("Contacto", modelo);
        }

        var consulta = new ConsultaDTO
        {
            Nombre = modelo.Nombre,
            // El formulario pide un nombre solo; la tabla espera apellido
            // aparte y no lo acepta vacío.
            Apellido = "—",
            Correo = modelo.Email,
            Telefono = modelo.Telefono,
            Motivo = modelo.TipoConsulta,
            Mensaje = modelo.Mensaje,
        };

        if (!_consultas.Guardar(consulta))
        {
            _registro.LogError("No se pudo guardar la consulta de {Correo}.", modelo.Email);
            ModelState.AddModelError("", "No pudimos registrar tu consulta. Probá de nuevo en un momento.");
            return View("Contacto", modelo);
        }

        // El correo es el aviso, no el registro: si falla, la consulta ya está
        // guardada y el visitante no tiene por qué enterarse de un problema
        // nuestro.
        await _correo.EnviarAsync(new CorreoDTO
        {
            Para = "seguranetarg@gmail.com",
            Asunto = $"Consulta de {modelo.Nombre} — {modelo.TipoConsulta}",
            Contenido = $"""
                <p><strong>{modelo.Nombre}</strong> escribió desde el sitio.</p>
                <p>Correo: {modelo.Email}<br>Teléfono: {modelo.Telefono}</p>
                <p>Motivo: {modelo.TipoConsulta}</p>
                <hr>
                <p>{modelo.Mensaje}</p>
                """,
        });

        TempData["Mensaje"] = "Gracias por tu consulta, nos pondremos en contacto pronto.";
        return RedirectToAction(nameof(Contacto));
    }

    /// <summary>Página de error. La usa UseExceptionHandler.</summary>
    [ResponseCache(NoStore = true, Location = ResponseCacheLocation.None)]
    public IActionResult Error() => View();
}
