using Microsoft.Data.Sqlite;

namespace Seguranet.Datos;

/// <summary>
/// Abre conexiones a la base.
///
/// En el proyecto viejo cada clase de acceso a datos leía la cadena con
/// <c>ConfigurationManager.ConnectionStrings[...]</c> en un campo estático. Eso
/// ataba el acceso a datos a <c>System.Web</c> —que no existe en .NET 8— y
/// además hacía imposible apuntar a otra base sin tocar el código.
///
/// Acá la cadena entra una vez, por configuración, y las clases de datos la
/// reciben inyectada.
/// </summary>
public class FabricaConexion
{
    private readonly string _cadena;

    public FabricaConexion(string cadena)
    {
        _cadena = cadena;

        // La carpeta del archivo puede no existir la primera vez: en el servidor
        // los datos viven fuera del directorio de la aplicación para que un
        // despliegue no se los lleve por delante.
        var constructor = new SqliteConnectionStringBuilder(cadena);
        var carpeta = Path.GetDirectoryName(Path.GetFullPath(constructor.DataSource));
        if (!string.IsNullOrEmpty(carpeta))
        {
            Directory.CreateDirectory(carpeta);
        }
    }

    public SqliteConnection Abrir()
    {
        var conexion = new SqliteConnection(_cadena);
        conexion.Open();
        return conexion;
    }
}
