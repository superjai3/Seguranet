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

/* Localidades por provincia.
   Se le piden a este mismo sitio, que las trae de georef y las cachea. Si no
   hay JavaScript o georef no responde, el campo sigue siendo un texto libre y
   el formulario se envía igual: la lista es una comodidad, no un requisito. */
(function () {
    "use strict";

    var provincia = document.querySelector("[data-provincia]");
    var localidad = document.querySelector("[data-localidad]");
    var lista = document.getElementById("lista-localidades");
    var ayuda = document.querySelector("[data-localidad-ayuda]");
    if (!provincia || !localidad || !lista) { return; }

    function avisar(texto) {
        if (ayuda) { ayuda.textContent = texto; }
    }

    function cargar() {
        var id = provincia.value;
        lista.innerHTML = "";
        if (!id) {
            localidad.placeholder = "Elegí la provincia primero";
            avisar("Las localidades salen del servicio oficial de datos geográficos del Estado.");
            return;
        }

        localidad.placeholder = "Buscando localidades…";
        fetch("/api/localidades?provincia=" + encodeURIComponent(id))
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (datos) {
                if (!datos.disponible || !datos.localidades.length) {
                    localidad.placeholder = "Escribí tu localidad";
                    avisar("No pudimos traer la lista en este momento: escribí la localidad a mano.");
                    return;
                }
                var fragmento = document.createDocumentFragment();
                datos.localidades.forEach(function (nombre) {
                    var opcion = document.createElement("option");
                    opcion.value = nombre;
                    fragmento.appendChild(opcion);
                });
                lista.appendChild(fragmento);
                localidad.placeholder = "Empezá a escribir y elegí de la lista";
                avisar(datos.localidades.length + " localidades disponibles.");
            })
            .catch(function () {
                localidad.placeholder = "Escribí tu localidad";
                avisar("No pudimos traer la lista en este momento: escribí la localidad a mano.");
            });
    }

    provincia.addEventListener("change", cargar);
    if (provincia.value) { cargar(); }
})();
