// public/js/modules/utils.js
console.log("[DEBUG] utils.js: Módulo carregado.");

export const nomesMeses = {
  1: "Janeiro",
  2: "Fevereiro",
  3: "Março",
  4: "Abril",
  5: "Maio",
  6: "Junho",
  7: "Julho",
  8: "Agosto",
  9: "Setembro",
  10: "Outubro",
  11: "Novembro",
  12: "Dezembro",
};

export const tailwindInputClasses =
  "block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 bg-white text-gray-700 placeholder-gray-400";
export const tailwindSelectClasses =
  "block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 bg-white text-gray-700";
export const tailwindCheckboxClasses =
  "h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500";

let activeToastTimeout = null;
export function showToast(message, type = "info", duration = 3500) {
  const existingToast = document.getElementById("toast-notification");
  if (existingToast) {
    existingToast.remove();
    if (activeToastTimeout) clearTimeout(activeToastTimeout);
  }
  const toast = document.createElement("div");
  toast.id = "toast-notification";

  let bgColor = "bg-blue-500";
  let iconHtml = "";
  const iconClasses = "w-5 h-5 mr-2";

  if (type === "success") {
    bgColor = "bg-green-500";
    iconHtml = `<i data-lucide="check-circle" class="${iconClasses}"></i>`;
  } else if (type === "error") {
    bgColor = "bg-red-500";
    iconHtml = `<i data-lucide="x-circle" class="${iconClasses}"></i>`;
  } else if (type === "warning") {
    bgColor = "bg-yellow-500 text-gray-800";
    iconHtml = `<i data-lucide="alert-triangle" class="${iconClasses}"></i>`;
  } else if (type === "info") {
    bgColor = "bg-blue-500";
    iconHtml = `<i data-lucide="info" class="${iconClasses}"></i>`;
  }

  toast.className = `fixed bottom-5 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-white text-sm font-medium z-[1060] transition-all duration-300 ease-out opacity-0 translate-y-10 flex items-center ${bgColor}`;
  toast.innerHTML = iconHtml + `<span>${message}</span>`;

  document.body.appendChild(toast);

  if (typeof lucide !== "undefined" && iconHtml !== "") {
    const iconElement = toast.querySelector("i[data-lucide]");
    if (iconElement) {
      lucide.createIcons({
        nodes: [iconElement],
      });
    }
  }

  requestAnimationFrame(() => {
    toast.classList.remove("opacity-0", "translate-y-10");
    toast.classList.add("opacity-100", "translate-y-0");
  });

  activeToastTimeout = setTimeout(() => {
    toast.classList.remove("opacity-100", "translate-y-0");
    toast.classList.add("opacity-0", "translate-y-10");
    toast.addEventListener("transitionend", () => toast.remove(), {
      once: true,
    });
  }, duration);
}

export let todosOsColaboradores = [];

export async function buscarEArmazenarColaboradores() {
  console.log("[DEBUG] buscarEArmazenarColaboradores (utils.js) chamado.");
  if (
    todosOsColaboradores.length > 0 &&
    todosOsColaboradores[0] &&
    todosOsColaboradores[0].hasOwnProperty("id")
  ) {
    console.log("[DEBUG] Usando colaboradores já armazenados (utils.js).");
    return todosOsColaboradores;
  }
  try {
    console.log("[DEBUG] Buscando colaboradores do servidor (utils.js)...");
    const response = await fetch("api/obter_colaboradores.php"); //
    console.log(
      "[DEBUG] Resposta de obter_colaboradores.php status (utils.js):",
      response.status
    );
    if (!response.ok) {
      let errorMsg = `Falha ao buscar colaboradores: HTTP ${response.status}`;
      try {
        const errData = await response.json();
        errorMsg = errData.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    const data = await response.json();
    console.log("[DEBUG] Dados de colaboradores recebidos (utils.js):", data);
    if (data.success && data.colaboradores) {
      todosOsColaboradores = data.colaboradores;
      return todosOsColaboradores;
    } else {
      showToast(
        data.message || "Falha ao carregar lista de colaboradores do backend.",
        "error"
      );
      todosOsColaboradores = [];
      return [];
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro na requisição fetch de colaboradores (utils.js):",
      error
    );
    showToast(
      `Erro crítico ao carregar colaboradores: ${error.message}`,
      "error"
    );
    todosOsColaboradores = [];
    return [];
  }
}

export function popularSelectColaborador(
  selectElement,
  valorSelecionado = null,
  colaboradoresArray = null
) {
  const colaboradores = colaboradoresArray || todosOsColaboradores;
  selectElement.innerHTML =
    '<option value="" class="text-gray-500">Selecione...</option>';
  if (!Array.isArray(colaboradores)) {
    console.error(
      "[DEBUG] Erro: 'colaboradores' não é um array em popularSelectColaborador (utils.js)."
    );
    return;
  }
  colaboradores.forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.nome_completo;
    option.textContent = colab.nome_completo;
    if (valorSelecionado && colab.nome_completo === valorSelecionado)
      option.selected = true;
    selectElement.appendChild(option);
  });
}

export function calcularDuracaoDecimal(horaInicioStr, horaFimStr) {
  if (!horaInicioStr || !horaFimStr) return 0;
  const [h1Str, m1Str] = horaInicioStr.split(":");
  const [h2Str, m2Str] = horaFimStr.split(":");

  const h1 = parseInt(h1Str, 10);
  const m1 = parseInt(m1Str, 10);
  const h2 = parseInt(h2Str, 10);
  const m2 = parseInt(m2Str, 10);

  if (isNaN(h1) || isNaN(m1) || isNaN(h2) || isNaN(m2)) return 0;

  let inicioEmMinutos = h1 * 60 + m1;
  let fimEmMinutos = h2 * 60 + m2;

  if (fimEmMinutos < inicioEmMinutos) {
    fimEmMinutos += 24 * 60;
  }

  const duracaoEmMinutos = fimEmMinutos - inicioEmMinutos;
  return duracaoEmMinutos > 0 ? duracaoEmMinutos / 60.0 : 0;
}

export function showConfirmationModal(
  message,
  onConfirm,
  onCancel,
  title = "Confirmação"
) {
  const modalId = "confirmation-modal-dynamic";
  let existingModal = document.getElementById(modalId);
  if (existingModal) {
    existingModal.remove();
  }

  const modal = document.createElement("div");
  modal.id = modalId;
  modal.className =
    "fixed inset-0 bg-gray-800 bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-[1080] p-4 transition-opacity duration-300 ease-in-out opacity-0";

  modal.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-sm transform transition-all duration-300 ease-in-out scale-95 opacity-0">
            <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i data-lucide="alert-circle" class="w-5 h-5 mr-2 text-yellow-500"></i>
                    ${title}
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 modal-close-btn-confirm p-1 rounded-full hover:bg-gray-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="mt-4 mb-6">
                <p class="text-sm text-gray-600">${message}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="modal-cancel-btn-confirm inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150">
                    <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancelar
                </button>
                <button type="button" class="modal-confirm-btn-confirm inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-150">
                    <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Confirmar
                </button>
            </div>
        </div>
    `;
  document.body.appendChild(modal);

  const modalContent = modal.querySelector(".bg-white");

  if (typeof lucide !== "undefined") {
    lucide.createIcons({
      nodes: modal.querySelectorAll("i[data-lucide]"),
    });
  }

  requestAnimationFrame(() => {
    modal.classList.remove("opacity-0");
    if (modalContent) modalContent.classList.remove("scale-95", "opacity-0");
    if (modalContent) modalContent.classList.add("scale-100", "opacity-100");
  });

  const closeModal = (callback) => {
    if (modalContent) modalContent.classList.remove("scale-100", "opacity-100");
    if (modalContent) modalContent.classList.add("scale-95", "opacity-0");
    modal.classList.add("opacity-0");

    modal.addEventListener(
      "transitionend",
      () => {
        modal.remove();
        if (callback && typeof callback === "function") {
          callback();
        }
      },
      { once: true }
    );
  };

  modal
    .querySelector(".modal-confirm-btn-confirm")
    .addEventListener("click", () => {
      closeModal(onConfirm);
    });

  modal
    .querySelector(".modal-cancel-btn-confirm")
    .addEventListener("click", () => {
      closeModal(onCancel);
    });

  modal
    .querySelector(".modal-close-btn-confirm")
    .addEventListener("click", () => {
      closeModal(onCancel);
    });

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeModal(onCancel);
    }
  });
}
