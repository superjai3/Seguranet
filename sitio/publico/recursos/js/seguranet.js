/* Seguranet — lo mínimo. El sitio funciona sin JavaScript; esto sólo mejora. */
(function () {
    "use strict";

    // Menú en pantallas chicas.
    var boton = document.querySelector("[data-menu]");
    var menu = document.getElementById("menu-principal");
    if (boton && menu) {
        boton.addEventListener("click", function () {
            var abierto = menu.getAttribute("data-abierto") === "si";
            menu.setAttribute("data-abierto", abierto ? "no" : "si");
            boton.setAttribute("aria-expanded", abierto ? "false" : "true");
        });
    }
})();
