using Microsoft.AspNetCore.Mvc;
using Seguranet.Servicios;

namespace Seguranet.Controllers;

/// <summary>
/// La API que consume el cotizador.
///
/// Reemplaza a los cuatro controladores del proyecto SeguranetAPI —MercadoLibre,
/// Cotizacion, Auth y Session—. Se unifican acá porque entre los cuatro no
/// llegaban a ciento veinte líneas y ninguno hacía nada que no fuera consultar.
///
/// Al vivir en el mismo proceso que el sitio **desaparece el CORS**: el front
/// del cotizador y esta API pasan a estar en el mismo origen, así que la
/// política de `AllowAnyOrigin` que traía el proyecto viejo —que dejaba llamar
/// desde cualquier página de internet— deja de hacer falta.
///
/// Y hay una razón de fondo para que el front pase por acá en vez de llamar
/// directo a las tres fuentes, que es lo que hacía: **la caché**. Llamando
/// directo, cada visitante interroga a MercadoLibre, a vPIC y a fueleconomy por
/// su cuenta. Pasando por el servidor, la primera consulta sirve para todos
/// durante 24 horas.
/// </summary>
[ApiController]
[Route("api")]
public class CotizadorApiController : ControllerBase
{
    private readonly ICatalogoVehiculos _catalogo;

    public CotizadorApiController(ICatalogoVehiculos catalogo) => _catalogo = catalogo;

    /// <summary>
    /// Años para los que se puede cotizar.
    ///
    /// Se calculan acá y no se piden a nadie: el endpoint de MercadoLibre que
    /// los devolvía ya no existe, y de todas formas «los últimos treinta años»
    /// es una lista que no hace falta ir a buscar a ningún lado.
    /// </summary>
    [HttpGet("anios")]
    public ActionResult<int[]> Anios()
    {
        var hasta = DateTime.UtcNow.Year + 1;
        return Ok(Enumerable.Range(hasta - 30, 31).Reverse().ToArray());
    }

    [HttpGet("marcas")]
    public async Task<ActionResult<string[]>> Marcas([FromQuery] int anio, CancellationToken ct)
        => AnioValido(anio)
            ? Ok(await _catalogo.MarcasAsync(anio, ct))
            : BadRequest(new { error = "Anio fuera de rango." });

    [HttpGet("modelos")]
    public async Task<ActionResult<string[]>> Modelos(
        [FromQuery] int anio, [FromQuery] string marca, CancellationToken ct)
    {
        if (!AnioValido(anio)) return BadRequest(new { error = "Anio fuera de rango." });
        if (string.IsNullOrWhiteSpace(marca)) return BadRequest(new { error = "Falta la marca." });
        return Ok(await _catalogo.ModelosAsync(anio, marca, ct));
    }

    [HttpGet("versiones")]
    public async Task<ActionResult<string[]>> Versiones(
        [FromQuery] int anio, [FromQuery] string marca, [FromQuery] string modelo, CancellationToken ct)
    {
        if (!AnioValido(anio))
        {
            return BadRequest(new { error = "Año fuera de rango." });
        }

        if (string.IsNullOrWhiteSpace(marca) || string.IsNullOrWhiteSpace(modelo))
        {
            return BadRequest(new { error = "Faltan la marca o el modelo." });
        }

        return Ok(await _catalogo.VersionesAsync(anio, marca, modelo, ct));
    }

    /// <summary>
    /// Para comprobar que la API está viva sin depender de nadie de afuera.
    ///
    /// Separa dos fallas que desde fuera se ven igual: que el sitio esté caído,
    /// o que una de las tres fuentes ajenas no conteste.
    /// </summary>
    [HttpGet("estado")]
    public IActionResult Estado() => Ok(new { estado = "ok", hora = DateTime.UtcNow });

    /// <summary>
    /// El catálogo no tiene nada anterior a 1984 ni posterior al año que viene.
    /// Filtrar acá evita convertir un pedido que se sabe inútil en una consulta
    /// a un servicio ajeno.
    /// </summary>
    private static bool AnioValido(int anio) => anio >= 1984 && anio <= DateTime.UtcNow.Year + 1;
}
