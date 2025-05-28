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
  table.className = "min-w-full border-collapse border border-gray-300";

  const thead = table.createTHead();
  const headerRow = thead.insertRow();
  headerRow.className = "bg-gray-100";
  DIAS_SEMANA.forEach((dia) => {
    const th = document.createElement("th");
    th.className =
      "p-2 border border-gray-300 text-xs sm:text-sm font-medium text-gray-600 uppercase";
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
        // Adiciona T00:00:00 para garantir que as datas sejam tratadas como locais e não UTC.
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
                data_evento: dataCorrente.toISOString().split("T")[0], // Data específica do dia no calendário
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
        "p-1.5 border border-gray-200 h-28 sm:h-32 align-top relative";

      if (i === 0 && j < startingDayOfWeek) {
        cell.classList.add("bg-gray-50");
      } else if (currentDay <= daysInCurrentMonth) {
        cell.classList.add("bg-white");
        const dayNumberDiv = document.createElement("div");
        dayNumberDiv.className =
          "text-xs sm:text-sm font-medium text-gray-700 mb-1 text-right";
        dayNumberDiv.textContent = currentDay;
        cell.appendChild(dayNumberDiv);

        if (eventosPorDia[currentDay]) {
          const eventosListDiv = document.createElement("div");
          eventosListDiv.className =
            "space-y-1 overflow-y-auto max-h-[calc(6rem-1.5rem)] sm:max-h-[calc(7rem-1.75rem)] text-xs";

          eventosPorDia[currentDay].sort((a, b) => {
            if (a.type === "turno" && b.type !== "turno") return -1;
            if (a.type !== "turno" && b.type === "turno") return 1;
            if (a.type === "turno" && b.type === "turno") {
              return (a.hora_inicio || "").localeCompare(b.hora_inicio || "");
            }
            // Para ausências, pode ordenar por nome ou observação se necessário
            return (a.colaborador_nome || a.observacoes || "").localeCompare(
              b.colaborador_nome || b.observacoes || ""
            );
          });

          eventosPorDia[currentDay].forEach((evento) => {
            const eventoDiv = document.createElement("div");
            let displayColaborador = "";
            let titleText = "";

            if (evento.type === "turno") {
              eventoDiv.className =
                "bg-blue-100 text-blue-800 p-1 rounded truncate hover:whitespace-normal hover:overflow-visible hover:z-10 hover:relative";
              displayColaborador = evento.colaborador || "N/D";
              titleText = `${evento.hora_inicio || ""} - ${
                evento.hora_fim || ""
              }\n${evento.colaborador}`;
              if (displayColaborador.length > 12) {
                displayColaborador =
                  displayColaborador.substring(0, 10) + "...";
              }
              eventoDiv.innerHTML = `
                                <span class="font-medium">${
                                  evento.hora_inicio
                                    ? evento.hora_inicio.substring(0, 5)
                                    : ""
                                }</span>
                                <span class="block text-[0.7rem] leading-tight">${displayColaborador}</span>
                            `;
            } else if (evento.type === "ausencia") {
              eventoDiv.className =
                "bg-orange-100 text-orange-800 p-1 rounded truncate hover:whitespace-normal hover:overflow-visible hover:z-10 hover:relative";
              displayColaborador = evento.colaborador_nome || "Ausência";
              let obs = evento.observacoes
                ? ` (${
                    evento.observacoes.substring(0, 15) +
                    (evento.observacoes.length > 15 ? "..." : "")
                  })`
                : "";
              titleText = `${displayColaborador}${obs}`;
              if (displayColaborador.length > 12) {
                displayColaborador =
                  displayColaborador.substring(0, 10) + "...";
              }
              eventoDiv.innerHTML = `
                                <i data-lucide="user-x" class="w-3 h-3 inline-block mr-1"></i>
                                <span class="font-medium">${displayColaborador}</span>
                                ${
                                  obs
                                    ? `<span class="block text-[0.7rem] leading-tight">${obs}</span>`
                                    : ""
                                }
                            `;
              if (typeof lucide !== "undefined") {
                const iconEl = eventoDiv.querySelector("i[data-lucide]");
                if (iconEl) lucide.createIcons({ nodes: [iconEl] });
              }
            }
            eventoDiv.title = titleText;
            eventosListDiv.appendChild(eventoDiv);
          });
          cell.appendChild(eventosListDiv);
        }
        currentDay++;
      } else {
        cell.classList.add("bg-gray-50");
      }
    }
  }
  calendarContainer.appendChild(table);
  // Não é necessário chamar createIcons aqui, pois os ícones dentro do loop já são processados.
}

export function initTurnosCalendar() {
  console.log("[DEBUG] Módulo de Calendário de Turnos inicializado/pronto.");
}
