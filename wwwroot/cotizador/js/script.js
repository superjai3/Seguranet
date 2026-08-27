// VARIABLES
document.addEventListener("DOMContentLoaded", () => {
  const primaFinal = document.getElementById("primaFinal");
  const getPriceButton = document.getElementById("getPrice");
  const isNewContainer = document.getElementById("isNewContainer");
  //onst isNewSelect = document.getElementById("isNew");
  const priceOptions = document.getElementById("priceOptions");
  const payOptions = document.getElementById("payOptions");
  const contractButton = document.getElementById("contractButton");
  const saveButton = document.getElementById("saveButton");
  const clearAll = document.getElementById("clearAll");

  ///////////////////////////////////////////////////////////////////////////////////

  // SECCIÓN DATOS DEL CLIENTE

  //Arranca el listado de Años
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("client-form");

    // Obtener los valores de los campos
    const nombre = document.getElementById("nombre").value.trim();
    const apellido = document.getElementById("apellido").value.trim();
    const dni = document.getElementById("dni").value.trim();
    const fechaNacimiento = document.getElementById("fechaNacimiento").value;
    const domicilio = document.getElementById("domicilio").value.trim();
    const codigoPostal = document.getElementById("codigoPostal").value.trim();
    const telefono = document.getElementById("telefono").value.trim();
    const mail = document.getElementById("mail").value.trim();

    // Validaciones básicas
    if (
      !nombre ||
      !apellido ||
      !dni ||
      !fechaNacimiento ||
      !domicilio ||
      !codigoPostal ||
      !telefono ||
      !mail
    ) {
      alert("Por favor, completa todos los campos.");
      return;
    }

    // Validación de edad mínima de 18 años
    if (!isAdult(fechaNacimiento)) {
      alert("Debes ser mayor de 18 años para registrarte.");
      return;
    }

    if (!validateEmail(mail)) {
      alert("Por favor, ingresa un correo electrónico válido.");
      return;
    }

    if (!validatePhone(telefono)) {
      alert("Por favor, ingresa un número de teléfono válido (solo dígitos).");
      return;
    }
  });

  // Función para validar el correo electrónico
  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
  }

  // Función para validar el teléfono (solo permite dígitos)
  function validatePhone(phone) {
    const re = /^\d+$/;
    return re.test(phone);
  }

  // Función para validar si el usuario es mayor de 18 años
  function isAdult(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    const age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < birth.getDate())
    ) {
      return age - 1 >= 18;
    }

    return age >= 18;
  }

  ///////////////////////////////////////////////////////////////////////////////////

  // SECCIÓN DATOS DEL VEHÍCULO

  const yearSelect = document.getElementById("year");
  const brandSelect = document.getElementById("brand");
  const modelSelect = document.getElementById("model");
  const versionSelect = document.getElementById("version");
  const licensePlateInput = document.getElementById("licensePlate");
  const licensePlateError = document.getElementById("licensePlateError");
  const priceDisplay = document.getElementById("priceDisplay");
  const finalPriceDisplay = document.getElementById("finalPriceDisplay");
  const sumaAseguradaInput = document.getElementById("sumaAsegurada");
  const msgNoVersions = document.getElementById("msgNoVersions");
  // SECCIÓN PATENTES
  licensePlateInput.addEventListener("input", function () {
    let licensePlate = licensePlateInput.value.toUpperCase().replace(/\s/g, "");
    licensePlateInput.value = licensePlate;

    let maxLength = 10;
    let errorMessage = "";

    if (/^E\/?T?$/.test(licensePlate)) {
      licensePlateInput.value = "E/T";
      maxLength = 3;
    }

    if (licensePlate.startsWith("101")) {
      maxLength = licensePlate.length <= 6 ? 9 : 10;
      if (
        !/^101([A-Z]{0,3}\d{0,3}|[A-Z]{0,2}\d{3}[A-Z]{0,2})$/.test(licensePlate)
      ) {
        errorMessage =
          "Formato de tráiler inválido. Utiliza '101' seguido de hasta 3 letras y 3 números.";
      }
    } else if (/^[A-Z]{2}\d{0,3}[A-Z]{0,2}$/.test(licensePlate)) {
      maxLength = 7;
    } else if (/^[A-Z]{3}\d{0,3}$/.test(licensePlate)) {
      maxLength = 6;
    } else if (/^\d{1,3}$/.test(licensePlate)) {
      if (licensePlate.startsWith("1") && licensePlate.length === 1) {
        errorMessage = "";
      } else if (licensePlate.startsWith("10") && licensePlate.length === 2) {
        errorMessage =
          "Después de '10', debes escribir '1' para el formato de tráiler.";
      } else {
        errorMessage = "El formato debe ser de tráiler o de auto.";
      }
    } else {
      if (/^[A-Z]/.test(licensePlate)) {
        errorMessage = "";
      } else {
        errorMessage =
          "Formato inválido: utiliza letras y números en el orden correcto.";
      }
    }

    if (/([A-Z]{4}|[0-9]{4})/.test(licensePlate)) {
      errorMessage = "No se permiten más de 3 letras o números consecutivos.";
    }

    if (licensePlate.length > maxLength) {
      licensePlateInput.value = licensePlate.slice(0, maxLength);
      licensePlate = licensePlateInput.value;
    }

    if (licensePlate.length === maxLength) {
      const isValid =
        /^[A-Z]{3}\d{3}$/.test(licensePlate) ||
        /^[A-Z]{2}\d{3}[A-Z]{2}$/.test(licensePlate) ||
        /^101[A-Z]{3}\d{3}$/.test(licensePlate) ||
        /^101[A-Z]{2}\d{3}[A-Z]{2}$/.test(licensePlate) ||
        /^E\/T$/.test(licensePlate);

      licensePlateError.style.display = isValid ? "none" : "block";
      licensePlateError.innerText = isValid
        ? ""
        : "Formato de patente incorrecto.";
    } else {
      licensePlateError.style.display = errorMessage ? "block" : "none";
      licensePlateError.innerText = errorMessage;
    }
  });

  // ANTIGUEDAD DEL AUTO (HASTA 50 AÑOS).
  async function loadYears() {
    yearSelect.innerHTML = '<option value="">Selecciona un año</option>';
    for (let year = 2024; year >= 1974; year--) {
      const option = document.createElement("option");
      option.value = year;
      option.textContent = year;
      yearSelect.appendChild(option);
    }
  }

  loadYears();

  // API DE MARCAS DE AUTOS
  async function loadBrands() {
    try {
      const response = await fetch(
        "https://api.mercadolibre.com/categories/MLA1744/attributes"
      );
      if (!response.ok) {
        throw new Error("Error al cargar las marcas");
      }
      const attributes = await response.json();
      const brands = attributes.find(
        (attr) => attr.name.toLowerCase() === "marca"
      );

      brandSelect.innerHTML = '<option value="">Selecciona una marca</option>'; // Opción predeterminada
      if (brands && brands.values.length > 0) {
        brands.values.forEach((brand) => {
          const option = document.createElement("option");
          option.value = brand.name;
          option.textContent = brand.name;
          brandSelect.appendChild(option);
        });
        brandSelect.disabled = false; // Habilitar el select de marcas
      } else {
        brandSelect.disabled = true; // Deshabilitar si no hay opciones
      }
    } catch (error) {
      console.error("Error al cargar las marcas:", error);
      alert("Error al cargar las marcas");
    }
  }

  // API DE MODELOS DE AUTOS
  async function loadModels(brand) {
    try {
      const response = await fetch(
        `https://vpic.nhtsa.dot.gov/api/vehicles/getmodelsformake/${brand}?format=json`
      );
      if (!response.ok) {
        throw new Error("Error al cargar los modelos");
      }
      const data = await response.json();
      const models = data.Results;

      modelSelect.innerHTML = '<option value="">Selecciona un modelo</option>'; // Opción predeterminada
      if (models && models.length > 0) {
        models.forEach((model) => {
          const option = document.createElement("option");
          option.value = model.Model_Name;
          option.textContent = model.Model_Name;
          modelSelect.appendChild(option);
        });
        modelSelect.disabled = false; // Habilitar el select de modelos
        msgNoVersions.classList.add("d-none");
      } else {
        modelSelect.disabled = true; // Deshabilitar si no hay opciones
      }
    } catch (error) {
      console.error("Error al cargar los modelos:", error);
      alert("Error al cargar los modelos");
    }
  }

  // API DE VERSIONES DE AUTOS
  async function loadVersions(year, brand, model) {
    try {
      const response = await fetch(
        `https://www.fueleconomy.gov/ws/rest/vehicle/menu/options?year=${year}&make=${brand}&model=${model}`
      );
      if (!response.ok) {
        throw new Error(`Error ${response.status}: ${response.statusText}`);
      }

      const textData = await response.text();
      const parser = new DOMParser();
      const xmlDoc = parser.parseFromString(textData, "text/xml");

      const menuItems = xmlDoc.getElementsByTagName("menuItem");
      versionSelect.innerHTML =
        '<option value="">Selecciona una versión</option>'; // Opción predeterminada

      if (menuItems.length > 0) {
        Array.from(menuItems).forEach((item) => {
          const text = item.getElementsByTagName("text")[0].textContent;
          const value = item.getElementsByTagName("value")[0].textContent;

          const option = document.createElement("option");
          option.value = value;
          option.textContent = text;
          versionSelect.appendChild(option);
        });
        versionSelect.disabled = false; // Habilitar el select de versiones
        msgNoVersions.classList.add("d-none");
      } else {
        versionSelect.disabled = true; // Deshabilitar si no hay opciones
        msgNoVersions.classList.remove("d-none");
      }
    } catch (error) {
      console.error("Error al cargar las versiones:", error);
      alert("Error al cargar las versiones");
    }
  }

  // Carga de los años disponibles

  yearSelect.addEventListener("change", async () => {
    const year = yearSelect.value;
    if (year) {
      await loadBrands();
      modelSelect.disabled = true; // Deshabilitar modelos
      versionSelect.disabled = true; // Deshabilitar versiones
      getPriceButton.disabled = true; // Deshabilitar botón de obtener precio
    }
  });

  // Cargar marcas cuando se seleccione un año
  brandSelect.addEventListener("change", async () => {
    const brand = brandSelect.value;
    if (brand) {
      await loadModels(brand);
      versionSelect.disabled = true; // Deshabilitar versiones
      getPriceButton.disabled = true; // Deshabilitar botón de obtener precio
    }
  });

  // Cargar modelos cuando se seleccione una marca
  modelSelect.addEventListener("change", async () => {
    const model = modelSelect.value;
    if (model) {
      const year = yearSelect.value;
      await loadVersions(year, brandSelect.value, model);
      getPriceButton.disabled = false; // Habilitar botón de obtener precio
    }
  });

  ///////////////////////////////////////////////////////////////////////////////////

  // SECCIÓN PRECIO

  // SUMA ASEGURADA
  let baseValueFixed = null; // Variable para almacenar la suma asegurada fija

  // Obtener el precio base y actualizar el display
  async function getPrice(year, brand, model, version) {
    try {
      const mockPrice = Math.floor(Math.random() * (50000 - 20000 + 1) + 0); // Generar un precio ficticio entre $20,000 y $50,000

      // Mostrar el valor de 'mockPrice' (precio base)
      console.log("Precio base generado (mockPrice):", mockPrice);

      updatePriceDisplay(mockPrice); // Llamar a la función para actualizar el display del precio base
      return mockPrice;

      // Función para actualizar el display del precio base sin adicionales
      function updatePriceDisplay(price) {
        // Mostrar el valor de 'priceDisplay'
        console.log(
          "Elemento 'priceDisplay' actualizado con el precio:",
          price
        );

        priceDisplay.innerHTML = `<p>Prima Técnica: $${price.toFixed(2)}</p>`;
      }
    } catch (error) {
      console.error("Error al obtener el precio:", error);
      alert("Error al obtener el precio");
      return 0;
    }
  }

  // Precio Base sin Adicionales

  // Cargar versiones cuando se seleccione un modelo
  versionSelect.addEventListener("change", async () => {
    const year = yearSelect.value;
    const brand = brandSelect.value;
    const model = modelSelect.value;
    const version = versionSelect.value;

    // Asegúrate de que se han seleccionado todos los campos necesarios
    if (!year || !brand || !model || !version) {
      alert(
        "Por favor, selecciona todos los campos para obtener la Suma Asegurada."
      );
      return;
    }

    if (version) {
      const price = await getPrice(year, brand, model, version);

      // Crear una nueva variable 'priceCopy' y asignarle el mismo valor que 'price'
      let priceCopy = price; // 'priceCopy' toma el mismo valor que 'price'

      // Mostrar el valor de 'priceCopy'
      console.log("Valor de 'priceCopy':", priceCopy);

      // Mostrar el valor de 'price' una vez recibido
      console.log("Precio recibido de 'getPrice':", price);
      const sumaAsegurada = price * 1.3 * 10; // Ejemplo: 30% más del precio como suma asegurada
      sumaAseguradaInput.value = `$${sumaAsegurada.toFixed(2)}`;
      baseValueFixed = sumaAsegurada; // Gu
      //PRECIO FINAL CON ADICIONALES
      let sumaBaseOriginal = priceCopy;
      let sumaBaseConAdicionales = sumaBaseOriginal;

      const isNewSelect = document.getElementById("isNewSelect");
      const tracking = document.getElementById("tracking");
      const gnc = document.getElementById("gnc");
      const adjustmentClause = document.getElementById("adjustmentClause");
      const vehicleUse = document.getElementById("vehicleUse");
      const planA_Price = document.getElementById("planA_Price");
      const planB_Price = document.getElementById("planB_Price");
      const planC_Price = document.getElementById("planC_Price");
      const planD_Price = document.getElementById("planD_Price");
      const coverageType = document.getElementById("coverageType");
      const primaFinal = document.getElementById("primaFinal");
      const iva = document.getElementById("iva");
      const premio = document.getElementById("premio");
      let precioBaseConAdicionales;

      function recalcularSuma() {
        sumaBaseConAdicionales = sumaBaseOriginal;

        //if (isNewSelect.value === "yes") {
        //  sumaBaseConAdicionales += sumaBaseOriginal * 0.10;
        //}

        if (gnc.value === "no") {
          sumaBaseConAdicionales -= sumaBaseOriginal * 0.15;
        }

        if (tracking.value === "no") {
          sumaBaseConAdicionales += sumaBaseOriginal * 0.05;
        }

        switch (adjustmentClause.value) {
          case "1":
            sumaBaseConAdicionales += sumaBaseOriginal * 0.05;
            break;
          case "2":
            sumaBaseConAdicionales += sumaBaseOriginal * 0.1;
            break;
          case "3":
            sumaBaseConAdicionales += sumaBaseOriginal * 0.15;
            break;
          case "4":
            sumaBaseConAdicionales += sumaBaseOriginal * 0.2;
            break;
          case "5":
            sumaBaseConAdicionales += sumaBaseOriginal * 0.25;
            break;
        }

        if (vehicleUse.value === "commercial") {
          sumaBaseConAdicionales += sumaBaseOriginal * 0.05;
        }
        const planA = sumaBaseConAdicionales;
        const planB = (sumaBaseConAdicionales += sumaBaseConAdicionales * 0.1);
        const planC = (sumaBaseConAdicionales += sumaBaseConAdicionales * 0.2);
        const planD = (sumaBaseConAdicionales += sumaBaseConAdicionales * 0.3);

        planA_Price.innerHTML = `<p><strong>Plan A (Solo RC):</strong> $${planA.toFixed(
          2
        )}</p>`;
        planB_Price.innerHTML = `<p><strong>Plan B (Todo Total):</strong> $${planB.toFixed(
          2
        )}</p>`;
        planC_Price.innerHTML = `<p><strong>Plan C (Terceros Completo):</strong> $${planC.toFixed(
          2
        )}</p>`;
        planD_Price.innerHTML = `<p><strong>Plan D (Todo Riesgo):</strong> $${planD.toFixed(
          2
        )}</p>`;

        switch (coverageType.value) {
          case "planA":
            finalPriceDisplay.innerHTML = `<span>Prima Final: $${planA.toFixed(
              2
            )}</span>`;
            precioBaseConAdicionales = planA.toFixed(2);
            break;
          case "planB":
            finalPriceDisplay.innerHTML = `<span>Prima Final: $${planB.toFixed(
              2
            )}</span>`;
            precioBaseConAdicionales = planB.toFixed(2);
            break;
          case "planC":
            finalPriceDisplay.innerHTML = `<span>Prima Final: $${planC.toFixed(
              2
            )}</span>`;
            precioBaseConAdicionales = planC.toFixed(2);
            break;
          case "planD":
            finalPriceDisplay.innerHTML = `<span>Prima Final: $${planD.toFixed(
              2
            )}</span>`;
            precioBaseConAdicionales = planD.toFixed(2);
            break;
        }

        const cuentaIva = Number(precioBaseConAdicionales) * 0.21;
        const premioCuenta = Number(precioBaseConAdicionales) + cuentaIva;
        primaFinal.innerHTML = `<span>$${precioBaseConAdicionales}</span>`;
        iva.innerHTML = `<span>$${cuentaIva.toFixed(2)}</span>`;
        premio.innerHTML = `<span>$${premioCuenta.toFixed(2)}</span>`;
        console.log(
          "sumaBaseConAdicionales actualizada: ",
          sumaBaseConAdicionales
        );
      }

      // isNewSelect.addEventListener("change", recalcularSuma);
      gnc.addEventListener("change", recalcularSuma);
      tracking.addEventListener("change", recalcularSuma);
      adjustmentClause.addEventListener("change", recalcularSuma);
      vehicleUse.addEventListener("change", recalcularSuma);
      coverageType.addEventListener("change", recalcularSuma);
      recalcularSuma();

      finalPriceDisplay.innerHTML = `<span>Prima Final $${sumaBaseConAdicionales.toFixed(
        2
      )}</span>`;
    }
  });

  ///////////////////////////////////////////////////////////////////////////////////

  // DATOS ADICIONALES

  // LÓGICA DEL 0KM (Muestra o Oculta dependiendo del año seleccionado)
  yearSelect.addEventListener("change", function () {
    const selectedYear = parseInt(this.value);
    const currentYear = new Date().getFullYear();

    if (selectedYear === currentYear) {
      isNewContainer.style.display = "block"; // Mostrar "¿Es 0KM?"
    } else {
      isNewContainer.style.display = "none"; // Ocultar "¿Es 0KM?"
      isNewSelect.value = "no"; // Reiniciar la selección a "no"
    }
  });

  ///////////////////////////////////////////////////////////////////////////////////
});

document
  .getElementById("priceOptions")
  .addEventListener("change", function (event) {
    const selectedValue = event.target.value;
  });

document
  .getElementById("payOptions")
  .addEventListener("change", function (event) {
    const selectedValue = event.target.value;
  });

function limpiarDatos() {
  const clientForm = document.getElementById("client-form");

  clientForm.reset();

  document.getElementById("licensePlate").value = "";
  document.getElementById("licensePlateError").style.display = "none";
  document.getElementById("sumaAsegurada").value = "";
  document.getElementById("priceDisplay").textContent = "Prima Técnica: $0.00";
  document.getElementById("finalPriceDisplay").textContent =
    "Prima Final: $0.00";

  document.getElementById("planA_Price").textContent =
    "Plan A (Solo RC): $0.00";
  document.getElementById("planB_Price").textContent =
    "Plan B (Todo Total): $0.00";
  document.getElementById("planC_Price").textContent =
    "Plan C (Terceros Completo): $0.00";
  document.getElementById("planD_Price").textContent =
    "Plan D (Todo Riesgo): $0.00";
  document.getElementById("primaFinal").textContent = "$0.00";
  document.getElementById("iva").textContent = "$0.00";
  document.getElementById("premio").textContent = "$0.00";

  document.getElementById("year").value = "";
  document.getElementById("brand").value = "";
  document.getElementById("brand").disabled = true;
  document.getElementById("model").value = "";
  document.getElementById("model").disabled = true;
  document.getElementById("version").value = "";
  document.getElementById("version").disabled = true;
  document.getElementById("tracking").value = "yes";
  document.getElementById("gnc").value = "yes";
  document.getElementById("adjustmentClause").value = "1";
  document.getElementById("vehicleUse").value = "personal";
  document.getElementById("coverageType").value = "planA";
  document.getElementById("priceOptions").value = "";
  document.getElementById("payOptions").value = "";
}
document.addEventListener("DOMContentLoaded", () => {
  const saveButton = document.getElementById("saveButton");
  const modal = document.getElementById("modal");
  const closeButton = modal.querySelector(".close-button");
  const modalTableBody = document
    .getElementById("modalTable")
    .querySelector("tbody");

  function openModal() {
    modal.classList.remove("hidden");
  }

  function closeModal() {
    modal.classList.add("hidden");
  }

  function populateModalTable() {
    modalTableBody.innerHTML = "";

    const fields = [
      {
        label: "Nombre",
        value: document.getElementById("nombre").value.trim(),
      },
      {
        label: "Apellido",
        value: document.getElementById("apellido").value.trim(),
      },
      { label: "DNI", value: document.getElementById("dni").value.trim() },
      {
        label: "Fecha de Nacimiento",
        value: document.getElementById("fechaNacimiento").value,
      },
      {
        label: "Domicilio",
        value: document.getElementById("domicilio").value.trim(),
      },
      {
        label: "Código Postal",
        value: document.getElementById("codigoPostal").value.trim(),
      },
      {
        label: "Teléfono",
        value: document.getElementById("telefono").value.trim(),
      },
      {
        label: "Correo Electrónico",
        value: document.getElementById("mail").value.trim(),
      },
      { label: "Marca", value: document.getElementById("brand").value },
      { label: "Modelo", value: document.getElementById("model").value },
      { label: "Versión", value: document.getElementById("version").value },
      {
        label: "Patente",
        value: document.getElementById("licensePlate").value,
      },
      {
        label: "Suma Asegurada",
        value: document.getElementById("sumaAsegurada").value,
      },
      { label: "GNC", value: document.getElementById("gnc").value },
      {
        label: "Equipo de Rastreo",
        value: document.getElementById("tracking").value,
      },
      {
        label: "Clausula de Ajuste",
        value: document.getElementById("adjustmentClause").value,
      },
      {
        label: "Uso del vehiculo",
        value: document.getElementById("vehicleUse").value,
      },
      {
        label: "Cobertura",
        value: document.getElementById("coverageType").value,
      },
      {
        label: "Tipo Contratacion",
        value: document.getElementById("priceOptions").value,
      },
      {
        label: "Método de Pago",
        value: document.getElementById("payOptions").value,
      },
    ];

    fields.forEach((field) => {
      const row = document.createElement("tr");
      row.innerHTML = `
          <td>${field.label}</td>
          <td>${field.value}</td>
        `;
      modalTableBody.appendChild(row);
    });
  }

  saveButton.addEventListener("click", () => {
    populateModalTable();
    openModal();
  });

  closeButton.addEventListener("click", closeModal);

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });
});

document.getElementById("printButton").addEventListener("click", () => {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  // Título del PDF
  doc.setFontSize(18);
  doc.text("Resumen del Presupuesto", 105, 20, null, null, "center");

  // Construir la tabla
  const fields = [
    { label: "Nombre", value: document.getElementById("nombre").value.trim() },
    {
      label: "Apellido",
      value: document.getElementById("apellido").value.trim(),
    },
    { label: "DNI", value: document.getElementById("dni").value.trim() },
    {
      label: "Fecha de Nacimiento",
      value: document.getElementById("fechaNacimiento").value,
    },
    {
      label: "Domicilio",
      value: document.getElementById("domicilio").value.trim(),
    },
    {
      label: "Código Postal",
      value: document.getElementById("codigoPostal").value.trim(),
    },
    {
      label: "Teléfono",
      value: document.getElementById("telefono").value.trim(),
    },
    {
      label: "Correo Electrónico",
      value: document.getElementById("mail").value.trim(),
    },
    { label: "Marca", value: document.getElementById("brand").value },
    { label: "Modelo", value: document.getElementById("model").value },
    { label: "Versión", value: document.getElementById("version").value },
    { label: "Patente", value: document.getElementById("licensePlate").value },
    {
      label: "Suma Asegurada",
      value: document.getElementById("sumaAsegurada").value,
    },
    { label: "GNC", value: document.getElementById("gnc").value },
    {
      label: "Equipo de Rastreo",
      value: document.getElementById("tracking").value,
    },
    {
      label: "Clausula de Ajuste",
      value: document.getElementById("adjustmentClause").value,
    },
    {
      label: "Uso del vehículo",
      value: document.getElementById("vehicleUse").value,
    },
    {
      label: "Cobertura",
      value: document.getElementById("coverageType").value,
    },
    {
      label: "Tipo Contratación",
      value: document.getElementById("priceOptions").value,
    },
    {
      label: "Método de Pago",
      value: document.getElementById("payOptions").value,
    },
  ];

  // Opciones iniciales de la tabla
  let startX = 10;
  let startY = 30;
  let rowHeight = 10;

  // Dibujar encabezado de la tabla
  doc.setFontSize(12);
  doc.setTextColor(255, 255, 255); // Blanco
  doc.setFillColor(0, 0, 0); // Negro
  doc.rect(startX, startY, 190, rowHeight, "F"); // Rectángulo relleno para el encabezado
  doc.text("Categoría", startX + 5, startY + 7);
  doc.text("Información", startX + 95, startY + 7);

  // Dibujar contenido de la tabla
  doc.setTextColor(0, 0, 0); // Negro
  startY += rowHeight; // Mover hacia la primera fila
  fields.forEach((field) => {
    doc.rect(startX, startY, 95, rowHeight); // Columna 1
    doc.rect(startX + 95, startY, 95, rowHeight); // Columna 2
    doc.text(field.label, startX + 5, startY + 7); // Categoría
    doc.text(field.value || "N/A", startX + 100, startY + 7); // Información
    startY += rowHeight;

    // Si la posición Y excede el límite de la página, agregar una nueva página
    if (startY > 280) {
      doc.addPage();
      startY = 10; // Reset posición vertical
    }
  });

  // Agregar valores adicionales en negrita después de la tabla
  const primaFinal = document.getElementById("primaFinal").textContent || "N/A";
  const iva = document.getElementById("iva").textContent || "N/A";
  const premioFinal = document.getElementById("premio").textContent || "N/A";

  startY += 10; // Espacio después de la tabla

  doc.setFont("helvetica", "bold");
  doc.text(`Prima Final: ${primaFinal}`, startX, startY);
  startY += 10;
  doc.text(`IVA: ${iva}`, startX, startY);
  startY += 10;
  doc.text(`Premio Final: ${premioFinal}`, startX, startY);

  // Guardar el PDF
  doc.save("resumen_presupuesto.pdf");
  Swal.fire({
    title: "Presupuesto Descargado!",
    icon: "success",
    confirmButtonText: "Cerrar",
    confirmButtonColor: "#007bff",
  });
});
