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

/* Modelos por marca. El catálogo viene en la página, así que esto no toca la
   red: cambiar de marca repuebla la lista en el acto. Sin JavaScript, el campo
   sigue siendo texto libre y el formulario se envía igual. */
(function () {
    "use strict";

    var marca = document.querySelector("[data-marca]");
    var modelo = document.querySelector("[data-modelo]");
    var lista = document.getElementById("lista-modelos");
    var ayuda = document.querySelector("[data-modelo-ayuda]");
    if (!marca || !modelo || !lista || !window.SN_CATALOGO) { return; }

    function cargar(conservar) {
        var modelos = window.SN_CATALOGO[marca.value] || [];
        lista.innerHTML = "";
        if (!conservar) { modelo.value = ""; }

        if (!marca.value) {
            modelo.placeholder = "Elegí la marca primero";
            return;
        }
        var fragmento = document.createDocumentFragment();
        modelos.forEach(function (nombre) {
            var opcion = document.createElement("option");
            opcion.value = nombre;
            fragmento.appendChild(opcion);
        });
        lista.appendChild(fragmento);
        modelo.placeholder = "Empezá a escribir y elegí de la lista";
        if (ayuda) {
            ayuda.textContent = modelos.length
                ? modelos.length + " modelos de " + marca.value + ". Si el tuyo no está, escribilo igual."
                : "No tenemos modelos cargados de esa marca: escribilo a mano.";
        }
    }

    marca.addEventListener("change", function () { cargar(false); });
    // Al volver de un error de validación, el modelo ya elegido no se pierde.
    if (marca.value) { cargar(true); }
})();

/* Cotizador de hogar: mostrar sólo lo que corresponde.
   Sin JavaScript el formulario funciona igual —los campos se muestran u
   ocultan también desde PHP al volver de una validación— pero así el cambio se
   ve en el momento y nadie completa un campo que no le van a cobrar. */
(function () {
    "use strict";

    var condicion = document.querySelector("[data-condicion]");
    var tipo = document.querySelector("[data-tipo]");
    var campoEdificio = document.querySelector("[data-campo-edificio]");
    var campoPlantaBaja = document.querySelector("[data-planta-baja]");

    function mostrar(elemento, visible) {
        if (elemento) { elemento.hidden = !visible; }
    }

    if (condicion && campoEdificio) {
        condicion.addEventListener("change", function () {
            // El inquilino no asegura el edificio: no es suyo.
            mostrar(campoEdificio, condicion.value !== "inquilino");
        });
    }

    if (tipo && campoPlantaBaja) {
        tipo.addEventListener("change", function () {
            // "Planta baja" sólo tiene sentido en un departamento.
            var esDepartamento = tipo.value === "departamento";
            mostrar(campoPlantaBaja, esDepartamento);
            if (!esDepartamento) {
                var casilla = campoPlantaBaja.querySelector("input");
                if (casilla) { casilla.checked = false; }
            }
        });
    }
})();
