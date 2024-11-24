public class CotizadorAutoModel : PageModel
{
    [BindProperty]
    public Cotizacion Cotizacion { get; set; } // Modelo de cotización

    public void OnPost()
    {
        return Redirect("http://127.0.0.1:5500/CotizadorSeguros/html/index.html"); // Cambia el puerto al que estés usando
    }
}



