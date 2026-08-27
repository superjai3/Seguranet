using Microsoft.AspNetCore.HttpOverrides;
using Seguranet.Datos;
using Seguranet.Servicios;

var builder = WebApplication.CreateBuilder(args);

// ---------------------------------------------------------------------------
// Configuración
//
// Nada de credenciales en el código. El proyecto viejo tenía la casilla y la
// contraseña de aplicación de Gmail escritas dentro de CorreoServicio.cs, en un
// repositorio público: cualquiera podía mandar correo haciéndose pasar por
// Seguranet. Ahora salen de la configuración, y en el servidor de un archivo de
// entorno que no está en el repositorio.
// ---------------------------------------------------------------------------
builder.Services.Configure<OpcionesCorreo>(builder.Configuration.GetSection(OpcionesCorreo.Seccion));
builder.Services.Configure<OpcionesMercadoLibre>(builder.Configuration.GetSection(OpcionesMercadoLibre.Seccion));
builder.Services.Configure<OpcionesSitio>(builder.Configuration.GetSection(OpcionesSitio.Seccion));

// La base. SQL Server pedía un servidor aparte —y en Linux, contenedor—; para
// seis tablas y veinte filas SQLite hace lo mismo con un archivo.
var rutaBase = builder.Configuration.GetConnectionString("Seguranet")
               ?? "Data Source=seguranet.db";
builder.Services.AddSingleton(new FabricaConexion(rutaBase));
builder.Services.AddScoped<DBUsuario>();
builder.Services.AddScoped<DBConsulta>();

builder.Services.AddSingleton<CorreoServicio>();
builder.Services.AddHttpClient();
builder.Services.AddTransient<IMercadoLibreService, MercadoLibreService>();

// ---------------------------------------------------------------------------
// Caché
//
// La API consulta años, marcas, modelos y versiones a MercadoLibre. Son datos
// que cambian una vez al año, así que repetir la llamada en cada visita es
// gastar cuota y hacer esperar al visitante.
//
// Redis si está configurado; si no, memoria del propio proceso. Sin esto, un
// servidor sin Redis no arrancaba: el proyecto viejo apuntaba a
// "localhost:6379" escrito a mano y daba por hecho que estaba ahí.
// ---------------------------------------------------------------------------
var redis = builder.Configuration.GetConnectionString("Redis");
if (!string.IsNullOrWhiteSpace(redis))
{
    builder.Services.AddStackExchangeRedisCache(o =>
    {
        o.Configuration = redis;
        o.InstanceName = "seguranet:";
    });
}
else
{
    builder.Services.AddDistributedMemoryCache();
}

builder.Services.AddSession(o =>
{
    o.IdleTimeout = TimeSpan.FromMinutes(30);
    o.Cookie.HttpOnly = true;
    o.Cookie.IsEssential = true;
    o.Cookie.Name = "seguranet.sesion";
    o.Cookie.SameSite = SameSiteMode.Lax;
    o.Cookie.SecurePolicy = CookieSecurePolicy.SameAsRequest;
});

builder.Services.AddControllersWithViews();
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// nginx termina el TLS y pasa el pedido por HTTP. Sin esto la aplicación cree
// que todo llega en claro y arma las URL absolutas con http://.
builder.Services.Configure<ForwardedHeadersOptions>(o =>
{
    o.ForwardedHeaders = ForwardedHeaders.XForwardedFor | ForwardedHeaders.XForwardedProto;
    o.KnownNetworks.Clear();
    o.KnownProxies.Clear();
});

var app = builder.Build();

app.UseForwardedHeaders();

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
}

// Swagger sólo fuera de producción: el contrato de la API no tiene por qué
// quedar publicado en el sitio de cara al público.
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

// Sirve index.html cuando se pide una carpeta. Hace falta para /cotizador/,
// que es el front estático: sin esto la dirección da 404 y hay que escribir
// /cotizador/index.html a mano, que nadie hace.
app.UseDefaultFiles();
app.UseStaticFiles();
app.UseRouting();
app.UseSession();
app.UseAuthorization();

app.MapControllers();

// La raíz es la home pública, no la pantalla de acceso. En el proyecto viejo
// apuntaba a Inicio/Login: la dirección principal de un sitio que quiere que lo
// encuentren era un formulario de usuario y contraseña.
app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Home}/{action=Index}/{id?}");

// La base y sus tablas se crean solas la primera vez, para que publicar no
// dependa de que alguien se acuerde de correr un script a mano.
using (var alcance = app.Services.CreateScope())
{
    var fabrica = alcance.ServiceProvider.GetRequiredService<FabricaConexion>();
    Esquema.Crear(fabrica);
}

app.Run();
