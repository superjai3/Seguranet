namespace Seguranet.Servicios;

/// <summary>
/// Datos de la casilla desde la que salen los correos de confirmación y de
/// restablecimiento de clave.
///
/// Existe porque en el proyecto viejo estaban escritos dentro de
/// <c>CorreoServicio.cs</c>: la casilla y la contraseña de aplicación de Gmail,
/// en texto plano, en un repositorio público. Cualquiera podía leerlas y mandar
/// correo haciéndose pasar por Seguranet.
///
/// Nacen vacías a propósito. Sin credenciales el sitio funciona igual —las
/// consultas se guardan en la base— pero no manda correo, y lo dice en el
/// registro en vez de reventar.
/// </summary>
public class OpcionesCorreo
{
    public const string Seccion = "Correo";

    public string Servidor { get; set; } = "smtp.gmail.com";
    public int Puerto { get; set; } = 587;
    public string Usuario { get; set; } = "";
    public string Clave { get; set; } = "";
    public string NombreEnvia { get; set; } = "Seguranet";

    public bool Habilitado =>
        !string.IsNullOrWhiteSpace(Usuario) && !string.IsNullOrWhiteSpace(Clave);
}

/// <summary>
/// Credenciales de la API de MercadoLibre, de donde salen los años, marcas,
/// modelos y versiones de vehículos que ofrece el cotizador.
///
/// El <see cref="RedirectUri"/> tiene que coincidir con el que esté dado de alta
/// en la aplicación de MercadoLibre. Al pasar a seguranet.es hay que cambiarlo
/// en los dos lados o el intercambio de token falla sin decir por qué.
/// </summary>
public class OpcionesMercadoLibre
{
    public const string Seccion = "MercadoLibre";

    public string ClientId { get; set; } = "";
    public string ClientSecret { get; set; } = "";
    public string RedirectUri { get; set; } = "";

    public bool Habilitado =>
        !string.IsNullOrWhiteSpace(ClientId) && !string.IsNullOrWhiteSpace(ClientSecret);
}

/// <summary>
/// Cómo se publica el sitio. El dominio vive acá y no escrito dentro del código:
/// lo usan las URL canónicas, las de Open Graph y el sitemap, así que un cambio
/// de dominio es una línea de configuración y no una búsqueda por el proyecto.
/// </summary>
public class OpcionesSitio
{
    public const string Seccion = "Sitio";

    /// <summary>Sin esquema ni barra final: "seguranet.es".</summary>
    public string Dominio { get; set; } = "";

    public bool HayDominio => !string.IsNullOrWhiteSpace(Dominio);

    /// <summary>
    /// URL absoluta del sitio. Con el dominio configurado se usa siempre ese,
    /// aunque el visitante haya entrado por la IP: si no, los buscadores verían
    /// la misma página en dos direcciones y repartirían el posicionamiento.
    /// </summary>
    public string UrlBase(HttpRequest pedido) => HayDominio
        ? $"https://{Dominio.Trim().TrimEnd('/')}"
        : $"{pedido.Scheme}://{pedido.Host}";
}
