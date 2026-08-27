using Microsoft.AspNetCore.Mvc;
using Seguranet.Servicios;

namespace Seguranet.Controllers;

/// <summary>
/// La API que consume el cotizador.
///
/// Reemplaza a los cuatro controladores del proyecto SeguranetAPI —
/// MercadoLibre, Cotizacion, Auth y Session—. Se unifican acá porque los tres
/// últimos tenían entre veinte y cuarenta líneas y ninguno hacía nada que no
/// fuera consultar o devolver lo que ya sabía.
///
/// Al vivir en el mismo proceso que el sitio, **desaparece el CORS**: el front
/// del cotizador y esta API pasan a estar en el mismo origen, así que la
/// política de `AllowAnyOrigin` que traía el proyecto viejo —que dejaba llamar
/// desde cualquier página de internet— deja de hacer falta.
/// </summary>
[ApiController]
[Route("api")]
public class CotizadorApiController : ControllerBase
{
    private readonly IMercadoLibreService _ml;

    public CotizadorApiController(IMercadoLibreService ml) => _ml = ml;

    /// <summary>Años disponibles para cotizar.</summary>
    [HttpGet("anios")]
    public async Task<ActionResult<string[]>> Anios(CancellationToken ct)
        => Ok(await _ml.AniosAsync(ct));

    /// <summary>Marcas de un año.</summary>
    [HttpGet("marcas/{anio:int}")]
    public async Task<ActionResult<string[]>> Marcas(int anio, CancellationToken ct)
    {
        // El rango existe para que un pedido con año 12345 no se convierta en
        // una llamada a MercadoLibre que se sabe de antemano que no sirve.
        if (anio < 1950 || anio > DateTime.UtcNow.Year + 1)
        {
            return BadRequest(new { error = "Año fuera de rango." });
        }

        return Ok(await _ml.MarcasAsync(anio, ct));
    }

    /// <summary>Modelos de una marca.</summary>
    [HttpGet("modelos/{marca}")]
    public async Task<ActionResult<string[]>> Modelos(string marca, CancellationToken ct)
        => string.IsNullOrWhiteSpace(marca)
            ? BadRequest(new { error = "Falta la marca." })
            : Ok(await _ml.ModelosAsync(marca, ct));

    /// <summary>Versiones de un modelo.</summary>
    [HttpGet("versiones/{modelo}")]
    public async Task<ActionResult<string[]>> Versiones(string modelo, CancellationToken ct)
        => string.IsNullOrWhiteSpace(modelo)
            ? BadRequest(new { error = "Falta el modelo." })
            : Ok(await _ml.VersionesAsync(modelo, ct));

    /// <summary>
    /// Para comprobar que la API está viva sin depender de MercadoLibre.
    ///
    /// Sirve para separar dos fallas que desde fuera se ven igual: que el sitio
    /// esté caído, o que el servicio externo no conteste.
    /// </summary>
    [HttpGet("estado")]
    public IActionResult Estado() => Ok(new { estado = "ok", hora = DateTime.UtcNow });
}
