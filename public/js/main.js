// public/js/main.js
import * as utils from "./modules/utils.js";
import * as state from "./modules/state.js";
import * as uiUpdater from "./modules/uiUpdater.js";
import * as turnosManager from "./modules/turnosManager.js";
import * as ausenciasManager from "./modules/ausenciasManager.js";
import * as observacoesManager from "./modules/observacoesManager.js";
import * as widgetsDashboard from "./modules/widgetsDashboard.js";
import * as backupHandler from "./modules/backupHandler.js";
import { initTooltips } from "./modules/tooltipManager.js";
import {
  renderTurnosCalendar,
  initTurnosCalendar,
} from "./modules/turnosCalendarManager.js";

console.log("[DEBUG] main.js: Módulo principal carregado.");

async function syncDatesAndReloadAll(newYear, newMonth) {
  console.log(
    `[DEBUG] syncDatesAndReloadAll (main.js) chamado com newYear: ${newYear}, newMonth: ${newMonth}`
  );
  state.updateGlobalDate(newYear, newMonth);

  uiUpdater.updateAllDisplays();

  let turnosDoMes = [];
  let ausenciasDoMes = [];

  if (document.getElementById("shifts-table-main")) {
    turnosDoMes = await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      true
    );
  }

  if (document.getElementById("ausencias-table-main")) {
    // Carrega dados de ausências para a tabela de ausências e para o calendário
    ausenciasDoMes = await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  } else if (document.getElementById("turnos-calendar-view-container")) {
    // Se a tabela de ausências não estiver na página, mas o calendário estiver,
    // ainda precisamos carregar os dados de ausências para o calendário.
    ausenciasDoMes = await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYear, // Usa o ano/mês global para o calendário
      state.currentDisplayMonth
    );
  }

  if (document.getElementById("turnos-calendar-view-container")) {
    renderTurnosCalendar(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      turnosDoMes,
      ausenciasDoMes
    );

    const calendarPeriodSpan = document.getElementById("calendar-view-period");
    if (calendarPeriodSpan) {
      calendarPeriodSpan.textContent =
        utils.nomesMeses[state.currentDisplayMonth] || "";
    }
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
  if (typeof initTooltips === "function") initTooltips();
}

document.addEventListener("DOMContentLoaded", async function () {
  console.log("[DEBUG] DOMContentLoaded (main.js): Evento disparado.");

  requestAnimationFrame(() => {
    document.body.classList.add("body-visible");
  });

  console.log(`[DEBUG] User Role: ${window.APP_USER_ROLE}`);
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

  if (typeof initTooltips === "function") initTooltips();

  await utils.buscarEArmazenarColaboradores();

  const IS_ADMIN_ON_MAIN = window.APP_USER_ROLE === "admin";
  let turnosIniciais = [];
  let ausenciasIniciais = [];

  if (document.getElementById("shifts-table-main")) {
    turnosManager.initTurnosEventListeners();
    turnosIniciais = await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      true
    );
  }

  if (document.getElementById("ausencias-table-main")) {
    ausenciasManager.initAusenciasEventListeners();
    ausenciasIniciais = await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  } else if (document.getElementById("turnos-calendar-view-container")) {
    ausenciasIniciais = await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth
    );
  }

  if (document.getElementById("turnos-calendar-view-container")) {
    initTurnosCalendar();
    renderTurnosCalendar(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      turnosIniciais,
      ausenciasIniciais
    );
    const calendarPeriodSpan = document.getElementById("calendar-view-period");
    if (calendarPeriodSpan) {
      calendarPeriodSpan.textContent =
        utils.nomesMeses[state.currentDisplayMonth] || "";
    }
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

  if (IS_ADMIN_ON_MAIN && document.getElementById("backup-db-btn")) {
    backupHandler.initBackupHandler();
  } else if (document.getElementById("backup-db-btn")) {
    document.getElementById("backup-db-btn").style.display = "none";
  }

  if (typeof initTooltips === "function") initTooltips();

  const prevMonthButton = document.getElementById("prev-month-button");
  if (prevMonthButton) {
    prevMonthButton.addEventListener("click", () => {
      let nm = state.currentDisplayMonth - 1,
        ny = state.currentDisplayYear;
      if (nm < 1) {
        nm = 12;
        ny--;
      }
      syncDatesAndReloadAll(ny, nm);
    });
  }
  const nextMonthButton = document.getElementById("next-month-button");
  if (nextMonthButton) {
    nextMonthButton.addEventListener("click", () => {
      let nm = state.currentDisplayMonth + 1,
        ny = state.currentDisplayYear;
      if (nm > 12) {
        nm = 1;
        ny++;
      }
      syncDatesAndReloadAll(ny, nm);
    });
  }

  const prevMonthBtnAus = document.getElementById(
    "prev-month-ausencias-button"
  );
  if (prevMonthBtnAus && prevMonthBtnAus !== prevMonthButton) {
    prevMonthBtnAus.addEventListener("click", () => {
      let nm = state.currentDisplayMonthAusencias - 1,
        ny = state.currentDisplayYearAusencias;
      if (nm < 1) {
        nm = 12;
        ny--;
      }

      syncDatesAndReloadAll(ny, nm);
    });
  }
  const nextMonthBtnAus = document.getElementById(
    "next-month-ausencias-button"
  );
  if (nextMonthBtnAus && nextMonthBtnAus !== nextMonthButton) {
    nextMonthBtnAus.addEventListener("click", () => {
      let nm = state.currentDisplayMonthAusencias + 1,
        ny = state.currentDisplayYearAusencias;
      if (nm > 12) {
        nm = 1;
        ny++;
      }
      syncDatesAndReloadAll(ny, nm);
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

// Adicionando uma função global para permitir que o ausenciasManager atualize o calendário
// caso uma ausência seja salva/excluída e o calendário precise refletir isso imediatamente.
// Isso é uma forma de comunicação entre módulos, não ideal, mas funcional para este caso.
// Uma abordagem mais robusta usaria um sistema de eventos (event bus).
window.atualizarCalendarioGeral = async (
  year,
  month,
  turnos = null,
  ausencias = null
) => {
  console.log("[DEBUG] window.atualizarCalendarioGeral chamado.");
  let turnosParaCalendario = turnos;
  let ausenciasParaCalendario = ausencias;

  if (turnos === null) {
    // Se turnos não foram passados, recarrega-os
    turnosParaCalendario = await turnosManager.carregarTurnosDoServidor(
      year,
      month,
      false
    ); // false para não atualizar resumos de novo
  }
  if (ausencias === null) {
    // Se ausências não foram passadas, recarrega-as
    ausenciasParaCalendario =
      await ausenciasManager.carregarAusenciasDoServidor(year, month);
  }

  if (document.getElementById("turnos-calendar-view-container")) {
    renderTurnosCalendar(
      year,
      month,
      turnosParaCalendario,
      ausenciasParaCalendario
    );
  }
};

console.log("[DEBUG] main.js: Fim da análise do script.");
