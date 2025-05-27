// src/js/modules/observacoesManager.js
import { showToast } from "./utils.js";
console.log("[DEBUG] observacoesManager.js: Módulo carregado.");

const IS_USER_ADMIN_OBS = window.APP_USER_ROLE === "admin";

async function carregarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenObsGeralInput = document.getElementById(
    "csrf-token-obs-geral"
  ); // Só existe para admin
  if (!textarea) return;
  // Não precisa de csrf para GET, mas o input só existirá se for admin (ver home.php)

  try {
    const response = await fetch("api/gerenciar_observacao_geral.php");
    let data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP ${response.status}`);
    if (data.success) {
      textarea.value = data.observacao || "";
      if (IS_USER_ADMIN_OBS && csrfTokenObsGeralInput && data.csrf_token) {
        csrfTokenObsGeralInput.value = data.csrf_token;
      }
    } else {
      showToast(data.message || "Erro ao carregar observação.", "error");
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro de conexão ao carregar observação (observacoesManager.js):",
      error
    );
    showToast(
      "Erro de conexão ao carregar observação: " + error.message,
      "error"
    );
  }
}

async function salvarObservacaoGeral() {
  if (!IS_USER_ADMIN_OBS) {
    showToast("Apenas administradores podem salvar observações.", "error");
    return;
  }

  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenInput = document.getElementById("csrf-token-obs-geral");
  const saveButton = document.getElementById("salvar-observacoes-gerais-btn");
  if (!textarea || !csrfTokenInput || !saveButton) return; // csrfTokenInput e saveButton só existem para admin

  // ... (Resto da função salvarObservacaoGeral como estava)
  const originalButtonHtml = saveButton.innerHTML;
  saveButton.disabled = true; /* ... spinner ... */
  const payload = {
    observacao: textarea.value,
    csrf_token: csrfTokenInput.value,
  };
  try {
    const response = await fetch("api/gerenciar_observacao_geral.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    let data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP ${response.status}`);
    if (data.success) {
      showToast(data.message || "Observação salva!", "success");
      if (data.csrf_token) csrfTokenInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao salvar observação.", "error");
    }
  } catch (error) {
    /* ... tratamento de erro ... */
  } finally {
    saveButton.disabled = false;
    saveButton.innerHTML = originalButtonHtml; /* ... lucide ... */
  }
}

export function initObservacoesEventListeners() {
  const salvarObsBtn = document.getElementById("salvar-observacoes-gerais-btn");
  const obsGeralTextarea = document.getElementById(
    "observacoes-gerais-textarea"
  );

  if (obsGeralTextarea) {
    // Textarea sempre existe, mas pode ser readonly
    carregarObservacaoGeral(); // Carrega para todos
    if (!IS_USER_ADMIN_OBS) {
      obsGeralTextarea.readOnly = true; // PHP já deve fazer isso
    }
  }

  if (salvarObsBtn) {
    // Botão só existe para admin (ver home.php)
    if (IS_USER_ADMIN_OBS) {
      salvarObsBtn.addEventListener("click", salvarObservacaoGeral);
    } else {
      salvarObsBtn.style.display = "none"; // Redundante se PHP já oculta
    }
  }
}
