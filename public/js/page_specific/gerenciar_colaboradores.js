// public/js/page_specific/gerenciar_colaboradores.js
import { showToast } from "../modules/utils.js";
import { initTooltips } from "../modules/tooltipManager.js";

// A variável window.APP_USER_ROLE é definida no header.php
const IS_USER_ADMIN_COLAB = window.APP_USER_ROLE === "admin";

document.addEventListener("DOMContentLoaded", function () {
  console.log(
    "[DEBUG] gerenciar_colaboradores.js: DOMContentLoaded. Admin: " +
      IS_USER_ADMIN_COLAB
  );

  const collaboratorsTableBody = document.querySelector(
    "#collaborators-table tbody"
  );
  const editModal = document.getElementById("edit-collaborator-modal");
  const editForm = document.getElementById("edit-collaborator-form");
  const modalCloseButton = document.getElementById("modal-close-btn");
  const cancelEditButton = document.getElementById("cancel-edit-colab-button");

  // Se não for admin, idealmente a página nem carregaria este conteúdo via PHP.
  // Mas como uma segunda barreira, podemos impedir a funcionalidade JS.
  if (!IS_USER_ADMIN_COLAB) {
    console.log(
      "[DEBUG] Usuário não admin acessando gerenciar_colaboradores.js. Funcionalidade limitada."
    );
    // Poderia desabilitar botões ou outras interações se o PHP não as removeu.
    // Por exemplo, o botão "Novo Colaborador" (se não removido pelo PHP):
    const novoColabBtn = document.querySelector(
      'a[href*="cadastrar_colaborador.php"]'
    );
    if (novoColabBtn) novoColabBtn.style.display = "none";
  }

  async function carregarColaboradoresNaTabela() {
    if (!collaboratorsTableBody) {
      /* ... */ return;
    }
    collaboratorsTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block"></i></td></tr>`;
    if (typeof lucide !== "undefined") lucide.createIcons();

    try {
      const response = await fetch(`api/listar_colaboradores.php`);
      const data = await response.json();
      collaboratorsTableBody.innerHTML = "";

      if (data.success && data.colaboradores) {
        if (data.colaboradores.length === 0) {
          collaboratorsTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Nenhum colaborador cadastrado. ${
            IS_USER_ADMIN_COLAB
              ? '<a href="cadastrar_colaborador.php" class="text-blue-600 hover:underline">Adicionar novo</a>.'
              : ""
          }</td></tr>`;
          return;
        }
        data.colaboradores.forEach((colab) => {
          const row = collaboratorsTableBody.insertRow();
          // ... (células de dados)
          row.setAttribute("data-colab-id", colab.id);
          row.insertCell().textContent = colab.id;
          row.insertCell().textContent = colab.nome_completo;
          row.insertCell().textContent = colab.email || "N/A";
          row.insertCell().textContent = colab.cargo || "N/A";

          const statusCell = row.insertCell();
          statusCell.innerHTML = colab.ativo
            ? '<span class="status-ativo px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>'
            : '<span class="status-inativo px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inativo</span>';
          statusCell.className = "px-6 py-4 whitespace-nowrap text-sm";

          const actionsCell = row.insertCell();
          actionsCell.className =
            "px-6 py-4 whitespace-nowrap text-right text-sm font-medium";

          if (IS_USER_ADMIN_COLAB) {
            // Ações apenas para Admin
            const editButton = document.createElement("button");
            editButton.innerHTML =
              '<i data-lucide="edit-3" class="w-4 h-4"></i>';
            editButton.className =
              "action-button info btn-sm text-indigo-600 hover:text-indigo-900 mr-3 p-1 transition-transform duration-150 ease-in-out hover:scale-110 active:scale-95";
            editButton.setAttribute("data-tooltip-text", "Editar Colaborador");
            editButton.onclick = () => abrirModalEdicao(colab);
            actionsCell.appendChild(editButton);

            const toggleStatusButton = document.createElement("button");
            toggleStatusButton.innerHTML = colab.ativo
              ? '<i data-lucide="toggle-left" class="w-4 h-4"></i>'
              : '<i data-lucide="toggle-right" class="w-4 h-4"></i>';
            toggleStatusButton.className = `action-button btn-sm p-1 transition-transform duration-150 ease-in-out hover:scale-110 active:scale-95 ${
              colab.ativo
                ? "text-yellow-600 hover:text-yellow-900"
                : "text-green-600 hover:text-green-900"
            }`;
            toggleStatusButton.setAttribute(
              "data-tooltip-text",
              colab.ativo ? "Desativar Colaborador" : "Ativar Colaborador"
            );
            toggleStatusButton.onclick = () =>
              alternarStatusColaborador(colab.id, !colab.ativo);
            actionsCell.appendChild(toggleStatusButton);
          } else {
            actionsCell.textContent = "N/A"; // Ou deixar em branco
          }
        });

        if (typeof lucide !== "undefined") lucide.createIcons();
        if (typeof initTooltips === "function") initTooltips();
      } else {
        // ... (tratamento de erro)
      }
    } catch (error) {
      // ... (tratamento de erro)
    }
  }

  function abrirModalEdicao(colaborador) {
    if (!IS_USER_ADMIN_COLAB) return; // Não abrir modal se não for admin
    // ... (resto da função abrirModalEdicao)
    if (!editModal || !editForm) {
      /* ... */ return;
    }
    editForm.reset();
    document.getElementById("edit-colab-id").value = colaborador.id;
    document.getElementById("edit-nome_completo").value =
      colaborador.nome_completo;
    document.getElementById("edit-email").value = colaborador.email || "";
    document.getElementById("edit-cargo").value = colaborador.cargo || "";
    const csrfTokenPageInput = document.getElementById(
      "csrf-token-colab-manage"
    );
    if (csrfTokenPageInput) {
      document.getElementById("edit-csrf-token").value =
        csrfTokenPageInput.value;
    }
    editModal.classList.remove("hidden");
    requestAnimationFrame(() => {
      const modalContent = document.getElementById(
        "edit-collaborator-modal-content"
      );
      if (modalContent) {
        modalContent.classList.remove("opacity-0", "scale-95");
        modalContent.classList.add("opacity-100", "scale-100");
      }
    });
    if (typeof lucide !== "undefined") lucide.createIcons();
    if (typeof initTooltips === "function") initTooltips();
  }

  function fecharModalEdicao() {
    // ... (código existente)
    if (!editModal) return;
    const modalContent = document.getElementById(
      "edit-collaborator-modal-content"
    );
    if (modalContent) {
      modalContent.classList.add("opacity-0", "scale-95");
      modalContent.classList.remove("opacity-100", "scale-100");
    }
    setTimeout(() => {
      editModal.classList.add("hidden");
    }, 300);
  }

  if (IS_USER_ADMIN_COLAB && editForm) {
    // Formulário de edição só para admin
    editForm.addEventListener("submit", async function (event) {
      // ... (código existente do submit)
    });
  } else if (editForm) {
    // Se o formulário existe mas o usuário não é admin, pode desabilitar o botão de salvar
    const saveBtn = document.getElementById("save-edit-colab-button");
    if (saveBtn) saveBtn.style.display = "none";
  }

  async function alternarStatusColaborador(colabId, novoStatusBool) {
    if (!IS_USER_ADMIN_COLAB) {
      showToast("Apenas administradores podem alterar o status.", "warning");
      return;
    }
    // ... (resto da função alternarStatusColaborador)
  }

  if (modalCloseButton)
    modalCloseButton.addEventListener("click", fecharModalEdicao);
  if (cancelEditButton && IS_USER_ADMIN_COLAB)
    cancelEditButton.addEventListener("click", fecharModalEdicao); // Botão de cancelar também só para admin se o modal for de edição

  if (editModal) {
    editModal.addEventListener("click", function (event) {
      if (event.target === editModal) {
        fecharModalEdicao();
      }
    });
  }

  if (collaboratorsTableBody) {
    carregarColaboradoresNaTabela();
  } else {
    /* ... */
  }
});
