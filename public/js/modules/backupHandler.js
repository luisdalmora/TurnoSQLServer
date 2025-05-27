// src/js/modules/backupHandler.js
import { showToast } from "./utils.js";
console.log("[DEBUG] backupHandler.js: Módulo carregado.");

function showBackupModal(
  message,
  isLoading,
  isError,
  isSuccess,
  elMsg,
  elProgressContainer,
  elProgress,
  elDownloadLink,
  elCloseBtn,
  elBackdrop,
  elModalContent // Adicionado para controlar animação do conteúdo
) {
  if (elBackdrop) {
    elBackdrop.classList.remove("hidden"); // Tornar o backdrop visível
  }

  if (elModalContent) {
    // Pequeno atraso ou forçar reflow para garantir que a transição ocorra
    requestAnimationFrame(() => {
      elModalContent.classList.remove("opacity-0", "scale-95");
      elModalContent.classList.add("opacity-100", "scale-100");
    });
  }

  if (elMsg) elMsg.textContent = message;

  if (elProgressContainer)
    elProgressContainer.style.display = isLoading ? "block" : "none";
  if (elProgress && isLoading) {
    elProgress.style.width = "0%"; // Reset para animação indeterminada
    // Para animação de progresso real, você precisaria de um loop ou eventos de progresso
    // Por agora, vamos simular um preenchimento rápido se for apenas visual.
    // Se você quer uma barra indeterminada, você pode adicionar/remover uma classe CSS para isso.
    // Para o efeito "indeterminate" que você tinha, uma classe CSS seria melhor:
    // .indeterminate { animation: progress-indeterminate 2s infinite linear; }
    // @keyframes progress-indeterminate { 0% { left: -100%; width: 100%; } 100% { left: 100%; width: 10%; } }
    // No JS: elProgress.classList.add("indeterminate"); elProgress.classList.remove("indeterminate");
    // Para simplificar e mostrar progresso visual:
    setTimeout(() => {
      if (isLoading && elProgress) elProgress.style.width = "100%";
    }, 100);
  } else if (elProgress) {
    // elProgress.classList.remove("indeterminate"); // se usando classe para indeterminado
  }

  if (elMsg) {
    elMsg.classList.remove("text-green-600", "text-red-600", "text-gray-700");
    if (isSuccess) elMsg.classList.add("text-green-600");
    else if (isError) elMsg.classList.add("text-red-600");
    else elMsg.classList.add("text-gray-700");
  }

  if (elDownloadLink) elDownloadLink.classList.add("hidden"); // Esconde por padrão
  if (elCloseBtn) elCloseBtn.style.display = "none"; // Esconde por padrão
}

export function initBackupHandler() {
  const backupDbBtn = document.getElementById("backup-db-btn");
  const csrfTokenBackupInput = document.getElementById("csrf-token-backup");
  const backupModalBackdrop = document.getElementById("backup-modal-backdrop");
  const backupModalMessage = document.getElementById("backup-modal-message");
  const backupModalCloseBtn = document.getElementById("backup-modal-close-btn");
  const backupProgressBarContainer = document.getElementById(
    "backup-progress-bar-container"
  );
  const backupProgressBar = document.getElementById("backup-progress-bar");
  const backupDownloadLink = document.getElementById("backup-download-link");

  // Obter o elemento de conteúdo do modal para animação
  const backupModalContent = backupModalBackdrop
    ? backupModalBackdrop.querySelector(".modal-content-backup")
    : null;

  let originalBackupBtnHTML = "";

  if (backupDbBtn && csrfTokenBackupInput) {
    originalBackupBtnHTML = backupDbBtn.innerHTML;

    if (backupModalCloseBtn && backupModalBackdrop && backupModalContent) {
      backupModalCloseBtn.addEventListener("click", () => {
        // Animação de saída
        if (backupModalContent) {
          backupModalContent.classList.add("opacity-0", "scale-95");
          backupModalContent.classList.remove("opacity-100", "scale-100");
        }

        // Adiciona um pequeno atraso para a animação antes de esconder o backdrop
        setTimeout(() => {
          if (backupModalBackdrop) backupModalBackdrop.classList.add("hidden");
        }, 300); // Ajuste este tempo para corresponder à sua duração de transição CSS

        if (backupDbBtn && originalBackupBtnHTML) {
          backupDbBtn.disabled = false;
          backupDbBtn.innerHTML = originalBackupBtnHTML;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
      });
    }

    backupDbBtn.addEventListener("click", async function (event) {
      event.preventDefault();
      if (backupDbBtn.disabled) return;
      if (
        !confirm("Tem certeza que deseja iniciar o backup do banco de dados?")
      )
        return;

      if (
        backupModalBackdrop &&
        backupModalMessage &&
        backupProgressBarContainer &&
        backupProgressBar &&
        backupDownloadLink &&
        backupModalCloseBtn &&
        backupModalContent // Checa se o conteúdo do modal existe
      ) {
        showBackupModal(
          "Iniciando backup, por favor aguarde...",
          true, // isLoading
          false,
          false,
          backupModalMessage,
          backupProgressBarContainer,
          backupProgressBar,
          backupDownloadLink,
          backupModalCloseBtn,
          backupModalBackdrop,
          backupModalContent // Passa o elemento de conteúdo
        );
      }

      backupDbBtn.disabled = true;
      backupDbBtn.innerHTML = `<i data-lucide="loader-circle" class="animate-spin w-4 h-4 mr-2"></i> Processando...`;
      if (typeof lucide !== "undefined") lucide.createIcons();
      const csrfToken = csrfTokenBackupInput.value;

      try {
        const response = await fetch("api/backup_database.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "create_backup",
            csrf_token_backup: csrfToken,
          }),
        });
        let data;
        if (!response.ok) {
          let errorMsg = `Servidor respondeu com erro: HTTP ${response.status}`;
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
          if (backupModalBackdrop && backupModalContent) {
            showBackupModal(
              data.message || "Backup concluído!",
              false, // isLoading
              false, // isError
              true, // isSuccess
              backupModalMessage,
              backupProgressBarContainer,
              backupProgressBar,
              backupDownloadLink,
              backupModalCloseBtn,
              backupModalBackdrop,
              backupModalContent // Passa o elemento de conteúdo
            );
            if (backupModalCloseBtn)
              backupModalCloseBtn.style.display = "inline-flex";
            if (data.download_url && backupDownloadLink) {
              backupDownloadLink.href = data.download_url;
              backupDownloadLink.classList.remove("hidden");
            } else if (
              data.filename &&
              !data.download_url &&
              backupDownloadLink
            ) {
              backupDownloadLink.href = `download_backup_file.php?file=${encodeURIComponent(
                data.filename
              )}`;
              backupDownloadLink.classList.remove("hidden");
            } else {
              if (backupDownloadLink)
                backupDownloadLink.classList.add("hidden");
              showToast(
                "URL de download não fornecida pelo servidor.",
                "warning"
              );
            }
          }
        } else {
          const errorMsg = data.message || "Falha no backup.";
          if (backupModalBackdrop && backupModalContent) {
            showBackupModal(
              "Erro: " + errorMsg,
              false, // isLoading
              true, // isError
              false, // isSuccess
              backupModalMessage,
              backupProgressBarContainer,
              backupProgressBar,
              backupDownloadLink,
              backupModalCloseBtn,
              backupModalBackdrop,
              backupModalContent // Passa o elemento de conteúdo
            );
            if (backupModalCloseBtn)
              backupModalCloseBtn.style.display = "inline-flex";
          }
          showToast("Falha no backup: " + errorMsg, "error", 7000);
        }
      } catch (error) {
        console.error(
          "[DEBUG] Erro requisição de backup (backupHandler.js):",
          error
        );
        if (backupModalBackdrop && backupModalContent) {
          showBackupModal(
            "Erro de comunicação ao tentar backup. Verifique o console.",
            false, // isLoading
            true, // isError
            false, // isSuccess
            backupModalMessage,
            backupProgressBarContainer,
            backupProgressBar,
            backupDownloadLink,
            backupModalCloseBtn,
            backupModalBackdrop,
            backupModalContent // Passa o elemento de conteúdo
          );
          if (backupModalCloseBtn)
            backupModalCloseBtn.style.display = "inline-flex";
        }
        showToast("Erro de comunicação: " + error.message, "error");
      }
    });
  } else {
    if (!backupDbBtn)
      console.warn(
        "[DEBUG] Botão de backup (backup-db-btn) não encontrado (backupHandler.js)."
      );
    if (!csrfTokenBackupInput)
      console.warn(
        "[DEBUG] Campo CSRF de backup (csrf-token-backup) não encontrado (backupHandler.js)."
      );
  }
}
