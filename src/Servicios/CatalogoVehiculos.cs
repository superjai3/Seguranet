using System.Text.Json;
using System.Xml.Linq;
using Microsoft.Extensions.Caching.Distributed;

namespace Seguranet.Servicios;

public interface ICatalogoVehiculos
{
    Task<string[]> MarcasAsync(int anio, CancellationToken ct = default);
    Task<string[]> ModelosAsync(int anio, string marca, CancellationToken ct = default);
    Task<string[]> VersionesAsync(int anio, string marca, string modelo, CancellationToken ct = default);
}

/// <summary>
/// Marcas, modelos y versiones de vehículos, de fueleconomy.gov.
///
/// **Por qué una sola fuente y no tres.** El cotizador original mezclaba
/// MercadoLibre para las marcas, vPIC para los modelos y fueleconomy para las
/// versiones. Esa cadena no cierra: vPIC llama «Escape» a lo que fueleconomy
/// llama «Escape AWD» y «Escape FWD», así que el modelo que devuelve el segundo
/// paso no existe para el tercero y el desplegable de versiones queda siempre
/// vacío. Comprobado contra las dos APIs.
///
/// fueleconomy.gov, del Departamento de Energía de los Estados Unidos, tiene los
/// tres niveles y son consistentes entre sí:
///
///     menu/make?year=            -> marcas de ese año
///     menu/model?year=&amp;make=      -> modelos de esa marca
///     menu/options?year=&amp;make=&amp;model= -> versiones
///
/// Es pública, gratuita, no pide clave y devuelve XML.
///
/// **Limitación conocida, y hay que decirla:** es un catálogo de vehículos
/// vendidos en Estados Unidos. Para un cotizador argentino o español va a faltar
/// buena parte del parque —no están los modelos que sólo se vendieron acá— y van
/// a sobrar otros. Sirve para que el cotizador funcione de punta a punta; para
/// producción de verdad hace falta un catálogo del mercado que corresponda. Está
/// anotado en AUDITORIA.md.
///
/// Los endpoints que usaba el servicio de .NET —`api.mercadolibre.com/vehicles/
/// years`, `/brands`, `/models`, `/versions`— **ya no existen**: devuelven 404,
/// comprobado. MercadoLibre los retiró, así que no había clave que los arreglara.
/// </summary>
public class CatalogoVehiculos : ICatalogoVehiculos
{
    private const string Base = "https://www.fueleconomy.gov/ws/rest/vehicle/menu";

    private static readonly DistributedCacheEntryOptions Duracion = new()
    {
        AbsoluteExpirationRelativeToNow = TimeSpan.FromHours(24),
    };

    private readonly HttpClient _http;
    private readonly IDistributedCache _cache;
    private readonly ILogger<CatalogoVehiculos> _registro;

    public CatalogoVehiculos(
        IHttpClientFactory fabrica, IDistributedCache cache, ILogger<CatalogoVehiculos> registro)
    {
        _http = fabrica.CreateClient();
        _http.Timeout = TimeSpan.FromSeconds(12);
        _cache = cache;
        _registro = registro;
    }

    public Task<string[]> MarcasAsync(int anio, CancellationToken ct = default) =>
        ConCacheAsync($"cat:marcas:{anio}", $"{Base}/make?year={anio}", ct);

    public Task<string[]> ModelosAsync(int anio, string marca, CancellationToken ct = default) =>
        ConCacheAsync($"cat:modelos:{anio}:{marca.ToLowerInvariant()}",
            $"{Base}/model?year={anio}&make={Uri.EscapeDataString(marca)}", ct);

    public Task<string[]> VersionesAsync(int anio, string marca, string modelo, CancellationToken ct = default) =>
        ConCacheAsync($"cat:versiones:{anio}:{marca.ToLowerInvariant()}:{modelo.ToLowerInvariant()}",
            $"{Base}/options?year={anio}&make={Uri.EscapeDataString(marca)}&model={Uri.EscapeDataString(modelo)}",
            ct);

    // ------------------------------------------------------------------ común

    private async Task<string[]> ConCacheAsync(string clave, string url, CancellationToken ct)
    {
        try
        {
            var guardado = await _cache.GetStringAsync(clave, ct);
            if (guardado is not null)
            {
                return JsonSerializer.Deserialize<string[]>(guardado) ?? [];
            }
        }
        catch (Exception e)
        {
            // La caché es una mejora, no un requisito: si Redis está caído se
            // sigue sin ella en vez de fallar.
            _registro.LogWarning(e, "No se pudo leer la caché para {Clave}.", clave);
        }

        var valores = await TraerAsync(url, ct);

        // Una lista vacía no se guarda: casi siempre significa que la fuente
        // falló, y cachear el fallo lo vuelve permanente por 24 horas.
        if (valores.Length == 0)
        {
            return valores;
        }

        try
        {
            await _cache.SetStringAsync(clave, JsonSerializer.Serialize(valores), Duracion, ct);
        }
        catch (Exception e)
        {
            _registro.LogWarning(e, "No se pudo guardar la caché {Clave}.", clave);
        }

        return valores;
    }

    /// <summary>
    /// Pide y lee la respuesta. Los tres niveles devuelven la misma forma:
    /// &lt;menuItems&gt; con &lt;menuItem&gt;&lt;text&gt; adentro.
    /// </summary>
    private async Task<string[]> TraerAsync(string url, CancellationToken ct)
    {
        try
        {
            var respuesta = await _http.GetAsync(url, ct);
            respuesta.EnsureSuccessStatusCode();

            var xml = await respuesta.Content.ReadAsStringAsync(ct);
            if (string.IsNullOrWhiteSpace(xml))
            {
                return [];
            }

            return XDocument.Parse(xml)
                .Descendants("menuItem")
                .Select(m => m.Element("text")?.Value)
                .Where(t => !string.IsNullOrWhiteSpace(t))
                .Select(t => t!)
                .Distinct()
                .ToArray();
        }
        catch (Exception e)
        {
            // Se anota y se devuelve vacío. El desplegable queda vacío —que es
            // visible y se entiende— en vez de tirar abajo el cotizador entero
            // porque un servicio ajeno no contestó.
            _registro.LogError(e, "Falló la consulta a {Url}", url);
            return [];
        }
    }
}
