// src/js/main.js
import * as utils from "./modules/utils.js";
import * as state from "./modules/state.js";
import * as uiUpdater from "./modules/uiUpdater.js";
import * as turnosManager from "./modules/turnosManager.js";
import * as ausenciasManager from "./modules/ausenciasManager.js";
import * as observacoesManager from "./modules/observacoesManager.js";
import * as widgetsDashboard from "./modules/widgetsDashboard.js";
import * as backupHandler from "./modules/backupHandler.js";

console.log("[DEBUG] main.js: Módulo principal carregado.");

// --- Dark Mode Handler ---
function initializeDarkMode() {
  const darkModeToggle = document.getElementById("darkModeToggle");
  const htmlElement = document.documentElement;

  if (!darkModeToggle) {
    console.warn("[DEBUG] Botão darkModeToggle não encontrado.");
    // Aplicar tema inicial mesmo sem botão
    if (
      localStorage.getItem("darkMode") === "true" ||
      (!("darkMode" in localStorage) &&
        window.matchMedia("(prefers-color-scheme: dark)").matches)
    ) {
      htmlElement.classList.add("dark");
    } else {
      htmlElement.classList.remove("dark");
    }
    return;
  }

  const moonIcon = darkModeToggle.querySelector('i[data-lucide="moon"]');
  const sunIcon = darkModeToggle.querySelector('i[data-lucide="sun"]');

  const updateButtonState = () => {
    const isDark = htmlElement.classList.contains("dark");
    if (!moonIcon || !sunIcon) return;

    if (isDark) {
      moonIcon.classList.add("hidden");
      sunIcon.classList.remove("hidden");
    } else {
      moonIcon.classList.remove("hidden");
      sunIcon.classList.add("hidden");
    }
  };

  // Define o tema inicial ao carregar a página
  if (
    localStorage.getItem("darkMode") === "true" ||
    (!("darkMode" in localStorage) &&
      window.matchMedia("(prefers-color-scheme: dark)").matches)
  ) {
    htmlElement.classList.add("dark");
  } else {
    htmlElement.classList.remove("dark");
  }
  updateButtonState(); // Atualiza os ícones para o estado inicial

  // Listener para o clique no botão
  darkModeToggle.addEventListener("click", () => {
    htmlElement.classList.toggle("dark"); // Alterna a classe no HTML
    localStorage.setItem("darkMode", htmlElement.classList.contains("dark")); // Salva a preferência
    updateButtonState(); // Atualiza os ícones

    if (typeof lucide !== "undefined") {
      lucide.createIcons(); // Garante que ícones Lucide sejam re-renderizados
    }

    // Exemplo para atualizar gráficos (adapte conforme sua implementação)
    if (
      typeof turnosManager !== "undefined" &&
      typeof turnosManager.employeeHoursChartInstance !== "undefined" &&
      turnosManager.employeeHoursChartInstance !== null
    ) {
      if (
        document.getElementById("shifts-table-main") &&
        typeof state !== "undefined" &&
        state.currentDisplayYear &&
        state.currentDisplayMonth
      ) {
        // Idealmente, chame uma função que apenas atualize as cores do gráfico.
        // Se não, recarregar os dados pode forçar o redesenho com novas cores (se o gráfico for recriado).
        // turnosManager.carregarTurnosDoServidor(state.currentDisplayYear, state.currentDisplayMonth, true);
      }
    }
  });
}
// --- Fim Dark Mode Handler ---

async function syncDatesAndReloadAll(newYear, newMonth) {
  console.log(
    `[DEBUG] syncDatesAndReloadAll (main.js) chamado com newYear: ${newYear}, newMonth: ${newMonth}`
  );
  state.updateGlobalDate(newYear, newMonth);

  uiUpdater.updateAllDisplays();

  if (document.getElementById("shifts-table-main")) {
    await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      true
    );
  }
  if (document.getElementById("ausencias-table-main")) {
    await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  }
  if (document.getElementById("feriados-table")) {
    await widgetsDashboard.carregarFeriados(
      state.currentDisplayYearFeriados,
      state.currentDisplayMonthFeriados
    );
  }
  if (document.getElementById("escala-sabados-table")) {
    await widgetsDashboard.carregarEscalaSabados(
      state.currentDisplayYearEscalaSabados,
      state.currentDisplayMonthEscalaSabados
    );
  }
  if (document.getElementById("ausencia-setor-table")) {
    await widgetsDashboard.carregarAusenciaSetor(
      state.currentDisplayYearAusenciaSetor,
      state.currentDisplayMonthAusenciaSetor
    );
  }
}

document.addEventListener("DOMContentLoaded", async function () {
  console.log("[DEBUG] DOMContentLoaded (main.js): Evento disparado.");

  initializeDarkMode();

  console.log(
    `[DEBUG] Data inicial global (main.js) ${state.currentDisplayMonth}/${state.currentDisplayYear}`
  );

  const displayElementInit = document.getElementById(
    "current-month-year-display"
  );
  if (
    displayElementInit &&
    displayElementInit.dataset.year &&
    displayElementInit.dataset.month
  ) {
    const initialYear = parseInt(displayElementInit.dataset.year, 10);
    const initialMonth = parseInt(displayElementInit.dataset.month, 10);
    if (!isNaN(initialYear) && !isNaN(initialMonth)) {
      state.updateGlobalDate(initialYear, initialMonth);
      console.log(
        `[DEBUG] Data ajustada pelo display para ${state.currentDisplayMonth}/${state.currentDisplayYear} (main.js)`
      );
    }
  }

  uiUpdater.updateAllDisplays();

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  } else {
    console.warn(
      "[DEBUG] Biblioteca Lucide (lucide.js) não está definida (main.js)."
    );
  }

  if (typeof utils.buscarEArmazenarColaboradores === "function") {
    await utils.buscarEArmazenarColaboradores();
  }

  if (document.getElementById("shifts-table-main")) {
    turnosManager.initTurnosEventListeners();
    await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      true
    );
  }
  if (document.getElementById("ausencias-table-main")) {
    ausenciasManager.initAusenciasEventListeners();
    await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  }
  if (document.getElementById("observacoes-gerais-textarea")) {
    observacoesManager.initObservacoesEventListeners();
  }
  if (document.getElementById("feriados-table")) {
    await widgetsDashboard.carregarFeriados(
      state.currentDisplayYearFeriados,
      state.currentDisplayMonthFeriados
    );
  }
  if (document.getElementById("escala-sabados-table")) {
    await widgetsDashboard.carregarEscalaSabados(
      state.currentDisplayYearEscalaSabados,
      state.currentDisplayMonthEscalaSabados
    );
  }
  if (document.getElementById("ausencia-setor-table")) {
    await widgetsDashboard.carregarAusenciaSetor(
      state.currentDisplayYearAusenciaSetor,
      state.currentDisplayMonthAusenciaSetor
    );
  }
  if (document.getElementById("backup-db-btn")) {
    backupHandler.initBackupHandler();
  }

  const prevMonthButton = document.getElementById("prev-month-button");
  if (prevMonthButton) {
    prevMonthButton.addEventListener("click", () => {
      let newMonth = state.currentDisplayMonth - 1;
      let newYear = state.currentDisplayYear;
      if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const nextMonthButton = document.getElementById("next-month-button");
  if (nextMonthButton) {
    nextMonthButton.addEventListener("click", () => {
      let newMonth = state.currentDisplayMonth + 1;
      let newYear = state.currentDisplayYear;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const prevMonthBtnAus = document.getElementById(
    "prev-month-ausencias-button"
  );
  if (prevMonthBtnAus && prevMonthBtnAus !== prevMonthButton) {
    prevMonthBtnAus.addEventListener("click", () => {
      let newMonth = state.currentDisplayMonthAusencias - 1;
      let newYear = state.currentDisplayYearAusencias;
      if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const nextMonthBtnAus = document.getElementById(
    "next-month-ausencias-button"
  );
  if (nextMonthBtnAus && nextMonthBtnAus !== nextMonthButton) {
    nextMonthBtnAus.addEventListener("click", () => {
      let newMonth = state.currentDisplayMonthAusencias + 1;
      let newYear = state.currentDisplayYearAusencias;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const logoutLk = document.getElementById("logout-link");
  if (logoutLk) {
    logoutLk.addEventListener("click", (e) => {
      e.preventDefault();
      utils.showToast("Saindo do sistema...", "info", 1500);
      setTimeout(() => {
        if (logoutLk.href) window.location.href = logoutLk.href;
      }, 1500);
    });
  }
  console.log(
    "[DEBUG] main.js: Todos os listeners e carregamentos iniciais configurados."
  );
});
window.showGlobalToast = utils.showToast;
console.log("[DEBUG] main.js: Fim da análise do script.");
