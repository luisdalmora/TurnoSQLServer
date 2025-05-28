// public/js/modules/turnosCalendarManager.js
import * as state from "./state.js";
import { nomesMeses } from "./utils.js";

console.log("[DEBUG] turnosCalendarManager.js: Módulo carregado.");

const DIAS_SEMANA = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];

function getDayISO(date) {
  let day = date.getDay();
  return day;
}

function getDaysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}

function* dateRange(startDate, endDate) {
  let currentDate = new Date(
    startDate.getFullYear(),
    startDate.getMonth(),
    startDate.getDate()
  );
  const end = new Date(
    endDate.getFullYear(),
    endDate.getMonth(),
    endDate.getDate()
  );
  while (currentDate <= end) {
    yield new Date(currentDate);
    currentDate.setDate(currentDate.getDate() + 1);
  }
}

export function renderTurnosCalendar(
  year,
  month,
  turnosData = [],
  ausenciasData = []
) {
  const calendarContainer = document.getElementById(
    "turnos-calendar-view-container"
  );
  if (!calendarContainer) {
    console.error("Elemento #turnos-calendar-view-container não encontrado.");
    return;
  }
  calendarContainer.innerHTML = "";

  const table = document.createElement("table");
  table.className = "min-w-full border-collapse border border-slate-300"; // Borda mais suave

  const thead = table.createTHead();
  const headerRow = thead.insertRow();
  headerRow.className = "bg-slate-100"; // Fundo suave para header do calendário
  DIAS_SEMANA.forEach((dia) => {
    const th = document.createElement("th");
    th.className =
      "p-1 border border-slate-300 text-[0.65rem] sm:text-xs font-medium text-slate-600 uppercase";
    th.textContent = dia;
    headerRow.appendChild(th);
  });

  const tbody = table.createTBody();
  const firstDayOfMonth = new Date(year, month - 1, 1);
  const daysInCurrentMonth = getDaysInMonth(year, month);
  const startingDayOfWeek = getDayISO(firstDayOfMonth);

  const eventosPorDia = {};

  if (Array.isArray(turnosData)) {
    turnosData.forEach((turno) => {
      if (turno.data) {
        const diaTurno = parseInt(turno.data.split("-")[2], 10);
        if (!eventosPorDia[diaTurno]) {
          eventosPorDia[diaTurno] = [];
        }
        eventosPorDia[diaTurno].push({ ...turno, type: "turno" });
      }
    });
  }

  if (Array.isArray(ausenciasData)) {
    ausenciasData.forEach((ausencia) => {
      if (ausencia.data_inicio && ausencia.data_fim) {
        const inicioAusencia = new Date(ausencia.data_inicio + "T00:00:00");
        const fimAusencia = new Date(ausencia.data_fim + "T00:00:00");

        for (const dataCorrente of dateRange(inicioAusencia, fimAusencia)) {
          if (
            dataCorrente.getFullYear() === year &&
            dataCorrente.getMonth() === month - 1
          ) {
            const diaAusencia = dataCorrente.getDate();
            if (!eventosPorDia[diaAusencia]) {
              eventosPorDia[diaAusencia] = [];
            }
            const ausJaExiste = eventosPorDia[diaAusencia].find(
              (e) => e.type === "ausencia" && e.id === ausencia.id
            );
            if (!ausJaExiste) {
              eventosPorDia[diaAusencia].push({
                ...ausencia,
                type: "ausencia",
                data_evento: dataCorrente.toISOString().split("T")[0],
              });
            }
          }
        }
      }
    });
  }

  let currentDay = 1;
  for (let i = 0; i < 6; i++) {
    if (
      currentDay > daysInCurrentMonth &&
      i > 0 &&
      i * 7 + 1 - startingDayOfWeek > daysInCurrentMonth
    )
      break;

    const weekRow = tbody.insertRow();
    for (let j = 0; j < 7; j++) {
      const cell = weekRow.insertCell();
      cell.className =
        "p-1 border border-slate-200 h-20 align-top relative text-[0.7rem]"; // h-20 para célula compacta

      if (i === 0 && j < startingDayOfWeek) {
        cell.classList.add("bg-slate-50");
      } else if (currentDay <= daysInCurrentMonth) {
        cell.classList.add("bg-white");
        const dayNumberDiv = document.createElement("div");
        dayNumberDiv.className =
          "text-[0.65rem] sm:text-xs font-medium text-slate-700 text-right mb-0.5";
        dayNumberDiv.textContent = currentDay;
        cell.appendChild(dayNumberDiv);

        if (eventosPorDia[currentDay]) {
          const eventosListDiv = document.createElement("div");
          eventosListDiv.className =
            "space-y-0.5 overflow-y-auto max-h-12 text-[0.65rem] leading-tight custom-scrollbar-thin";

          eventosPorDia[currentDay].sort((a, b) => {
            if (a.type === "turno" && b.type !== "turno") return -1;
            if (a.type !== "turno" && b.type === "turno") return 1;
            if (a.type === "turno" && b.type === "turno") {
              return (a.hora_inicio || "").localeCompare(b.hora_inicio || "");
            }
            return (a.colaborador_nome || a.observacoes || "").localeCompare(
              b.colaborador_nome || b.observacoes || ""
            );
          });

          const maxEventosVisiveisPorDia = 2;
          eventosPorDia[currentDay]
            .slice(0, maxEventosVisiveisPorDia)
            .forEach((evento) => {
              const eventoDiv = document.createElement("div");
              let displayColaborador = "";
              let titleText = "";

              if (evento.type === "turno") {
                eventoDiv.className =
                  "bg-sky-100 text-sky-700 p-0.5 rounded truncate text-[0.6rem]"; // Cor suave
                displayColaborador = evento.colaborador || "N/D";
                titleText = `${
                  evento.hora_inicio ? evento.hora_inicio.substring(0, 5) : ""
                } - ${
                  evento.hora_fim ? evento.hora_fim.substring(0, 5) : ""
                }\n${evento.colaborador}`;

                eventoDiv.innerHTML = `
                <span class="font-medium">${
                  evento.hora_inicio ? evento.hora_inicio.substring(0, 5) : ""
                }</span>
                <span class="block truncate" title="${
                  evento.colaborador
                }">${displayColaborador.substring(0, 8)}${
                  displayColaborador.length > 8 ? "..." : ""
                }</span>`;
              } else if (evento.type === "ausencia") {
                eventoDiv.className =
                  "bg-orange-100 text-orange-700 p-0.5 rounded truncate text-[0.6rem]"; // Cor para ausência
                displayColaborador = evento.colaborador_nome || "Ausência";
                let obsText = evento.observacoes ? evento.observacoes : "";
                titleText = `${displayColaborador}${
                  obsText ? "\n(" + obsText + ")" : ""
                }`;

                eventoDiv.innerHTML = `
                <span class="flex items-center" title="${titleText}">
                  <i data-lucide="user-x" class="w-2.5 h-2.5 inline-block mr-0.5 flex-shrink-0"></i>
                  <span class="truncate">${displayColaborador.substring(0, 8)}${
                  displayColaborador.length > 8 ? "..." : ""
                }</span>
                </span>`;
              }
              eventoDiv.setAttribute("data-tooltip-text", titleText);
              eventosListDiv.appendChild(eventoDiv);
            });

          if (eventosPorDia[currentDay].length > maxEventosVisiveisPorDia) {
            const maisEventosDiv = document.createElement("div");
            maisEventosDiv.className =
              "text-center text-[0.6rem] text-slate-500 mt-0.5";
            maisEventosDiv.textContent = `+${
              eventosPorDia[currentDay].length - maxEventosVisiveisPorDia
            } mais`;
            eventosListDiv.appendChild(maisEventosDiv);
          }

          cell.appendChild(eventosListDiv);
          if (typeof lucide !== "undefined") {
            lucide.createIcons({
              nodes: cell.querySelectorAll("i[data-lucide]"),
            });
          }
        }
        currentDay++;
      } else {
        cell.classList.add("bg-slate-50");
      }
    }
  }
  calendarContainer.appendChild(table);

  if (typeof initTooltips === "function") {
    // Garante que initTooltips seja definida
    initTooltips();
  }
}

export function initTurnosCalendar() {
  console.log("[DEBUG] Módulo de Calendário de Turnos inicializado/pronto.");
  // Estilos para scrollbar fina já estão no header.php
}
