using System;
using Seguranet.Models;

namespace Seguranet.Datos
{
    public class DBConsulta
    {
        // Método para guardar una consulta en la base de datos
        public static bool Guardar(ConsultaDTO consulta)
        {
            using (var context = new SeguranetContext()) // Usa SeguranetContext
            {
                var nuevaConsulta = new CONSULTAS_CONTACTO
                {
                    Nombre = consulta.Nombre,
                    Apellido = consulta.Apellido,
                    Correo = consulta.Correo,
                    Telefono = consulta.Telefono,
                    Motivo = consulta.Motivo,
                    Mensaje = consulta.Mensaje,
                    FechaConsulta = DateTime.Now,
                    Estado = "Pendiente"
                };

                context.CONSULTAS_CONTACTO.Add(nuevaConsulta);
                return context.SaveChanges() > 0; // Devuelve true si la consulta se guardó correctamente
            }
        }
    }
}
