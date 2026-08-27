using Microsoft.Data.Sqlite;

namespace Seguranet.Datos;

/// <summary>
/// Crea la base y sus tablas la primera vez que arranca la aplicación.
///
/// El esquema está traducido una a una desde el SQL Server original, con dos
/// cambios que impone SQLite y conviene conocer:
///
///  · No hay tipos de longitud fija. `varchar(50)` pasa a `TEXT`; SQLite no
///    corta por longitud, así que el límite lo pone la validación del modelo.
///  · No hay `bit`. Los booleanos van en `INTEGER` con 0 y 1, que es como los
///    lee y escribe Microsoft.Data.Sqlite.
///
/// Se ejecuta en cada arranque a propósito: `IF NOT EXISTS` lo hace inofensivo y
/// evita que publicar dependa de que alguien se acuerde de correr un script.
/// </summary>
public static class Esquema
{
    public static void Crear(FabricaConexion fabrica)
    {
        using var conexion = fabrica.Abrir();

        Ejecutar(conexion, """
            CREATE TABLE IF NOT EXISTS Usuario (
                IdUsuario   INTEGER PRIMARY KEY AUTOINCREMENT,
                Nombre      TEXT,
                Apellido    TEXT,
                Dni         TEXT,
                Correo      TEXT,
                Clave       TEXT,
                Restablecer INTEGER NOT NULL DEFAULT 0,
                Confirmado  INTEGER NOT NULL DEFAULT 0,
                Token       TEXT
            );
            """);

        // El correo identifica al usuario en el login y en el restablecimiento.
        // Sin este índice, dos altas con la misma casilla dejarían la cuenta en
        // un estado del que no se sale: el login encuentra dos y no sabe cuál.
        Ejecutar(conexion, """
            CREATE UNIQUE INDEX IF NOT EXISTS IX_Usuario_Correo ON Usuario(Correo);
            """);

        Ejecutar(conexion, """
            CREATE TABLE IF NOT EXISTS ConsultasContacto (
                IdConsulta    INTEGER PRIMARY KEY AUTOINCREMENT,
                Nombre        TEXT NOT NULL,
                Apellido      TEXT NOT NULL,
                Correo        TEXT NOT NULL,
                Telefono      TEXT,
                Motivo        TEXT NOT NULL,
                Mensaje       TEXT NOT NULL,
                FechaConsulta TEXT NOT NULL DEFAULT (datetime('now')),
                Estado        TEXT NOT NULL DEFAULT 'Nueva'
            );
            """);

        Ejecutar(conexion, """
            CREATE TABLE IF NOT EXISTS Planes (
                IdPlan        INTEGER PRIMARY KEY AUTOINCREMENT,
                NombrePlan    TEXT NOT NULL,
                Categoria     TEXT NOT NULL,
                ContenidoHTML TEXT
            );
            """);

        Ejecutar(conexion, """
            CREATE TABLE IF NOT EXISTS PreguntasContacto (
                IdPregunta        INTEGER PRIMARY KEY AUTOINCREMENT,
                NombrePregunta    TEXT NOT NULL,
                RespuestaPregunta TEXT NOT NULL
            );
            """);

        Ejecutar(conexion, """
            CREATE TABLE IF NOT EXISTS Presupuestos (
                IdPresupuesto   INTEGER PRIMARY KEY AUTOINCREMENT,
                Nombre          TEXT NOT NULL,
                Apellido        TEXT NOT NULL,
                Dni             TEXT NOT NULL,
                FechaNacimiento TEXT,
                Domicilio       TEXT,
                CodigoPostal    TEXT,
                Telefono        TEXT,
                Mail            TEXT,
                Patente         TEXT,
                Anio            INTEGER,
                Marca           TEXT,
                Modelo          TEXT,
                Version         TEXT,
                SumaAsegurada   REAL,
                TieneGNC        INTEGER NOT NULL DEFAULT 0,
                TieneRastreo    INTEGER NOT NULL DEFAULT 0,
                ClausulaAjuste  TEXT,
                UsoVehiculo     TEXT,
                TipoPlan        TEXT,
                Cobertura       TEXT,
                PrecioFinal     REAL,
                PrimaFinal      REAL,
                Contratacion    TEXT,
                MedioPago       TEXT,
                FechaAlta       TEXT NOT NULL DEFAULT (datetime('now'))
            );
            """);
    }

    private static void Ejecutar(SqliteConnection conexion, string sql)
    {
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = sql;
        cmd.ExecuteNonQuery();
    }
}
