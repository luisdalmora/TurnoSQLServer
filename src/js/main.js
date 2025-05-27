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
// function initializeDarkMode() { ... } // TODA A FUNÇÃO REMOVIDA
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

  // initializeDarkMode(); // CHAMADA REMOVIDA

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
