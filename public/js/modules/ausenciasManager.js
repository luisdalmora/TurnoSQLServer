// src/js/modules/ausenciasManager.js
import {
  showToast,
  popularSelectColaborador,
  tailwindInputClasses,
  tailwindSelectClasses,
  tailwindCheckboxClasses,
  buscarEArmazenarColaboradores,
  todosOsColaboradores as colaboradoresGlobais,
} from "./utils.js";
import * as state from "./state.js";
import { initTooltips } from "./tooltipManager.js"; // Para re-inicializar tooltips se necessário

console.log("[DEBUG] ausenciasManager.js: Módulo carregado.");

const IS_USER_ADMIN_AUSENCIAS = window.APP_USER_ROLE === "admin";

async function popularTabelaAusencias(ausencias) {
  console.log(
    "[DEBUG] popularTabelaAusencias (ausenciasManager.js) chamada. Admin: " +
      IS_USER_ADMIN_AUSENCIAS
  );
  const corpoTabela = document.querySelector("#ausencias-table-main tbody");
  if (!corpoTabela) {
    console.error(
      "[DEBUG] Tabela de ausências (tbody) não encontrada (ausenciasManager.js)."
    );
    return;
  }
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-ausencias");
  if (chkAll) chkAll.checked = false;

  if (
    colaboradoresGlobais.length === 0 ||
    !colaboradoresGlobais[0] ||
    !colaboradoresGlobais[0].hasOwnProperty("id")
  ) {
    await buscarEArmazenarColaboradores();
  }

  if (!ausencias || ausencias.length === 0) {
    const r = corpoTabela.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = IS_USER_ADMIN_AUSENCIAS ? 5 : 4; // Ajuste a colSpan
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhuma ausência registrada para este período.";
    return;
  }

  ausencias.forEach((item) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-ausencia-id", item.id);

    if (IS_USER_ADMIN_AUSENCIAS) {
      const cellCheckbox = nLinha.insertCell();
      cellCheckbox.className = "p-2 text-center";
      const inputCheckbox = document.createElement("input");
      inputCheckbox.type = "checkbox";
      inputCheckbox.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`;
      inputCheckbox.value = item.id;
      cellCheckbox.appendChild(inputCheckbox);
    } else {
      nLinha.insertCell().className = "p-2 w-10 text-center"; // Célula vazia para manter alinhamento
    }

    const cellDataInicio = nLinha.insertCell();
    cellDataInicio.className = "p-1";
    const inputDataInicio = document.createElement("input");
    inputDataInicio.type = "date";
    inputDataInicio.className = `ausencia-data-inicio ${tailwindInputClasses}`;
    inputDataInicio.value = item.data_inicio || "";
    inputDataInicio.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellDataInicio.appendChild(inputDataInicio);

    const cellDataFim = nLinha.insertCell();
    cellDataFim.className = "p-1";
    const inputDataFim = document.createElement("input");
    inputDataFim.type = "date";
    inputDataFim.className = `ausencia-data-fim ${tailwindInputClasses}`;
    inputDataFim.value = item.data_fim || "";
    inputDataFim.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellDataFim.appendChild(inputDataFim);

    const cellColaborador = nLinha.insertCell();
    cellColaborador.className = "p-1";
    const selectColaborador = document.createElement("select");
    selectColaborador.className = `ausencia-colaborador ${tailwindSelectClasses}`;
    popularSelectColaborador(
      selectColaborador,
      item.colaborador_nome,
      colaboradoresGlobais
    );
    selectColaborador.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellColaborador.appendChild(selectColaborador);

    const cellObs = nLinha.insertCell();
    cellObs.className = "p-1";
    const inputObs = document.createElement("input");
    inputObs.type = "text";
    inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`;
    inputObs.value = item.observacoes || "";
    inputObs.placeholder = "Motivo/Observações da ausência";
    inputObs.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellObs.appendChild(inputObs);
  });
  console.log(
    `[DEBUG] ${ausencias.length} ausência(s) populada(s) na tabela (ausenciasManager.js).`
  );
  if (typeof initTooltips === "function") initTooltips(); // Re-init tooltips
}

function coletarDadosDaTabelaDeAusencias() {
  if (!IS_USER_ADMIN_AUSENCIAS) return []; // Não coleta se não for admin

  const linhas = document.querySelectorAll("#ausencias-table-main tbody tr");
  const dados = [];
  let erroValidacaoGeral = false;
  linhas.forEach((linha, index) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;
    const idOrig = linha.getAttribute("data-ausencia-id");
    const dataInicioIn = linha.querySelector(".ausencia-data-inicio");
    const dataFimIn = linha.querySelector(".ausencia-data-fim");
    const colaboradorSelect = linha.querySelector(".ausencia-colaborador");
    const observacoesIn = linha.querySelector(".ausencia-observacoes");

    const inicioVal = dataInicioIn ? dataInicioIn.value.trim() : "";
    const fimVal = dataFimIn ? dataFimIn.value.trim() : "";
    const colaboradorVal = colaboradorSelect ? colaboradorSelect.value : "";
    const obsVal = observacoesIn ? observacoesIn.value.trim() : "";

    if ((colaboradorVal || obsVal) && (!inicioVal || !fimVal)) {
      showToast(
        `Atenção (linha ${
          index + 1
        }): Datas de Início e Fim são obrigatórias se um Colaborador ou Observação for fornecido. Não será salvo.`,
        "warning",
        7000
      );
      erroValidacaoGeral = true;
      return;
    }

    if (inicioVal && fimVal) {
      if (new Date(fimVal) < new Date(inicioVal)) {
        showToast(
          `Atenção (linha ${index + 1}): Data Fim (${new Date(
            fimVal
          ).toLocaleDateString()}) não pode ser anterior à Data Início (${new Date(
            inicioVal
          ).toLocaleDateString()}) para '${
            colaboradorVal || obsVal || "ausência"
          }'. Não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeral = true;
        return;
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data_inicio: inicioVal,
        data_fim: fimVal,
        colaborador_nome: colaboradorVal,
        observacoes: obsVal,
      });
    } else if (colaboradorVal || obsVal) {
      if (!erroValidacaoGeral) {
        showToast(
          `Linha de ausência ${index + 1} incompleta (faltam datas) para '${
            colaboradorVal || obsVal
          }'. Não será salva.`,
          "warning",
          5000
        );
      }
      erroValidacaoGeral = true;
    }
  });
  if (erroValidacaoGeral && dados.length === 0) return [];
  if (erroValidacaoGeral && dados.length > 0) return null;
  return dados;
}

async function salvarDadosAusenciasNoServidor(dadosAusencias, csrfToken) {
  if (!IS_USER_ADMIN_AUSENCIAS) {
    showToast("Apenas administradores podem salvar ausências.", "error");
    return;
  }
  // ... (Resto da função salvarDadosAusenciasNoServidor como estava)
  const btnSalvar = document.getElementById("save-ausencias-button");
  const originalButtonHtml = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    /* ... spinner ... */
  }
  const payload = {
    acao: "salvar_ausencias",
    ausencias: dadosAusencias,
    csrf_token: csrfToken,
  };
  try {
    const response = await fetch("api/gerenciar_ausencias.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    let data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP: ${response.status}`);
    if (data.success) {
      showToast(data.message || "Ausências salvas com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-ausencias");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarAusenciasDoServidor(
        state.currentDisplayYearAusencias,
        state.currentDisplayMonthAusencias
      );
    } else {
      showToast(
        "Erro ao salvar ausências: " + (data.message || "Erro desconhecido."),
        "error"
      );
    }
  } catch (error) {
    /* ... tratamento de erro ... */
  } finally {
    if (btnSalvar) {
      /* ... restaurar botão ... */
    }
  }
}

async function excluirAusenciasNoServidor(ids, csrfToken) {
  if (!IS_USER_ADMIN_AUSENCIAS) {
    showToast("Apenas administradores podem excluir ausências.", "error");
    return;
  }
  // ... (Resto da função excluirAusenciasNoServidor como estava)
  if (!ids || ids.length === 0) {
    /* ... */ return;
  }
  if (!confirm(`Tem certeza que deseja excluir ${ids.length} ausência(ões)?`))
    return;
  try {
    const response = await fetch("api/gerenciar_ausencias.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_ausencias",
        ids_ausencias: ids,
        csrf_token: csrfToken,
      }),
    });
    let data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP: ${response.status}`);
    if (data.success) {
      showToast(data.message || "Ausência(s) excluída(s)!", "success");
      if (data.csrf_token) {
        /* ... atualizar token ... */
      }
      carregarAusenciasDoServidor(
        state.currentDisplayYearAusencias,
        state.currentDisplayMonthAusencias
      );
    } else {
      /* ... showToast erro ... */
    }
  } catch (error) {
    /* ... tratamento de erro ... */
  }
}

export async function carregarAusenciasDoServidor(ano, mes) {
  console.log(
    `[DEBUG] carregarAusenciasDoServidor (ausenciasManager.js) chamado com ano: ${ano}, mes: ${mes}`
  );
  const tableBody = document.querySelector("#ausencias-table-main tbody");
  const csrfTokenInput = document.getElementById("csrf-token-ausencias");
  if (!tableBody) {
    /* ... erro ... */ return;
  }
  if (
    IS_USER_ADMIN_AUSENCIAS &&
    !csrfTokenInput &&
    document.getElementById("save-ausencias-button")
  ) {
    /* ... warn ... */
  }

  tableBody.innerHTML = `<tr><td colspan="${
    IS_USER_ADMIN_AUSENCIAS ? 5 : 4
  }" class="p-2 text-center text-gray-500 text-sm">Carregando ausências (${mes}/${ano})... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  const url = `api/gerenciar_ausencias.php?ano=${ano}&mes=${mes}`;
  try {
    const response = await fetch(url);
    let data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP: ${response.status}`);
    if (data.success) {
      if (IS_USER_ADMIN_AUSENCIAS && csrfTokenInput && data.csrf_token) {
        csrfTokenInput.value = data.csrf_token;
      }
      if (colaboradoresGlobais.length === 0) {
        await buscarEArmazenarColaboradores();
      }
      popularTabelaAusencias(data.data || []);
    } else {
      /* ... showToast aviso ... */ popularTabelaAusencias([]);
    }
  } catch (error) {
    /* ... tratamento de erro ... */ popularTabelaAusencias([]);
  }
}

export function initAusenciasEventListeners() {
  const btnAddAusencia = document.getElementById("add-ausencia-row-button");
  if (btnAddAusencia) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      btnAddAusencia.addEventListener("click", async function () {
        // ... (Lógica do botão adicionar como estava, mas agora dentro do if)
        const tbody = document.querySelector("#ausencias-table-main tbody");
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
        const nLinha = tbody.insertRow(); /* ... criar linha ... */
        // Adicionar inputs desabilitados se não for admin já é feito em popularTabelaAusencias,
        // mas aqui criamos uma nova linha, então os inputs também precisam ser
        let cell = nLinha.insertCell();
        cell.className = "p-2 text-center";
        let inputChk = document.createElement("input");
        inputChk.type = "checkbox";
        inputChk.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`;
        cell.appendChild(inputChk);
        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputDI = document.createElement("input");
        inputDI.type = "date";
        inputDI.className = `ausencia-data-inicio ${tailwindInputClasses}`;
        cell.appendChild(inputDI);
        inputDI.focus();
        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputDF = document.createElement("input");
        inputDF.type = "date";
        inputDF.className = `ausencia-data-fim ${tailwindInputClasses}`;
        cell.appendChild(inputDF);
        cell = nLinha.insertCell();
        cell.className = "p-1";
        const selColabAusencia = document.createElement("select");
        selColabAusencia.className = `ausencia-colaborador ${tailwindSelectClasses}`;
        popularSelectColaborador(selColabAusencia, null, colaboradoresGlobais);
        cell.appendChild(selColabAusencia);
        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputObs = document.createElement("input");
        inputObs.type = "text";
        inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`;
        inputObs.placeholder = "Motivo/Observações";
        cell.appendChild(inputObs);
        if (typeof initTooltips === "function") initTooltips();
      });
    } else {
      btnAddAusencia.style.display = "none"; // Oculta se não for admin
    }
  }

  const btnSalvarAusencias = document.getElementById("save-ausencias-button");
  if (btnSalvarAusencias) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      btnSalvarAusencias.addEventListener("click", () => {
        /* ... lógica de salvar ... */
      });
    } else {
      btnSalvarAusencias.style.display = "none";
    }
  }

  const chkAllAus = document.getElementById("select-all-ausencias");
  if (chkAllAus) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      chkAllAus.addEventListener("change", () => {
        /* ... */
      });
    } else {
      chkAllAus.disabled = true; // Desabilita se não for admin
    }
  }

  const btnDelSelAus = document.getElementById(
    "delete-selected-ausencias-button"
  );
  if (btnDelSelAus) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      btnDelSelAus.addEventListener("click", () => {
        /* ... lógica de excluir ... */
      });
    } else {
      btnDelSelAus.style.display = "none";
    }
  }
}
