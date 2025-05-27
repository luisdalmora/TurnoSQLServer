// src/js/gerenciar_scripts.js
import { showToast } from "../modules/utils.js";

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

  async function carregarScripts(termoPesquisa = "") {
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
        if (data.csrf_token) {
          const csrfInput = formNovoScript.querySelector(
            'input[name="csrf_token"]'
          );
          if (csrfInput) csrfInput.value = data.csrf_token;
        }
      } else {
        listaScriptsContainer.innerHTML = `<p class="text-center text-red-500">${
          data.message || "Erro ao carregar scripts."
        }</p>`;
        showToast(data.message || "Erro ao carregar scripts.", "error");
      }
    } catch (error) {
      console.error("Erro ao buscar scripts:", error);
      listaScriptsContainer.innerHTML =
        '<p class="text-center text-red-500">Erro de conexão ao carregar scripts.</p>';
      showToast("Erro de conexão ao carregar scripts.", "error");
    }
  }

  function renderizarListaScripts(scripts) {
    if (!listaScriptsContainer) return;
    if (scripts.length === 0) {
      listaScriptsContainer.innerHTML =
        '<p class="text-center text-gray-500">Nenhum script encontrado.</p>';
      return;
    }

    const table = document.createElement("table");
    table.className = "min-w-full divide-y divide-gray-200";
    const thead = table.createTHead();
    thead.className = "bg-gray-50";
    const headerRow = thead.insertRow();
    ["Título", "Conteúdo (Prévia)", "Criação", "Ações"].forEach((text) => {
      const th = document.createElement("th");
      th.scope = "col";
      th.className =
        "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider";
      th.textContent = text;
      headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = "bg-white divide-y divide-gray-200";

    scripts.forEach((script) => {
      const row = tbody.insertRow();
      row.insertCell().textContent = script.titulo;

      const conteudoCell = row.insertCell();
      conteudoCell.className = "px-4 py-2 text-sm text-gray-500";
      const previaConteudoDiv = document.createElement("div");
      previaConteudoDiv.className = "script-content-display overflow-hidden"; // Adiciona estilo para quebra de linha
      previaConteudoDiv.textContent =
        script.conteudo.substring(0, 150) +
        (script.conteudo.length > 150 ? "..." : "");
      conteudoCell.appendChild(previaConteudoDiv);

      row.insertCell().textContent = script.data_criacao_fmt;

      const acoesCell = row.insertCell();
      acoesCell.className = "px-4 py-2 whitespace-nowrap text-sm font-medium";

      const btnEditar = document.createElement("button");
      btnEditar.innerHTML = '<i data-lucide="edit-2" class="w-4 h-4"></i>';
      btnEditar.title = "Editar Script";
      btnEditar.className = "text-indigo-600 hover:text-indigo-900 mr-3 p-1";
      btnEditar.onclick = () => carregarScriptParaEdicao(script);
      acoesCell.appendChild(btnEditar);

      const btnExcluir = document.createElement("button");
      btnExcluir.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i>';
      btnExcluir.title = "Excluir Script";
      btnExcluir.className = "text-red-600 hover:text-red-900 p-1";
      btnExcluir.onclick = () => excluirScript(script.id, script.titulo);
      acoesCell.appendChild(btnExcluir);
    });

    listaScriptsContainer.innerHTML = "";
    listaScriptsContainer.appendChild(table);
    if (typeof lucide !== "undefined") {
      lucide.createIcons();
    }
  }

  function carregarScriptParaEdicao(script) {
    inputScriptId.value = script.id;
    inputTitulo.value = script.titulo;
    textareaConteudo.value = script.conteudo;
    btnSalvarScript.innerHTML =
      '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Atualizar Script';
    btnLimparFormulario.style.display = "inline-flex";
    inputTitulo.focus();
    if (typeof lucide !== "undefined") {
      lucide.createIcons();
    }
  }

  function limparFormulario() {
    formNovoScript.reset();
    inputScriptId.value = "";
    btnSalvarScript.innerHTML =
      '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Salvar Script';
    btnLimparFormulario.style.display = "none";
    if (typeof lucide !== "undefined") {
      lucide.createIcons();
    }
  }

  if (formNovoScript) {
    formNovoScript.addEventListener("submit", async function (event) {
      event.preventDefault();
      const titulo = inputTitulo.value.trim();
      const conteudo = textareaConteudo.value.trim();
      const scriptId = inputScriptId.value.trim();
      const csrfToken = formNovoScript.querySelector(
        'input[name="csrf_token"]'
      ).value;

      if (!titulo || !conteudo) {
        showToast("Título e Conteúdo são obrigatórios.", "warning");
        return;
      }

      const originalButtonHtml = btnSalvarScript.innerHTML;
      btnSalvarScript.disabled = true;
      btnSalvarScript.innerHTML = `<i data-lucide="loader-circle" class="animate-spin w-4 h-4 mr-2"></i> Salvando...`;
      if (typeof lucide !== "undefined") {
        lucide.createIcons();
      }

      const payload = {
        titulo: titulo,
        conteudo: conteudo,
        csrf_token: csrfToken,
      };
      if (scriptId) {
        payload.script_id = scriptId;
      }

      try {
        const response = await fetch("api/api_scripts.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        const data = await response.json();

        if (data.success) {
          showToast(data.message || "Script salvo com sucesso!", "success");
          limparFormulario();
          carregarScripts(); // Recarrega a lista
          if (data.csrf_token) {
            formNovoScript.querySelector('input[name="csrf_token"]').value =
              data.csrf_token;
          }
        } else {
          showToast(data.message || "Erro ao salvar script.", "error");
          if (data.csrf_token) {
            // Atualiza o token mesmo em erro, se enviado
            formNovoScript.querySelector('input[name="csrf_token"]').value =
              data.csrf_token;
          }
        }
      } catch (error) {
        console.error("Erro ao salvar script:", error);
        showToast("Erro de conexão ao salvar script.", "error");
      } finally {
        btnSalvarScript.disabled = false;
        btnSalvarScript.innerHTML = originalButtonHtml; // Restaura o texto original do botão
        // Se o ID ainda estiver preenchido, significa que estava editando, então mantém o botão "Atualizar"
        if (inputScriptId.value) {
          btnSalvarScript.innerHTML =
            '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Atualizar Script';
        }
        if (typeof lucide !== "undefined") {
          lucide.createIcons();
        }
      }
    });
  }

  async function excluirScript(scriptId, scriptTitulo) {
    if (
      !confirm(
        `Tem certeza que deseja excluir o script "${scriptTitulo}"? Esta ação não pode ser desfeita.`
      )
    ) {
      return;
    }
    const csrfToken = formNovoScript.querySelector(
      'input[name="csrf_token"]'
    ).value;
    try {
      const response = await fetch("api/api_scripts.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "excluir",
          script_id: scriptId,
          csrf_token: csrfToken,
        }),
      });
      const data = await response.json();
      if (data.success) {
        showToast(data.message || "Script excluído com sucesso!", "success");
        carregarScripts(); // Recarrega a lista
        if (inputScriptId.value == scriptId) {
          // Limpa form se o script excluído estava em edição
          limparFormulario();
        }
        if (data.csrf_token) {
          formNovoScript.querySelector('input[name="csrf_token"]').value =
            data.csrf_token;
        }
      } else {
        showToast(data.message || "Erro ao excluir script.", "error");
        if (data.csrf_token) {
          formNovoScript.querySelector('input[name="csrf_token"]').value =
            data.csrf_token;
        }
      }
    } catch (error) {
      console.error("Erro ao excluir script:", error);
      showToast("Erro de conexão ao excluir script.", "error");
    }
  }

  if (btnLimparFormulario) {
    btnLimparFormulario.addEventListener("click", limparFormulario);
  }

  if (inputPesquisaScript) {
    inputPesquisaScript.addEventListener("keyup", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        carregarScripts(this.value.trim());
      }, 300); // Atraso de 300ms para debounce
    });
  }

  // Carregamento inicial dos scripts
  carregarScripts();
});
