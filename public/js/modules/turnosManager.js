// public/js/modules/turnosManager.js
import {
  showToast,
  popularSelectColaborador,
  calcularDuracaoDecimal,
  tailwindInputClasses,
  tailwindSelectClasses,
  tailwindCheckboxClasses,
  buscarEArmazenarColaboradores,
  todosOsColaboradores as colaboradoresGlobais,
  nomesMeses,
  showConfirmationModal,
} from "./utils.js";
import * as state from "./state.js";
import { updateCurrentMonthYearDisplayTurnos } from "./uiUpdater.js";

console.log("[DEBUG] turnosManager.js: Módulo carregado.");

// let employeeHoursChartInstance = null; // Removido pois o gráfico foi removido

async function popularTabelaTurnos(turnos) {
  // ... (código existente, com ajustes de cores se desejar, ex: text-slate-500, bg-slate-50)
  const corpoTabela = document.querySelector("#shifts-table-main tbody");
  if (!corpoTabela) {
    return;
  }
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-shifts");
  if (chkAll) chkAll.checked = false;

  if (
    colaboradoresGlobais.length === 0 ||
    !colaboradoresGlobais[0] ||
    !colaboradoresGlobais[0].hasOwnProperty("id")
  ) {
    await buscarEArmazenarColaboradores();
  }

  if (!turnos || turnos.length === 0) {
    const r = corpoTabela.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    const colSpan = document.querySelector(
      "#shifts-table-main thead th input#select-all-shifts"
    )
      ? 5
      : 4;
    c.colSpan = colSpan;
    c.className = "p-2 text-center text-slate-500 text-sm"; // Cor suave
    c.textContent = "Nenhum turno programado para este período.";
    return;
  }

  const isUserAdmin = window.APP_USER_ROLE === "admin";

  turnos.forEach((turno) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-slate-50"; // Cor suave
    nLinha.setAttribute("data-turno-id", turno.id);

    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    if (isUserAdmin) {
      const inputCheckbox = document.createElement("input");
      inputCheckbox.type = "checkbox";
      inputCheckbox.className = `shift-select-checkbox ${tailwindCheckboxClasses
        .replace("text-indigo-600", "text-sky-600")
        .replace("focus:ring-indigo-500", "focus:ring-sky-500")
        .replace("border-gray-300", "border-slate-300")}`;
      inputCheckbox.value = turno.id;
      cellCheckbox.appendChild(inputCheckbox);
    }

    const cellData = nLinha.insertCell();
    cellData.className = "p-1";
    const inputData = document.createElement("input");
    inputData.type = "text";
    inputData.className = `shift-date ${tailwindInputClasses
      .replace("border-gray-300", "border-slate-300")
      .replace("focus:ring-blue-500", "focus:ring-sky-500")
      .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
    inputData.value = turno.data_formatada || turno.data;
    inputData.placeholder = "dd/Mês";
    inputData.disabled = !isUserAdmin;
    cellData.appendChild(inputData);

    const cellInicio = nLinha.insertCell();
    cellInicio.className = "p-1";
    const inputInicio = document.createElement("input");
    inputInicio.type = "time";
    inputInicio.className = `shift-time-inicio ${tailwindInputClasses
      .replace("border-gray-300", "border-slate-300")
      .replace("focus:ring-blue-500", "focus:ring-sky-500")
      .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
    inputInicio.value = turno.hora_inicio
      ? turno.hora_inicio.substring(0, 5)
      : "";
    inputInicio.disabled = !isUserAdmin;
    cellInicio.appendChild(inputInicio);

    const cellFim = nLinha.insertCell();
    cellFim.className = "p-1";
    const inputFim = document.createElement("input");
    inputFim.type = "time";
    inputFim.className = `shift-time-fim ${tailwindInputClasses
      .replace("border-gray-300", "border-slate-300")
      .replace("focus:ring-blue-500", "focus:ring-sky-500")
      .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
    inputFim.value = turno.hora_fim ? turno.hora_fim.substring(0, 5) : "";
    inputFim.disabled = !isUserAdmin;
    cellFim.appendChild(inputFim);

    const cellColab = nLinha.insertCell();
    cellColab.className = "p-1";
    const selColab = document.createElement("select");
    selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses
      .replace("border-gray-300", "border-slate-300")
      .replace("focus:ring-blue-500", "focus:ring-sky-500")
      .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
    popularSelectColaborador(selColab, turno.colaborador, colaboradoresGlobais);
    selColab.disabled = !isUserAdmin;
    cellColab.appendChild(selColab);
  });
}

export async function carregarTurnosDoServidor(
  ano,
  mes,
  atualizarResumosGlobais = true // Parâmetro agora é opcional e menos relevante sem o gráfico
) {
  const shiftsTableBody = document.querySelector("#shifts-table-main tbody");
  const csrfInputOriginal = document.getElementById("csrf-token-shifts");
  const isUserAdmin = window.APP_USER_ROLE === "admin";

  if (shiftsTableBody) {
    const colSpan = isUserAdmin ? 5 : 4;
    shiftsTableBody.innerHTML = `<tr><td colspan="${colSpan}" class="p-2 text-center text-slate-500 text-sm">Carregando turnos... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
    if (typeof lucide !== "undefined")
      lucide.createIcons({ nodes: [shiftsTableBody.querySelector("i")] });
  } else {
    // console.error(
    //   "[DEBUG] Elemento tbody da tabela de turnos não encontrado (turnosManager.js)."
    // );
    return []; // GARANTIR RETORNO DE ARRAY
  }

  try {
    const response = await fetch(`api/salvar_turnos.php?ano=${ano}&mes=${mes}`);
    let data;
    if (!response.ok) {
      let errorMsg = `Erro HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    const turnosCarregados = data.data || [];

    if (data.success) {
      if (isUserAdmin && data.csrf_token && csrfInputOriginal)
        csrfInputOriginal.value = data.csrf_token;

      await popularTabelaTurnos(turnosCarregados);
      // if (atualizarResumosGlobais) { // Resumos foram removidos
      //   atualizarTabelaResumoColaboradores(turnosCarregados);
      //   // atualizarGraficoResumoHoras(turnosCarregados);
      // }
      return turnosCarregados;
    } else {
      showToast(
        "Aviso: " + (data.message || "Não foi possível carregar turnos."),
        "warning"
      );
      await popularTabelaTurnos([]);
      // if (atualizarResumosGlobais) {
      //   atualizarTabelaResumoColaboradores([]);
      //   // atualizarGraficoResumoHoras([]);
      // }
      return []; // RETORNAR ARRAY VAZIO
    }
  } catch (error) {
    // console.error(
    //   `[DEBUG] Erro ao carregar turnos para ${mes}/${ano} (turnosManager.js):`,
    //   error
    // );
    showToast(`Erro ao carregar turnos: ${error.message}.`, "error");
    await popularTabelaTurnos([]);
    // if (atualizarResumosGlobais) {
    //   atualizarTabelaResumoColaboradores([]);
    //   // atualizarGraficoResumoHoras([]);
    // }
    return []; // RETORNAR ARRAY VAZIO
  }
}

// ... (funções coletarDadosDaTabelaDeTurnos, salvarDadosTurnosNoServidor, excluirTurnosNoServidor como antes, mas chamadas a carregarTurnosDoServidor devem ser awaited)
// ... (funções atualizarTabelaResumoColaboradores e atualizarGraficoResumoHoras foram removidas ou comentadas se não são mais usadas)

function coletarDadosDaTabelaDeTurnos() {
  // ... (código existente)
  const linhas = document.querySelectorAll("#shifts-table-main tbody tr");
  const dados = [];
  const displayElement = document.getElementById("current-month-year-display"); // Este ID está no cabeçalho da aba de turnos
  const anoTabela =
    displayElement && displayElement.dataset.year
      ? parseInt(displayElement.dataset.year, 10)
      : state.currentDisplayYear;

  let erroValidacaoGeralTurnos = false;

  linhas.forEach((linha, index) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;

    const dataIn = linha.querySelector(".shift-date");
    const horaInicioIn = linha.querySelector(".shift-time-inicio");
    const horaFimIn = linha.querySelector(".shift-time-fim");
    const colabSel = linha.querySelector(".shift-employee-select");
    const idOrig = linha.getAttribute("data-turno-id");

    const dataVal = dataIn ? dataIn.value.trim() : "";
    const inicioVal = horaInicioIn ? horaInicioIn.value.trim() : "";
    const fimVal = horaFimIn ? horaFimIn.value.trim() : "";
    const colabVal = colabSel ? colabSel.value.trim() : "";

    if (dataVal && inicioVal && fimVal && colabVal) {
      const inicioTotalMin =
        parseInt(inicioVal.split(":")[0], 10) * 60 +
        parseInt(inicioVal.split(":")[1], 10);
      const fimTotalMin =
        parseInt(fimVal.split(":")[0], 10) * 60 +
        parseInt(fimVal.split(":")[1], 10);

      if (
        fimTotalMin <= inicioTotalMin &&
        !(
          parseInt(fimVal.split(":")[0], 10) < 6 &&
          parseInt(inicioVal.split(":")[0], 10) > 18
        )
      ) {
        showToast(
          `Atenção (linha ${
            index + 1
          }): Turno para ${colabVal} em ${dataVal} tem Hora Fim (${fimVal}) não posterior à Hora Início (${inicioVal}). Este turno não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeralTurnos = true;
        return;
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data: dataVal,
        hora_inicio: inicioVal,
        hora_fim: fimVal,
        colaborador: colabVal,
        ano: anoTabela.toString(),
      });
    } else if (
      !(dataVal === "" && inicioVal === "" && fimVal === "" && colabVal === "")
    ) {
      showToast(
        `Linha de turno ${
          index + 1
        } incompleta não será salva. Preencha todos os campos: Dia, Início, Fim e Colaborador.`,
        "warning",
        5000
      );
      erroValidacaoGeralTurnos = true;
    }
  });

  if (erroValidacaoGeralTurnos && dados.length === 0) return [];
  if (erroValidacaoGeralTurnos && dados.length > 0) return null;
  return dados;
}

async function salvarDadosTurnosNoServidor(dadosTurnos, csrfToken) {
  const btnSalvar = document.getElementById("save-shifts-button");
  const originalButtonHTML = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined")
      lucide.createIcons({ nodes: [btnSalvar.querySelector("i")] });
  }

  const payload = {
    acao: "salvar_turnos",
    turnos: dadosTurnos,
    csrf_token: csrfToken,
  };

  try {
    const response = await fetch("api/salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Turnos salvos com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }

      await carregarTurnosDoServidor(
        // Adicionado await
        state.currentDisplayYear,
        state.currentDisplayMonth,
        false // Não atualizar resumos
      );
    } else {
      showToast(
        "Erro ao salvar: " + (data.message || "Erro desconhecido do servidor."),
        "error"
      );
    }
  } catch (error) {
    // console.error(
    //   "[DEBUG] Erro crítico ao salvar turnos (turnosManager.js):",
    //   error
    // );
    showToast(`Erro crítico ao salvar: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonHTML;
      if (
        typeof lucide !== "undefined" &&
        btnSalvar.querySelector('i[data-lucide="save"]')
      ) {
        // Garante que o ícone existe
        lucide.createIcons({
          nodes: [btnSalvar.querySelector('i[data-lucide="save"]')],
        });
      } else if (typeof lucide !== "undefined") {
        lucide.createIcons(); // Fallback para recriar todos se o específico não for encontrado
      }
    }
  }
}

async function excluirTurnosNoServidor(ids, csrfToken) {
  if (!ids || ids.length === 0) {
    showToast("Nenhum turno selecionado para exclusão.", "info");
    return;
  }

  try {
    const response = await fetch("api/salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_turnos",
        ids_turnos: ids,
        csrf_token: csrfToken,
      }),
    });
    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Turno(s) excluído(s) com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      await carregarTurnosDoServidor(
        // Adicionado await
        state.currentDisplayYear,
        state.currentDisplayMonth,
        false // Não atualizar resumos
      );
    } else {
      showToast(
        "Erro ao excluir: " + (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    // console.error(
    //   "[DEBUG] Erro crítico ao excluir turnos (turnosManager.js):",
    //   error
    // );
    showToast(`Erro crítico ao excluir: ${error.message}.`, "error");
  }
}

export function initTurnosEventListeners() {
  // ... (código existente com await nas chamadas de carregarTurnosDoServidor)
  const isUserAdmin = window.APP_USER_ROLE === "admin";

  if (isUserAdmin) {
    const btnSalvarTurnos = document.getElementById("save-shifts-button");
    if (btnSalvarTurnos) {
      btnSalvarTurnos.addEventListener("click", async () => {
        // Adicionado async
        // console.log("[DEBUG] Botão 'Salvar Turnos' clicado (turnosManager.js).");
        const csrfTokenEl = document.getElementById("csrf-token-shifts");
        const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
        if (!csrfToken) {
          showToast(
            "Erro de segurança (token turnos ausente). Recarregue a página.",
            "error"
          );
          return;
        }
        const dados = coletarDadosDaTabelaDeTurnos();
        if (dados && dados.length > 0) {
          await salvarDadosTurnosNoServidor(dados, csrfToken); // Adicionado await
        } else if (dados && dados.length === 0) {
          const tbody = document.querySelector("#shifts-table-main tbody");
          const placeholderVisivel =
            tbody && tbody.querySelector("td[colspan='5']");
          if (placeholderVisivel || (tbody && tbody.rows.length === 0)) {
            showToast("Adicione pelo menos um turno para salvar.", "info");
          } else {
            showToast(
              "Nenhum turno válido para salvar. Verifique as linhas.",
              "warning"
            );
          }
        } else if (dados === null) {
          // console.log(
          //   "[DEBUG] Coleta de dados de turnos retornou null (erro de validação) (turnosManager.js)."
          // );
        } else {
          // console.error(
          //   "[DEBUG] coletarDadosDaTabelaDeTurnos retornou valor inesperado:",
          //   dados
          // );
          showToast("Erro interno ao coletar dados dos turnos.", "error");
        }
      });
    }

    const btnAdicionarTurno = document.getElementById("add-shift-row-button");
    if (btnAdicionarTurno) {
      btnAdicionarTurno.addEventListener("click", async function () {
        // console.log(
        //   "[DEBUG] Botão 'Adicionar Turno' clicado (turnosManager.js)."
        // );
        const tbody = document.querySelector("#shifts-table-main tbody");
        if (!tbody) return;
        const placeholderRow = tbody.querySelector("td[colspan='5']");
        if (placeholderRow) tbody.innerHTML = "";

        if (
          colaboradoresGlobais.length === 0 ||
          !colaboradoresGlobais[0] ||
          !colaboradoresGlobais[0].hasOwnProperty("id")
        ) {
          await buscarEArmazenarColaboradores();
        }

        const newId = "new-" + Date.now();
        const nLinha = tbody.insertRow();
        nLinha.className = "bg-white hover:bg-slate-50"; // Cor suave
        nLinha.setAttribute("data-turno-id", newId);

        let cell = nLinha.insertCell();
        cell.className = "p-2 text-center";
        let inputChk = document.createElement("input");
        inputChk.type = "checkbox";
        inputChk.className = `shift-select-checkbox ${tailwindCheckboxClasses
          .replace("text-indigo-600", "text-sky-600")
          .replace("focus:ring-indigo-500", "focus:ring-sky-500")
          .replace("border-gray-300", "border-slate-300")}`;
        inputChk.value = newId;
        cell.appendChild(inputChk);

        const dataAtual = new Date();
        const dia = String(dataAtual.getDate()).padStart(2, "0");

        const nomeMesAtual =
          nomesMeses[state.currentDisplayMonth] ||
          `Mês ${state.currentDisplayMonth}`;

        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputData = document.createElement("input");
        inputData.type = "text";
        inputData.className = `shift-date ${tailwindInputClasses
          .replace("border-gray-300", "border-slate-300")
          .replace("focus:ring-blue-500", "focus:ring-sky-500")
          .replace("focus:border-indigo-500", "focus:border-sky-500")}`;

        inputData.value = `${dia}/${nomeMesAtual.substring(0, 3)}`;
        inputData.placeholder = "dd/Mês";
        cell.appendChild(inputData);

        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputInicio = document.createElement("input");
        inputInicio.type = "time";
        inputInicio.className = `shift-time-inicio ${tailwindInputClasses
          .replace("border-gray-300", "border-slate-300")
          .replace("focus:ring-blue-500", "focus:ring-sky-500")
          .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
        inputInicio.value = "08:00";
        cell.appendChild(inputInicio);

        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputFim = document.createElement("input");
        inputFim.type = "time";
        inputFim.className = `shift-time-fim ${tailwindInputClasses
          .replace("border-gray-300", "border-slate-300")
          .replace("focus:ring-blue-500", "focus:ring-sky-500")
          .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
        inputFim.value = "12:00";
        cell.appendChild(inputFim);

        cell = nLinha.insertCell();
        cell.className = "p-1";
        const selColab = document.createElement("select");
        selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses
          .replace("border-gray-300", "border-slate-300")
          .replace("focus:ring-blue-500", "focus:ring-sky-500")
          .replace("focus:border-indigo-500", "focus:border-sky-500")}`;
        popularSelectColaborador(selColab, null, colaboradoresGlobais);
        cell.appendChild(selColab);

        if (inputData) inputData.focus();
      });
    }

    const chkAllShifts = document.getElementById("select-all-shifts");
    if (chkAllShifts) {
      chkAllShifts.addEventListener("change", () => {
        document
          .querySelectorAll("#shifts-table-main .shift-select-checkbox")
          .forEach((c) => (c.checked = chkAllShifts.checked));
      });
    }

    const btnDelSelShifts = document.getElementById(
      "delete-selected-shifts-button"
    );
    if (btnDelSelShifts) {
      btnDelSelShifts.addEventListener("click", () => {
        // console.log(
        //   "[DEBUG] Botão 'Excluir Turnos Selecionados' clicado (turnosManager.js)."
        // );
        const csrfTokenEl = document.getElementById("csrf-token-shifts");
        const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
        if (!csrfToken) {
          showToast("Erro de segurança. Recarregue a página.", "error");
          return;
        }
        const idsParaExcluirServidor = [];
        let linhasNovasRemovidasLocalmente = 0;
        const linhasParaRemoverLocalmente = [];

        document
          .querySelectorAll("#shifts-table-main .shift-select-checkbox:checked")
          .forEach((c) => {
            const tr = c.closest("tr");
            if (tr) {
              const id = tr.getAttribute("data-turno-id");
              if (id && !id.startsWith("new-")) {
                idsParaExcluirServidor.push(id);
              } else if (id && id.startsWith("new-")) {
                linhasNovasRemovidasLocalmente++;
                linhasParaRemoverLocalmente.push(tr);
              }
            }
          });

        if (
          idsParaExcluirServidor.length === 0 &&
          linhasNovasRemovidasLocalmente === 0
        ) {
          showToast("Nenhum turno selecionado para exclusão.", "info");
          return;
        }

        let confirmMessage = "";
        if (
          idsParaExcluirServidor.length > 0 &&
          linhasNovasRemovidasLocalmente > 0
        ) {
          confirmMessage = `Tem certeza que deseja excluir ${idsParaExcluirServidor.length} turno(s) salvo(s) e remover ${linhasNovasRemovidasLocalmente} linha(s) nova(s)? A exclusão dos turnos salvos não pode ser desfeita.`;
        } else if (idsParaExcluirServidor.length > 0) {
          confirmMessage = `Tem certeza que deseja excluir ${idsParaExcluirServidor.length} turno(s) salvo(s)? Esta ação não pode ser desfeita.`;
        } else {
          confirmMessage = `Tem certeza que deseja remover ${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s))?`;
        }

        const chkAllShiftsCurrent =
          document.getElementById("select-all-shifts");

        showConfirmationModal(
          confirmMessage,
          async () => {
            if (idsParaExcluirServidor.length > 0) {
              await excluirTurnosNoServidor(idsParaExcluirServidor, csrfToken);
            }

            if (linhasNovasRemovidasLocalmente > 0) {
              linhasParaRemoverLocalmente.forEach((tr) => tr.remove());
              showToast(
                `${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s)) foram removida(s).`,
                "info"
              );
              const tbody = document.querySelector("#shifts-table-main tbody");
              if (tbody && tbody.rows.length === 0) {
                await popularTabelaTurnos([]);
              }
            }
            if (chkAllShiftsCurrent) chkAllShiftsCurrent.checked = false;
          },
          () => {
            showToast("Exclusão de turnos cancelada.", "info");
            document
              .querySelectorAll(
                "#shifts-table-main .shift-select-checkbox:checked"
              )
              .forEach((c) => (c.checked = false));
            if (chkAllShiftsCurrent) chkAllShiftsCurrent.checked = false;
          }
        );
      });
    }
  }
}
