using System.Data.Entity; // Asegúrate de tener referencia a EntityFramework
using Seguranet.Models; // Importa tu espacio de nombres donde están los modelos

namespace Seguranet.Datos // El espacio de nombres debe coincidir con la estructura de tu proyecto
{
    public class SeguranetContext : DbContext
    {
        // Constructor que recibe el nombre de la conexión
        public SeguranetContext() : base("name=DBSeguranetContext") // Usa el nombre de la cadena de conexión existente
        {
        }

        // Propiedad que representa la tabla CONSULTAS_CONTACTO
        public DbSet<CONSULTAS_CONTACTO> CONSULTAS_CONTACTO { get; set; }

        // Si tienes otras tablas, puedes añadir más DbSet aquí
        // public DbSet<OtraEntidad> OtraEntidad { get; set; }
    }
}
