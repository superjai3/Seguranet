using System.Text.Json;
using Microsoft.Extensions.Caching.Distributed;

namespace Seguranet.Servicios;

public interface IMercadoLibreService
{
    Task<string[]> AniosAsync(CancellationToken ct = default);
    Task<string[]> MarcasAsync(int anio, CancellationToken ct = default);
    Task<string[]> ModelosAsync(string marca, CancellationToken ct = default);
    Task<string[]> VersionesAsync(string modelo, CancellationToken ct = default);
}

/// <summary>
/// Años, marcas, modelos y versiones de vehículos, desde la API de MercadoLibre.
///
/// **Acá está el cambio de fondo del port.** El proyecto viejo registraba Redis
/// en <c>Program.cs</c> y no lo usaba nadie: cero referencias a
/// <c>IDistributedCache</c> en todo el código. Cada visitante que abría el
/// desplegable de marcas disparaba una llamada a MercadoLibre, aunque el de al
/// lado hubiera pedido lo mismo un segundo antes.
///
/// Ahora sí cachea. Son listas que cambian una vez al año, así que repetir la
/// llamada es gastar cuota —MercadoLibre limita por aplicación— y hacer esperar
/// al visitante por algo que ya se sabía.
///
/// La caché es <see cref="IDistributedCache"/>, que según haya Redis
/// configurado o no usa Redis o la memoria del proceso. El servicio no se entera
/// ni tiene por qué.
/// </summary>
public class MercadoLibreService : IMercadoLibreService
{
    // Un día. Los modelos de autos no cambian más seguido, y si algo se agrega
    // a mitad de año, esperar hasta mañana no le arruina el día a nadie.
    private static readonly DistributedCacheEntryOptions Duracion = new()
    {
        AbsoluteExpirationRelativeToNow = TimeSpan.FromHours(24),
    };

    private readonly HttpClient _http;
    private readonly IDistributedCache _cache;
    private readonly ILogger<MercadoLibreService> _registro;

    public MercadoLibreService(
        IHttpClientFactory fabrica, IDistributedCache cache, ILogger<MercadoLibreService> registro)
    {
        _http = fabrica.CreateClient();
        _http.Timeout = TimeSpan.FromSeconds(10);
        _cache = cache;
        _registro = registro;
    }

    public Task<string[]> AniosAsync(CancellationToken ct = default) =>
        ConCacheAsync("ml:anios", "https://api.mercadolibre.com/vehicles/years", ct);

    public Task<string[]> MarcasAsync(int anio, CancellationToken ct = default) =>
        ConCacheAsync($"ml:marcas:{anio}",
            $"https://api.mercadolibre.com/vehicles/brands?year={anio}", ct);

    public Task<string[]> ModelosAsync(string marca, CancellationToken ct = default) =>
        ConCacheAsync($"ml:modelos:{marca}",
            $"https://api.mercadolibre.com/vehicles/models?brand={Uri.EscapeDataString(marca)}", ct);

    public Task<string[]> VersionesAsync(string modelo, CancellationToken ct = default) =>
        ConCacheAsync($"ml:versiones:{modelo}",
            $"https://api.mercadolibre.com/vehicles/versions?model={Uri.EscapeDataString(modelo)}", ct);

    /// <summary>
    /// Busca en la caché; si no está, pregunta a MercadoLibre y guarda.
    ///
    /// Si la llamada falla se devuelve una lista vacía y se anota en el
    /// registro. El desplegable queda vacío —que es visible y se entiende— en
    /// vez de tirar abajo la página entera del cotizador por un servicio ajeno
    /// que no respondió.
    /// </summary>
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
            // Si Redis está caído, se sigue sin caché en vez de fallar: la
            // caché es una mejora, no un requisito.
            _registro.LogWarning(e, "No se pudo leer la caché para {Clave}.", clave);
        }

        string[] valores;
        try
        {
            var respuesta = await _http.GetAsync(url, ct);
            respuesta.EnsureSuccessStatusCode();

            var json = await respuesta.Content.ReadAsStringAsync(ct);
            valores = Extraer(json);
        }
        catch (Exception e)
        {
            _registro.LogError(e, "Falló la consulta a MercadoLibre: {Url}", url);
            return [];
        }

        try
        {
            await _cache.SetStringAsync(clave, JsonSerializer.Serialize(valores), Duracion, ct);
        }
        catch (Exception e)
        {
            _registro.LogWarning(e, "No se pudo guardar en la caché {Clave}.", clave);
        }

        return valores;
    }

    /// <summary>
    /// MercadoLibre devuelve listas de objetos con <c>id</c> y <c>name</c>. Al
    /// cotizador sólo le interesa el nombre.
    /// </summary>
    private static string[] Extraer(string json)
    {
        using var documento = JsonDocument.Parse(json);

        if (documento.RootElement.ValueKind != JsonValueKind.Array)
        {
            return [];
        }

        return documento.RootElement
            .EnumerateArray()
            .Select(e => e.TryGetProperty("name", out var nombre)
                ? nombre.GetString()
                : e.ValueKind == JsonValueKind.String ? e.GetString() : null)
            .Where(n => !string.IsNullOrWhiteSpace(n))
            .Select(n => n!)
            .ToArray();
    }
}
