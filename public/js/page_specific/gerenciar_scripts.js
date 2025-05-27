// src/js/page_specific/gerenciar_scripts.js
import { showToast } from "../modules/utils.js";
import { initTooltips } from "../modules/tooltipManager.js";

const IS_USER_ADMIN_SCRIPTS = window.APP_USER_ROLE === "admin";

document.addEventListener("DOMContentLoaded", function () {
  const formNovoScript = document.getElementById("form-novo-script");
  const inputScriptId = document.getElementById("script_id");
  const inputTitulo = document.getElementById("script-titulo");
  const textareaConteudo = document.getElementById("script-conteudo");
  const btnSalvarScript = document.getElementById("btn-salvar-script");
  const btnLimparFormulario = document.getElementById(
    "btn-limpar-formulario-script"
  );
  const listaScriptsContainer = document.getElementById(
    "lista-scripts-container"
  );
  const inputPesquisaScript = document.getElementById("input-pesquisa-script");

  let debounceTimer;

  // Desabilitar campos do formulário se não for admin
  if (!IS_USER_ADMIN_SCRIPTS) {
    if (inputTitulo) inputTitulo.disabled = true;
    if (textareaConteudo) textareaConteudo.disabled = true;
    if (btnSalvarScript) btnSalvarScript.style.display = "none";
    if (btnLimparFormulario) btnLimparFormulario.style.display = "none";
    // Ocultar a seção inteira de adicionar novo script
    const sectionNovoScript = document
      .querySelector("form#form-novo-script")
      .closest("section");
    if (sectionNovoScript) sectionNovoScript.style.display = "none";
  }

  async function carregarScripts(termoPesquisa = "") {
    if (!listaScriptsContainer) return;
    // ... (lógica de carregar scripts, a visualização é para todos)
    // ... (chamada fetch para api/api_scripts.php?search=...)
    // ... (renderizarListaScripts(data.scripts);)
    // ... (atualizar CSRF se admin)
    if (!listaScriptsContainer) return;
    listaScriptsContainer.innerHTML =
      '<p class="text-center text-gray-500">Carregando scripts...</p>';
    try {
      const response = await fetch(
        `api/api_scripts.php?search=${encodeURIComponent(termoPesquisa)}`
      );
      const data = await response.json();
      if (data.success && data.scripts) {
        renderizarListaScripts(data.scripts);
        if (IS_USER_ADMIN_SCRIPTS && data.csrf_token) {
          // Só atualiza token se for admin e o form estiver visível
          const csrfInput = formNovoScript.querySelector(
            'input[name="csrf_token"]'
          );
          if (csrfInput) csrfInput.value = data.csrf_token;
        }
      } else {
        /* ... erro ... */
      }
    } catch (error) {
      /* ... erro ... */
    }
  }

  function renderizarListaScripts(scripts) {
    // ... (lógica de renderizar, visualização para todos)
    // Ações de editar/excluir só aparecem se IS_USER_ADMIN_SCRIPTS for true
    if (!listaScriptsContainer) return;
    if (scripts.length === 0) {
      /* ... */ return;
    }
    const table = document.createElement("table"); /* ... */
    scripts.forEach((script) => {
      const row = tbody.insertRow(); /* ... */
      if (IS_USER_ADMIN_SCRIPTS) {
        // Coluna de ações apenas para admin
        const acoesCell = row.insertCell(); /* ... */
        const btnEditar =
          document.createElement(
            "button"
          ); /* ... onclick só chama carregarScriptParaEdicao se admin ... */
        btnEditar.onclick = () => {
          if (IS_USER_ADMIN_SCRIPTS) carregarScriptParaEdicao(script);
        };
        acoesCell.appendChild(btnEditar);
        const btnExcluir =
          document.createElement(
            "button"
          ); /* ... onclick só chama excluirScript se admin ... */
        btnExcluir.onclick = () => {
          if (IS_USER_ADMIN_SCRIPTS) excluirScript(script.id, script.titulo);
        };
        acoesCell.appendChild(btnExcluir);
      } else {
        // Adiciona uma célula vazia ou 'N/A' para a coluna de ações se não for admin
        const acoesCell = row.insertCell();
        acoesCell.className =
          "px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-400";
        acoesCell.textContent = "N/A";
      }
    });
    // ...
    if (typeof lucide !== "undefined") lucide.createIcons();
    if (typeof initTooltips === "function") initTooltips();
  }

  function carregarScriptParaEdicao(script) {
    if (!IS_USER_ADMIN_SCRIPTS) return;
    // ... (lógica existente)
  }

  function limparFormulario() {
    if (!IS_USER_ADMIN_SCRIPTS) return;
    // ... (lógica existente)
  }

  if (IS_USER_ADMIN_SCRIPTS && formNovoScript) {
    // Formulário e listeners só para admin
    formNovoScript.addEventListener("submit", async function (event) {
      // ... (lógica de submit como estava)
    });
  }

  async function excluirScript(scriptId, scriptTitulo) {
    if (!IS_USER_ADMIN_SCRIPTS) {
      showToast("Apenas administradores podem excluir scripts.", "error");
      return;
    }
    // ... (lógica de excluir como estava)
  }

  if (IS_USER_ADMIN_SCRIPTS && btnLimparFormulario) {
    btnLimparFormulario.addEventListener("click", limparFormulario);
  }

  if (inputPesquisaScript) {
    // Pesquisa é para todos
    inputPesquisaScript.addEventListener("keyup", function () {
      /* ... */
    });
  }

  carregarScripts(); // Carregamento inicial para todos
});
