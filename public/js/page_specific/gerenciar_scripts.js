// public/js/page_specific/gerenciar_scripts.js
import { showToast, showConfirmationModal } from "../modules/utils.js";
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

  if (!IS_USER_ADMIN_SCRIPTS) {
    if (inputTitulo) inputTitulo.disabled = true;
    if (textareaConteudo) textareaConteudo.disabled = true;
    if (btnSalvarScript) btnSalvarScript.style.display = "none";
    if (btnLimparFormulario) btnLimparFormulario.style.display = "none";
    const sectionNovoScript = document
      .querySelector("form#form-novo-script")
      ?.closest("section");
    if (sectionNovoScript) sectionNovoScript.style.display = "none";
  }

  async function carregarScripts(termoPesquisa = "") {
    if (!listaScriptsContainer) return;
    listaScriptsContainer.innerHTML =
      '<p class="text-center text-gray-500 py-4">Carregando scripts... <i data-lucide="loader-circle" class="lucide-spin inline-block ml-2"></i></p>';
    if (typeof lucide !== "undefined")
      lucide.createIcons({ nodes: [listaScriptsContainer.querySelector("i")] });

    try {
      const response = await fetch(
        `api/api_scripts.php?search=${encodeURIComponent(termoPesquisa)}` //
      );
      const data = await response.json();
      if (data.success && data.scripts) {
        renderizarListaScripts(data.scripts);
        if (IS_USER_ADMIN_SCRIPTS && data.csrf_token && formNovoScript) {
          const csrfInput = formNovoScript.querySelector(
            'input[name="csrf_token"]'
          );
          if (csrfInput) csrfInput.value = data.csrf_token;
        }
      } else {
        listaScriptsContainer.innerHTML = `<p class="text-center text-red-500 py-4">Erro ao carregar scripts: ${
          data.message || "Falha na comunicação."
        }</p>`;
        showToast(data.message || "Erro ao carregar scripts.", "error");
      }
    } catch (error) {
      console.error("Erro ao buscar scripts:", error);
      listaScriptsContainer.innerHTML =
        '<p class="text-center text-red-500 py-4">Erro de conexão ao carregar scripts. Tente novamente.</p>';
      showToast("Erro de conexão ao carregar scripts.", "error");
    }
  }

  function renderizarListaScripts(scripts) {
    if (!listaScriptsContainer) return;
    if (scripts.length === 0) {
      listaScriptsContainer.innerHTML =
        '<p class="text-center text-gray-500 py-4">Nenhum script encontrado.</p>';
      return;
    }

    const table = document.createElement("table");
    table.className = "min-w-full divide-y divide-gray-200";
    table.innerHTML = `
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Atualizado em</th>
                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200"></tbody>
    `;
    const tbody = table.querySelector("tbody");

    scripts.forEach((script) => {
      const row = tbody.insertRow();
      row.className = "hover:bg-gray-50 transition-colors duration-150";

      const tituloCell = row.insertCell();
      tituloCell.className =
        "px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900";
      const tituloLink = document.createElement("a");
      tituloLink.href = "#";
      tituloLink.className =
        "text-blue-600 hover:text-blue-800 hover:underline";
      tituloLink.textContent = script.titulo;
      tituloLink.onclick = (e) => {
        e.preventDefault();
        carregarScriptParaVisualizacaoOuEdicao(script);
      };
      tituloCell.appendChild(tituloLink);

      const atualizadoCell = row.insertCell();
      atualizadoCell.className =
        "px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden md:table-cell";
      atualizadoCell.textContent =
        script.data_atualizacao_fmt || script.data_criacao_fmt || "N/A";

      const acoesCell = row.insertCell();
      acoesCell.className =
        "px-4 py-3 whitespace-nowrap text-right text-sm font-medium";
      if (IS_USER_ADMIN_SCRIPTS) {
        const btnEditar = document.createElement("button");
        btnEditar.innerHTML = '<i data-lucide="edit-3" class="w-4 h-4"></i>';
        btnEditar.className =
          "text-indigo-600 hover:text-indigo-900 mr-3 p-1 transition-transform duration-150 ease-in-out hover:scale-110 active:scale-95";
        btnEditar.setAttribute("data-tooltip-text", "Editar Script");
        btnEditar.onclick = () =>
          carregarScriptParaVisualizacaoOuEdicao(script, true);
        acoesCell.appendChild(btnEditar);

        const btnExcluir = document.createElement("button");
        btnExcluir.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i>';
        btnExcluir.className =
          "text-red-600 hover:text-red-800 p-1 transition-transform duration-150 ease-in-out hover:scale-110 active:scale-95";
        btnExcluir.setAttribute("data-tooltip-text", "Excluir Script");
        btnExcluir.onclick = () => excluirScript(script.id, script.titulo);
        acoesCell.appendChild(btnExcluir);
      } else {
        acoesCell.textContent = "N/A";
      }
    });

    listaScriptsContainer.innerHTML = "";
    listaScriptsContainer.appendChild(table);

    if (typeof lucide !== "undefined") lucide.createIcons();
    if (typeof initTooltips === "function") initTooltips();
  }

  function carregarScriptParaVisualizacaoOuEdicao(script, modoEdicao = false) {
    if (!inputTitulo || !textareaConteudo) return;

    inputTitulo.value = script.titulo;
    textareaConteudo.value = script.conteudo;

    if (IS_USER_ADMIN_SCRIPTS && modoEdicao) {
      if (inputScriptId) inputScriptId.value = script.id;
      if (btnLimparFormulario)
        btnLimparFormulario.style.display = "inline-flex";
      if (btnSalvarScript) {
        btnSalvarScript.innerHTML =
          '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Atualizar Script';
        if (typeof lucide !== "undefined")
          lucide.createIcons({ nodes: [btnSalvarScript.querySelector("i")] });
      }
      inputTitulo.disabled = false;
      textareaConteudo.disabled = false;
      inputTitulo.focus();

      const formSection = document
        .getElementById("form-novo-script")
        ?.closest("section");
      if (formSection) formSection.scrollIntoView({ behavior: "smooth" });
    } else {
      if (inputScriptId) inputScriptId.value = "";
      if (btnLimparFormulario) btnLimparFormulario.style.display = "none";
      if (btnSalvarScript && IS_USER_ADMIN_SCRIPTS) {
        btnSalvarScript.innerHTML =
          '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Salvar Novo Script';
        if (typeof lucide !== "undefined")
          lucide.createIcons({ nodes: [btnSalvarScript.querySelector("i")] });
      }

      inputTitulo.disabled = !IS_USER_ADMIN_SCRIPTS;
      textareaConteudo.disabled = !IS_USER_ADMIN_SCRIPTS;

      if (IS_USER_ADMIN_SCRIPTS && !modoEdicao) {
        // Se admin, mas clicou no título (visualizar), habilita para novo
        inputTitulo.disabled = false;
        textareaConteudo.disabled = false;
      }

      const formSection = document
        .getElementById("form-novo-script")
        ?.closest("section");
      if (formSection && modoEdicao)
        formSection.scrollIntoView({ behavior: "smooth" });
    }
  }

  function limparFormulario() {
    if (!IS_USER_ADMIN_SCRIPTS) return;
    if (formNovoScript) formNovoScript.reset();
    if (inputScriptId) inputScriptId.value = "";
    if (btnLimparFormulario) btnLimparFormulario.style.display = "none";
    if (btnSalvarScript) {
      btnSalvarScript.innerHTML =
        '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Salvar Script';
      if (typeof lucide !== "undefined")
        lucide.createIcons({ nodes: [btnSalvarScript.querySelector("i")] });
    }
    if (inputTitulo) inputTitulo.disabled = false;
    if (textareaConteudo) textareaConteudo.disabled = false;
    if (inputTitulo) inputTitulo.focus();
  }

  if (IS_USER_ADMIN_SCRIPTS && formNovoScript) {
    formNovoScript.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (!inputTitulo || !textareaConteudo || !btnSalvarScript) return;

      const titulo = inputTitulo.value.trim();
      const conteudo = textareaConteudo.value.trim();
      const scriptId = inputScriptId ? inputScriptId.value : null;
      const csrfTokenEl = formNovoScript.querySelector(
        'input[name="csrf_token"]'
      );
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;

      if (!titulo || !conteudo) {
        showToast("Título e Conteúdo são obrigatórios.", "warning");
        return;
      }
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token ausente). Recarregue a página.",
          "error"
        );
        return;
      }

      const originalButtonHtml = btnSalvarScript.innerHTML;
      btnSalvarScript.disabled = true;
      btnSalvarScript.innerHTML = scriptId
        ? '<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-2"></i> Atualizando...'
        : '<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-2"></i> Salvando...';
      if (typeof lucide !== "undefined")
        lucide.createIcons({ nodes: [btnSalvarScript.querySelector("i")] });

      try {
        const response = await fetch("api/api_scripts.php", {
          //
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            script_id: scriptId || null,
            titulo: titulo,
            conteudo: conteudo,
            csrf_token: csrfToken,
          }),
        });
        const data = await response.json();
        if (data.success) {
          showToast(data.message || "Script salvo com sucesso!", "success");
          limparFormulario();
          carregarScripts(inputPesquisaScript ? inputPesquisaScript.value : "");
          if (data.csrf_token && csrfTokenEl) {
            csrfTokenEl.value = data.csrf_token;
          }
        } else {
          showToast(data.message || "Erro ao salvar o script.", "error");
        }
      } catch (error) {
        console.error("Erro ao salvar script:", error);
        showToast("Erro de comunicação ao salvar o script.", "error");
      } finally {
        btnSalvarScript.disabled = false;

        if (!inputScriptId || !inputScriptId.value) {
          btnSalvarScript.innerHTML =
            '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Salvar Script';
        } else {
          btnSalvarScript.innerHTML =
            '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Atualizar Script';
        }
        if (typeof lucide !== "undefined")
          lucide.createIcons({ nodes: [btnSalvarScript.querySelector("i")] });
      }
    });
  }

  async function excluirScript(scriptId, scriptTitulo) {
    if (!IS_USER_ADMIN_SCRIPTS) {
      showToast("Apenas administradores podem excluir scripts.", "error");
      return;
    }

    const confirmMessageScript = `Tem certeza que deseja excluir o script "${scriptTitulo}"? Esta ação não pode ser desfeita.`;

    showConfirmationModal(
      confirmMessageScript,
      async () => {
        const csrfTokenEl = formNovoScript
          ? formNovoScript.querySelector('input[name="csrf_token"]')
          : null;
        const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;

        if (!csrfToken && IS_USER_ADMIN_SCRIPTS) {
          showToast(
            "Erro de segurança (token scripts ausente). Recarregue a página.",
            "error"
          );
          return;
        }

        try {
          const response = await fetch("api/api_scripts.php", {
            //
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
            showToast(
              data.message || "Script excluído com sucesso!",
              "success"
            );
            carregarScripts(
              inputPesquisaScript ? inputPesquisaScript.value : ""
            );

            if (inputScriptId && parseInt(inputScriptId.value) === scriptId) {
              limparFormulario();
            }
            if (data.csrf_token && csrfTokenEl) {
              csrfTokenEl.value = data.csrf_token;
            }
          } else {
            showToast(data.message || "Erro ao excluir o script.", "error");
          }
        } catch (error) {
          console.error("Erro ao excluir script:", error);
          showToast("Erro de comunicação ao excluir o script.", "error");
        }
      },
      () => {
        showToast("Exclusão do script cancelada.", "info");
      }
    );
  }

  if (IS_USER_ADMIN_SCRIPTS && btnLimparFormulario) {
    btnLimparFormulario.addEventListener("click", limparFormulario);
  }

  if (inputPesquisaScript) {
    inputPesquisaScript.addEventListener("keyup", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        carregarScripts(this.value);
      }, 300);
    });
  }

  carregarScripts();
  if (typeof initTooltips === "function") initTooltips();
});
