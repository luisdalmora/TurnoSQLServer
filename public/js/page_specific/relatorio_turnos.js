// public/js/page_specific/relatorio_turnos.js
import {
  showToast,
  buscarEArmazenarColaboradores,
  popularSelectColaborador,
} from "../modules/utils.js";
import { initTooltips } from "../modules/tooltipManager.js"; // Importar para tooltips nos botões

document.addEventListener("DOMContentLoaded", function () {
  console.log("[DEBUG] relatorio_turnos.js: DOMContentLoaded");

  const reportFiltersForm = document.getElementById("report-filters-form");
  const filtroColaboradorSelect = document.getElementById("filtro-colaborador");
  const reportTableBody = document.querySelector("#report-table tbody");
  const reportSummaryDiv = document.getElementById("report-summary");
  const generateReportButton = document.getElementById(
    "generate-report-button"
  );
  const csrfTokenReportPage = document.getElementById("csrf-token-reports");
  const exportCsvButton = document.getElementById("export-csv-button");
  const exportPdfButton = document.getElementById("export-pdf-button");

  // Guardar os filtros atuais para usar na exportação
  let currentFilters = {
    data_inicio: "",
    data_fim: "",
    colaborador: "",
    csrf_token: "",
  };

  async function carregarColaboradoresParaFiltroRelatorio() {
    // ... (código existente)
  }

  function exibirDadosRelatorio(turnos, totalHoras, totalTurnos) {
    // ... (código existente)
    if (typeof initTooltips === "function") initTooltips(); // Se a tabela tiver tooltips dinâmicos
  }

  function getCurrentFiltersForExport() {
    const dataInicio = document.getElementById("filtro-data-inicio").value;
    const dataFim = document.getElementById("filtro-data-fim").value;
    const colaborador = filtroColaboradorSelect
      ? filtroColaboradorSelect.value
      : "";
    const csrfToken = csrfTokenReportPage ? csrfTokenReportPage.value : null;

    if (!dataInicio || !dataFim) {
      showToast(
        "Por favor, gere um relatório primeiro para definir o período de exportação.",
        "warning"
      );
      return null;
    }
    if (new Date(dataInicio) > new Date(dataFim)) {
      showToast(
        "A Data Início não pode ser posterior à Data Fim para exportação.",
        "warning"
      );
      return null;
    }
    if (!csrfToken) {
      showToast(
        "Erro de segurança (token ausente). Recarregue a página.",
        "error"
      );
      return null;
    }
    return {
      data_inicio: dataInicio,
      data_fim: dataFim,
      colaborador: colaborador,
      csrf_token: csrfToken,
    };
  }

  if (reportFiltersForm) {
    reportFiltersForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      console.log(
        "[DEBUG] relatorio_turnos.js: Formulário de filtros submetido."
      );

      const originalButtonHtml = generateReportButton
        ? generateReportButton.innerHTML
        : "";
      if (generateReportButton) {
        /* ... spinner ... */
      }

      const dataInicio = document.getElementById("filtro-data-inicio").value;
      const dataFim = document.getElementById("filtro-data-fim").value;
      const colaborador = filtroColaboradorSelect
        ? filtroColaboradorSelect.value
        : "";
      const csrfToken = csrfTokenReportPage ? csrfTokenReportPage.value : null;

      if (!dataInicio || !dataFim) {
        /* ... erro ... */ return;
      }
      if (new Date(dataInicio) > new Date(dataFim)) {
        /* ... erro ... */ return;
      }
      if (!csrfToken) {
        /* ... erro ... */ return;
      }

      // Armazena os filtros atuais
      currentFilters = {
        data_inicio: dataInicio,
        data_fim: dataFim,
        colaborador: colaborador,
        csrf_token: csrfToken,
      };

      const params = new URLSearchParams(currentFilters);

      if (reportTableBody) {
        /* ... buscando dados ... */
      }

      try {
        const response = await fetch(
          `api/gerar_relatorio_turnos.php?${params.toString()}`
        );
        const data = await response.json();
        if (!response.ok)
          throw new Error(data.message || `Erro HTTP: ${response.status}`);
        if (data.success) {
          exibirDadosRelatorio(
            data.turnos,
            data.total_geral_horas,
            data.total_turnos
          );
          if (data.csrf_token && csrfTokenReportPage) {
            csrfTokenReportPage.value = data.csrf_token;
            currentFilters.csrf_token = data.csrf_token; // Atualiza o token nos filtros guardados
          }
        } else {
          /* ... erro ... */
        }
      } catch (error) {
        /* ... erro ... */
      } finally {
        if (generateReportButton) {
          /* ... restaurar botão ... */
        }
      }
    });
  } else {
    /* ... warn ... */
  }

  if (exportCsvButton) {
    exportCsvButton.addEventListener("click", function () {
      console.log("[DEBUG] Botão Exportar CSV clicado.");
      const filtersForExport = getCurrentFiltersForExport();
      if (!filtersForExport) return;

      const params = new URLSearchParams(filtersForExport);
      params.append("export", "csv");

      // Abre em uma nova aba ou dispara download diretamente
      window.open(
        `api/gerar_relatorio_turnos.php?${params.toString()}`,
        "_blank"
      );
      showToast("Preparando CSV para download...", "info");
    });
  }

  if (exportPdfButton) {
    exportPdfButton.addEventListener("click", function () {
      console.log("[DEBUG] Botão Exportar PDF clicado.");
      const filtersForExport = getCurrentFiltersForExport();
      if (!filtersForExport) return;

      // Verificação de biblioteca PDF (simulada)
      // No mundo real, você pode não precisar verificar isso no JS se o backend sempre tentar gerar
      if (true) {
        // Assumindo que o backend tentará gerar o PDF
        const params = new URLSearchParams(filtersForExport);
        params.append("export", "pdf");
        window.open(
          `api/gerar_relatorio_turnos.php?${params.toString()}`,
          "_blank"
        );
        showToast("Preparando PDF para download...", "info");
      } else {
        showToast(
          "Funcionalidade de exportar para PDF não está configurada no servidor.",
          "warning"
        );
      }
    });
  }

  if (document.getElementById("report-filters-form")) {
    carregarColaboradoresParaFiltroRelatorio();
    const hoje = new Date();
    const primeiroDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    const dataInicioInput = document.getElementById("filtro-data-inicio");
    const dataFimInput = document.getElementById("filtro-data-fim");
    if (dataInicioInput) dataInicioInput.valueAsDate = primeiroDiaDoMes;
    if (dataFimInput) dataFimInput.valueAsDate = ultimoDiaDoMes;

    // Preenche currentFilters com os valores iniciais dos campos de data
    if (dataInicioInput && dataFimInput && csrfTokenReportPage) {
      currentFilters = {
        data_inicio: dataInicioInput.value,
        data_fim: dataFimInput.value,
        colaborador: filtroColaboradorSelect
          ? filtroColaboradorSelect.value
          : "",
        csrf_token: csrfTokenReportPage.value,
      };
    }
  }
  if (typeof initTooltips === "function") initTooltips(); // Inicializa tooltips nos botões de exportação
});
