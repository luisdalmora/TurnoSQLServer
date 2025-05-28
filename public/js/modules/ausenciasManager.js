// src/js/modules/ausenciasManager.js
import {
  showToast,
  popularSelectColaborador,
  tailwindInputClasses,
  tailwindSelectClasses,
  tailwindCheckboxClasses,
  buscarEArmazenarColaboradores,
  todosOsColaboradores as colaboradoresGlobais,
  showConfirmationModal, // Adicionada importação
} from "./utils.js";
import * as state from "./state.js";
import { initTooltips } from "./tooltipManager.js";

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
    c.colSpan = IS_USER_ADMIN_AUSENCIAS ? 5 : 4;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhuma ausência registrada para este período.";
    return;
  }

  ausencias.forEach((item) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-ausencia-id", item.id);

    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    if (IS_USER_ADMIN_AUSENCIAS) {
      const inputCheckbox = document.createElement("input");
      inputCheckbox.type = "checkbox";
      inputCheckbox.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`; //
      inputCheckbox.value = item.id;
      cellCheckbox.appendChild(inputCheckbox);
    } else {
      cellCheckbox.className = "p-2 w-10 text-center";
    }

    const cellDataInicio = nLinha.insertCell();
    cellDataInicio.className = "p-1";
    const inputDataInicio = document.createElement("input");
    inputDataInicio.type = "date";
    inputDataInicio.className = `ausencia-data-inicio ${tailwindInputClasses}`; //
    inputDataInicio.value = item.data_inicio || "";
    inputDataInicio.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellDataInicio.appendChild(inputDataInicio);

    const cellDataFim = nLinha.insertCell();
    cellDataFim.className = "p-1";
    const inputDataFim = document.createElement("input");
    inputDataFim.type = "date";
    inputDataFim.className = `ausencia-data-fim ${tailwindInputClasses}`; //
    inputDataFim.value = item.data_fim || "";
    inputDataFim.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellDataFim.appendChild(inputDataFim);

    const cellColaborador = nLinha.insertCell();
    cellColaborador.className = "p-1";
    const selectColaborador = document.createElement("select");
    selectColaborador.className = `ausencia-colaborador ${tailwindSelectClasses}`; //
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
    inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`; //
    inputObs.value = item.observacoes || "";
    inputObs.placeholder = "Motivo/Observações da ausência";
    inputObs.disabled = !IS_USER_ADMIN_AUSENCIAS;
    cellObs.appendChild(inputObs);
  });
  console.log(
    `[DEBUG] ${ausencias.length} ausência(s) populada(s) na tabela (ausenciasManager.js).`
  );
  if (typeof initTooltips === "function") initTooltips();
}

function coletarDadosDaTabelaDeAusencias() {
  if (!IS_USER_ADMIN_AUSENCIAS) return [];

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

  const btnSalvar = document.getElementById("save-ausencias-button");
  const originalButtonHtml = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined")
      lucide.createIcons({ nodes: [btnSalvar.querySelector("i")] });
  }
  const payload = {
    acao: "salvar_ausencias",
    ausencias: dadosAusencias,
    csrf_token: csrfToken,
  };
  try {
    const response = await fetch("api/gerenciar_ausencias.php", {
      //
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
    console.error(
      "[DEBUG] Erro crítico ao salvar ausências (ausenciasManager.js):",
      error
    );
    showToast(`Erro crítico ao salvar ausências: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonHtml;
      if (typeof lucide !== "undefined")
        lucide.createIcons({ nodes: [btnSalvar.querySelector("i")] });
    }
  }
}

async function excluirAusenciasNoServidor(ids, csrfToken) {
  if (!IS_USER_ADMIN_AUSENCIAS) {
    showToast("Apenas administradores podem excluir ausências.", "error");
    return;
  }

  if (!ids || ids.length === 0) {
    showToast("Nenhum ID de ausência fornecido para exclusão.", "warning");
    return;
  }
  // A confirmação agora é feita externamente por showConfirmationModal
  try {
    const response = await fetch("api/gerenciar_ausencias.php", {
      //
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
        const csrfInput = document.getElementById("csrf-token-ausencias");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarAusenciasDoServidor(
        state.currentDisplayYearAusencias,
        state.currentDisplayMonthAusencias
      );
    } else {
      showToast(
        "Erro ao excluir ausência(s): " +
          (data.message || "Erro desconhecido."),
        "error"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro crítico ao excluir ausências (ausenciasManager.js):",
      error
    );
    showToast(`Erro crítico ao excluir ausências: ${error.message}.`, "error");
  }
}

export async function carregarAusenciasDoServidor(ano, mes) {
  console.log(
    `[DEBUG] carregarAusenciasDoServidor (ausenciasManager.js) chamado com ano: ${ano}, mes: ${mes}`
  );
  const tableBody = document.querySelector("#ausencias-table-main tbody");
  const csrfTokenInput = document.getElementById("csrf-token-ausencias");
  if (!tableBody) {
    console.error(
      "[DEBUG] Corpo da tabela de ausências não encontrado. (ausenciasManager.js)"
    );
    return;
  }
  if (
    IS_USER_ADMIN_AUSENCIAS &&
    !csrfTokenInput &&
    document.getElementById("save-ausencias-button")
  ) {
    console.warn(
      "[DEBUG] Input CSRF para ausências não encontrado, mas o botão de salvar existe. (ausenciasManager.js)"
    );
  }

  tableBody.innerHTML = `<tr><td colspan="${
    IS_USER_ADMIN_AUSENCIAS ? 5 : 4
  }" class="p-2 text-center text-gray-500 text-sm">Carregando ausências (${mes}/${ano})... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined")
    lucide.createIcons({ nodes: [tableBody.querySelector("i")] });

  const url = `api/gerenciar_ausencias.php?ano=${ano}&mes=${mes}`; //
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
      showToast(
        "Aviso: " + (data.message || "Não foi possível carregar ausências."),
        "warning"
      );
      popularTabelaAusencias([]);
    }
  } catch (error) {
    console.error(
      `[DEBUG] Erro ao carregar ausências para ${mes}/${ano} (ausenciasManager.js):`,
      error
    );
    showToast(
      `Erro ao carregar ausências: ${error.message}. Verifique o console.`,
      "error"
    );
    popularTabelaAusencias([]);
  }
}

export function initAusenciasEventListeners() {
  const btnAddAusencia = document.getElementById("add-ausencia-row-button");
  if (btnAddAusencia) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      btnAddAusencia.addEventListener("click", async function () {
        console.log(
          "[DEBUG] Botão 'Adicionar Ausência' clicado (ausenciasManager.js)."
        );
        const tbody = document.querySelector("#ausencias-table-main tbody");
        if (!tbody) return;

        const placeholderRow = tbody.querySelector(
          "td[colspan='5'], td[colspan='4']"
        );
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
        nLinha.className = "bg-white hover:bg-gray-50";
        nLinha.setAttribute("data-ausencia-id", newId);

        let cell;

        cell = nLinha.insertCell();
        cell.className = "p-2 text-center";
        if (IS_USER_ADMIN_AUSENCIAS) {
          let inputChk = document.createElement("input");
          inputChk.type = "checkbox";
          inputChk.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`; //
          inputChk.value = newId;
          cell.appendChild(inputChk);
        } else {
          cell.className = "p-2 w-10 text-center";
        }

        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputDI = document.createElement("input");
        inputDI.type = "date";
        inputDI.className = `ausencia-data-inicio ${tailwindInputClasses}`; //
        inputDI.disabled = !IS_USER_ADMIN_AUSENCIAS;
        cell.appendChild(inputDI);
        if (IS_USER_ADMIN_AUSENCIAS) inputDI.focus();

        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputDF = document.createElement("input");
        inputDF.type = "date";
        inputDF.className = `ausencia-data-fim ${tailwindInputClasses}`; //
        inputDF.disabled = !IS_USER_ADMIN_AUSENCIAS;
        cell.appendChild(inputDF);

        cell = nLinha.insertCell();
        cell.className = "p-1";
        const selColabAusencia = document.createElement("select");
        selColabAusencia.className = `ausencia-colaborador ${tailwindSelectClasses}`; //
        popularSelectColaborador(selColabAusencia, null, colaboradoresGlobais);
        selColabAusencia.disabled = !IS_USER_ADMIN_AUSENCIAS;
        cell.appendChild(selColabAusencia);

        cell = nLinha.insertCell();
        cell.className = "p-1";
        let inputObs = document.createElement("input");
        inputObs.type = "text";
        inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`; //
        inputObs.placeholder = "Motivo/Observações";
        inputObs.disabled = !IS_USER_ADMIN_AUSENCIAS;
        cell.appendChild(inputObs);

        if (typeof initTooltips === "function") initTooltips();
      });
    } else {
      btnAddAusencia.style.display = "none";
    }
  }

  const btnSalvarAusencias = document.getElementById("save-ausencias-button");
  if (btnSalvarAusencias) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      btnSalvarAusencias.addEventListener("click", () => {
        console.log(
          "[DEBUG] Botão 'Salvar Ausências' clicado (ausenciasManager.js)."
        );
        const csrfTokenEl = document.getElementById("csrf-token-ausencias");
        const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
        if (!csrfToken) {
          showToast(
            "Erro de segurança (token ausências ausente). Recarregue a página.",
            "error"
          );
          return;
        }
        const dados = coletarDadosDaTabelaDeAusencias();
        if (dados && dados.length > 0) {
          salvarDadosAusenciasNoServidor(dados, csrfToken);
        } else if (dados && dados.length === 0) {
          const tbody = document.querySelector("#ausencias-table-main tbody");
          const placeholderVisivel =
            tbody && tbody.querySelector("td[colspan='5'], td[colspan='4']");
          if (placeholderVisivel || (tbody && tbody.rows.length === 0)) {
            showToast("Adicione pelo menos uma ausência para salvar.", "info");
          } else {
            showToast(
              "Nenhuma ausência válida para salvar. Verifique as linhas.",
              "warning"
            );
          }
        } else if (dados === null) {
          console.log(
            "[DEBUG] Coleta de dados de ausências retornou null (erro de validação)."
          );
        }
      });
    } else {
      btnSalvarAusencias.style.display = "none";
    }
  }

  const chkAllAus = document.getElementById("select-all-ausencias");
  if (chkAllAus) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      chkAllAus.addEventListener("change", () => {
        document
          .querySelectorAll("#ausencias-table-main .ausencia-select-checkbox")
          .forEach((c) => (c.checked = chkAllAus.checked));
      });
    } else {
      chkAllAus.disabled = true;
      const thCheckbox = chkAllAus.closest("th");
      if (thCheckbox) thCheckbox.innerHTML = "";
    }
  }

  const btnDelSelAus = document.getElementById(
    "delete-selected-ausencias-button"
  );
  if (btnDelSelAus) {
    if (IS_USER_ADMIN_AUSENCIAS) {
      btnDelSelAus.addEventListener("click", () => {
        console.log(
          "[DEBUG] Botão 'Excluir Ausências Selecionadas' clicado (ausenciasManager.js)."
        );
        const csrfTokenEl = document.getElementById("csrf-token-ausencias");
        const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
        if (!csrfToken) {
          showToast("Erro de segurança. Recarregue a página.", "error");
          return;
        }
        const idsParaExcluirServidor = [];
        let linhasNovasRemovidasLocalmente = 0;
        const linhasParaRemoverLocalmente = [];

        document
          .querySelectorAll(
            "#ausencias-table-main .ausencia-select-checkbox:checked"
          )
          .forEach((c) => {
            const tr = c.closest("tr");
            if (tr) {
              const id = tr.getAttribute("data-ausencia-id");
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
          showToast("Nenhuma ausência selecionada para exclusão.", "info");
          return;
        }

        let confirmMessageAus = "";
        if (
          idsParaExcluirServidor.length > 0 &&
          linhasNovasRemovidasLocalmente > 0
        ) {
          confirmMessageAus = `Tem certeza que deseja excluir ${idsParaExcluirServidor.length} ausência(s) salva(s) e remover ${linhasNovasRemovidasLocalmente} linha(s) nova(s)? A exclusão das ausências salvas não pode ser desfeita.`;
        } else if (idsParaExcluirServidor.length > 0) {
          confirmMessageAus = `Tem certeza que deseja excluir ${idsParaExcluirServidor.length} ausência(s) salva(s)? Esta ação não pode ser desfeita.`;
        } else {
          confirmMessageAus = `Tem certeza que deseja remover ${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s))?`;
        }

        const chkAllAusenciasCurrent = document.getElementById(
          "select-all-ausencias"
        );

        showConfirmationModal(
          confirmMessageAus,
          () => {
            // Ação de confirmação
            if (idsParaExcluirServidor.length > 0) {
              excluirAusenciasNoServidor(idsParaExcluirServidor, csrfToken);
            }
            if (linhasNovasRemovidasLocalmente > 0) {
              linhasParaRemoverLocalmente.forEach((tr) => tr.remove());
              showToast(
                `${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s)) foram removida(s).`,
                "info"
              );
              const tbody = document.querySelector(
                "#ausencias-table-main tbody"
              );
              if (tbody && tbody.rows.length === 0) {
                popularTabelaAusencias([]);
              }
            }
            if (chkAllAusenciasCurrent) chkAllAusenciasCurrent.checked = false;
          },
          () => {
            showToast("Exclusão de ausências cancelada.", "info");
            document
              .querySelectorAll(
                "#ausencias-table-main .ausencia-select-checkbox:checked"
              )
              .forEach((c) => (c.checked = false));
            if (chkAllAusenciasCurrent) chkAllAusenciasCurrent.checked = false;
          }
        );
      });
    } else {
      btnDelSelAus.style.display = "none";
    }
  }
}
