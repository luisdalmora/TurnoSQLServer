// public/js/main.js
import * as utils from "./modules/utils.js";
import * as state from "./modules/state.js";
import * as uiUpdater from "./modules/uiUpdater.js";
import * as turnosManager from "./modules/turnosManager.js";
import * as ausenciasManager from "./modules/ausenciasManager.js";
import * as widgetsDashboard from "./modules/widgetsDashboard.js";
import * as backupHandler from "./modules/backupHandler.js";
import { initTooltips } from "./modules/tooltipManager.js";
import {
  renderTurnosCalendar,
  initTurnosCalendar,
} from "./modules/turnosCalendarManager.js";
import * as tabsManager from "./modules/tabsManager.js";

console.log("[DEBUG] main.js: Módulo principal carregado.");

async function syncDatesAndReloadAll(newYear, newMonth) {
  // console.log(
  //   `[DEBUG] syncDatesAndReloadAll (main.js) chamado com newYear: ${newYear}, newMonth: ${newMonth}`
  // );
  state.updateGlobalDate(newYear, newMonth);
  uiUpdater.updateAllDisplays();

  let turnosDoMes = [];
  let ausenciasDoMes = [];

  if (document.getElementById("tab-content-turnos")) {
    turnosDoMes = await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      false
    );
  }
  if (document.getElementById("tab-content-ausencias")) {
    ausenciasDoMes = await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  } else if (document.getElementById("turnos-calendar-view-container")) {
    if (!document.getElementById("tab-content-ausencias")) {
      ausenciasDoMes = await ausenciasManager.carregarAusenciasDoServidor(
        state.currentDisplayYear,
        state.currentDisplayMonth
      );
    }
  }

  if (
    (!turnosDoMes || turnosDoMes.length === 0) &&
    document.getElementById("turnos-calendar-view-container") &&
    !document.getElementById("tab-content-turnos")
  ) {
    turnosDoMes = await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      false
    );
  }

  // Garantir que são arrays antes de passar para renderTurnosCalendar
  const finalTurnosParaCalendario = Array.isArray(turnosDoMes)
    ? turnosDoMes
    : [];
  const finalAusenciasParaCalendario = Array.isArray(ausenciasDoMes)
    ? ausenciasDoMes
    : [];

  if (document.getElementById("turnos-calendar-view-container")) {
    renderTurnosCalendar(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      finalTurnosParaCalendario,
      finalAusenciasParaCalendario
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

  if (document.getElementById("main-tabs")) {
    tabsManager.refreshActiveTabState();
  }

  if (typeof initTooltips === "function") initTooltips();
}

function initMobileMenu() {
  const sidebar = document.getElementById("app-sidebar");
  const menuButton = document.getElementById("mobile-menu-button");
  const overlay = document.getElementById("sidebar-overlay");

  if (sidebar && menuButton && overlay) {
    menuButton.addEventListener("click", function (event) {
      event.stopPropagation();
      sidebar.classList.toggle("open");
      overlay.classList.toggle("open");
    });

    overlay.addEventListener("click", function () {
      sidebar.classList.remove("open");
      overlay.classList.remove("open");
    });

    const sidebarLinks = sidebar.querySelectorAll("a");
    sidebarLinks.forEach((link) => {
      link.addEventListener("click", function () {
        if (sidebar.classList.contains("open")) {
          sidebar.classList.remove("open");
          overlay.classList.remove("open");
        }
      });
    });
  } else {
    // Comentado para não poluir console se elementos não existirem em todas as páginas
    // if (!sidebar) console.warn('Elemento da sidebar (#app-sidebar) não encontrado para menu mobile.');
    // if (!menuButton) console.warn('Botão do menu mobile (#mobile-menu-button) não encontrado.');
    // if (!overlay) console.warn('Overlay da sidebar (#sidebar-overlay) não encontrado.');
  }
}

document.addEventListener("DOMContentLoaded", async function () {
  // console.log("[DEBUG] DOMContentLoaded (main.js): Evento disparado.");

  requestAnimationFrame(() => {
    document.body.classList.add("body-visible");
  });

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
    }
  }

  uiUpdater.updateAllDisplays();

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  if (typeof initTooltips === "function") initTooltips();
  initMobileMenu();

  await utils.buscarEArmazenarColaboradores();

  const IS_ADMIN_ON_MAIN = window.APP_USER_ROLE === "admin";
  let turnosIniciais = [];
  let ausenciasIniciaisParaCalendario = [];

  if (document.getElementById("main-tabs")) {
    tabsManager.initMainTabs();
  }

  if (document.getElementById("tab-content-turnos")) {
    turnosManager.initTurnosEventListeners();
    turnosIniciais = await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      false
    );
  }
  turnosIniciais = Array.isArray(turnosIniciais) ? turnosIniciais : []; // Garantia extra

  if (document.getElementById("tab-content-ausencias")) {
    ausenciasManager.initAusenciasEventListeners();
    const ausenciasParaAba = await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
    ausenciasIniciaisParaCalendario = Array.isArray(ausenciasParaAba)
      ? ausenciasParaAba
      : []; // Garantia
  } else {
    // Se a aba de ausências não existe, carrega para o calendário (se ele existir)
    ausenciasIniciaisParaCalendario = []; // Inicializa se não foi pela aba
  }
  ausenciasIniciaisParaCalendario = Array.isArray(
    ausenciasIniciaisParaCalendario
  )
    ? ausenciasIniciaisParaCalendario
    : []; // Garantia

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

  if (document.getElementById("turnos-calendar-view-container")) {
    let turnosCal = turnosIniciais;
    let ausenciasCal = ausenciasIniciaisParaCalendario;

    // Se os dados de turnos não foram carregados pela aba (porque a aba não existe)
    if (
      turnosCal.length === 0 &&
      !document.getElementById("tab-content-turnos")
    ) {
      turnosCal = await turnosManager.carregarTurnosDoServidor(
        state.currentDisplayYear,
        state.currentDisplayMonth,
        false
      );
      turnosCal = Array.isArray(turnosCal) ? turnosCal : [];
    }
    // Se os dados de ausências não foram carregados pela aba ou se a data do calendário é diferente
    if (
      ausenciasCal.length === 0 ||
      state.currentDisplayYear !== state.currentDisplayYearAusencias ||
      state.currentDisplayMonth !== state.currentDisplayMonthAusencias
    ) {
      ausenciasCal = await ausenciasManager.carregarAusenciasDoServidor(
        state.currentDisplayYear,
        state.currentDisplayMonth
      );
      ausenciasCal = Array.isArray(ausenciasCal) ? ausenciasCal : [];
    }

    initTurnosCalendar();
    renderTurnosCalendar(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      turnosCal,
      ausenciasCal
    );
    const calendarPeriodSpan = document.getElementById("calendar-view-period");
    if (calendarPeriodSpan) {
      calendarPeriodSpan.textContent =
        utils.nomesMeses[state.currentDisplayMonth] || "";
    }
  }

  if (IS_ADMIN_ON_MAIN && document.getElementById("backup-db-btn")) {
    backupHandler.initBackupHandler();
  } else if (document.getElementById("backup-db-btn")) {
    if (document.getElementById("backup-db-btn"))
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
  if (prevMonthBtnAus) {
    prevMonthBtnAus.addEventListener("click", async () => {
      // Adicionado async
      let nm = state.currentDisplayMonthAusencias - 1,
        ny = state.currentDisplayYearAusencias;
      if (nm < 1) {
        nm = 12;
        ny--;
      }
      state.setAusenciasDate(ny, nm);
      uiUpdater.updateCurrentMonthYearDisplayAusencias();
      if (document.getElementById("tab-content-ausencias")) {
        await ausenciasManager.carregarAusenciasDoServidor(ny, nm); // Adicionado await
      }
    });
  }
  const nextMonthBtnAus = document.getElementById(
    "next-month-ausencias-button"
  );
  if (nextMonthBtnAus) {
    nextMonthBtnAus.addEventListener("click", async () => {
      // Adicionado async
      let nm = state.currentDisplayMonthAusencias + 1,
        ny = state.currentDisplayYearAusencias;
      if (nm > 12) {
        nm = 1;
        ny++;
      }
      state.setAusenciasDate(ny, nm);
      uiUpdater.updateCurrentMonthYearDisplayAusencias();
      if (document.getElementById("tab-content-ausencias")) {
        await ausenciasManager.carregarAusenciasDoServidor(ny, nm); // Adicionado await
      }
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

  // console.log(
  //   "[DEBUG] main.js: Todos os listeners e carregamentos iniciais configurados."
  // );
});

window.showGlobalToast = utils.showToast;

window.atualizarCalendarioGeral = async (
  year,
  month,
  turnos = null,
  ausencias = null
) => {
  // console.log("[DEBUG] window.atualizarCalendarioGeral chamado.");
  let turnosParaCalendario = Array.isArray(turnos) ? turnos : [];
  let ausenciasParaCalendario = Array.isArray(ausencias) ? ausencias : [];

  if (turnos === null) {
    // Só recarrega se for explicitamente null
    turnosParaCalendario = await turnosManager.carregarTurnosDoServidor(
      year,
      month,
      false
    );
    turnosParaCalendario = Array.isArray(turnosParaCalendario)
      ? turnosParaCalendario
      : [];
  }

  if (ausencias === null) {
    // Só recarrega se for explicitamente null
    ausenciasParaCalendario =
      await ausenciasManager.carregarAusenciasDoServidor(year, month);
    ausenciasParaCalendario = Array.isArray(ausenciasParaCalendario)
      ? ausenciasParaCalendario
      : [];
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

// console.log("[DEBUG] main.js: Fim da análise do script.");
