// public/js/modules/backupHandler.js
import { showToast } from "./utils.js";

console.log("[DEBUG] backupHandler.js: Módulo carregado.");

function showBackupModal(
  title,
  message,
  showProgress = false,
  showClose = false,
  showDownload = false,
  downloadUrl = "",
  downloadFilename = ""
) {
  const modalBackdrop = document.getElementById("backup-modal-backdrop");
  const modalTitle = document.getElementById("backup-modal-title");
  const modalMessage = document.getElementById("backup-modal-message");
  const progressBarContainer = document.getElementById(
    "backup-progress-bar-container"
  );
  const progressBar = document.getElementById("backup-progress-bar");
  const downloadLink = document.getElementById("backup-download-link");
  const closeButton = document.getElementById("backup-modal-close-btn");

  if (
    !modalBackdrop ||
    !modalTitle ||
    !modalMessage ||
    !progressBarContainer ||
    !progressBar ||
    !downloadLink ||
    !closeButton
  ) {
    console.error("Elementos do modal de backup não encontrados.");
    showToast(
      "Erro ao exibir o status do backup: elementos do modal ausentes.",
      "error"
    );
    return;
  }

  modalTitle.textContent = title;
  modalMessage.innerHTML = message; // Usar innerHTML para permitir links ou formatação básica

  progressBarContainer.style.display = showProgress ? "block" : "none";
  if (showProgress) {
    progressBar.style.width = "0%";
    progressBar.textContent = "0%";
  }

  downloadLink.style.display = showDownload ? "inline-flex" : "none";
  if (showDownload && downloadUrl && downloadFilename) {
    downloadLink.href = downloadUrl;
    downloadLink.download = downloadFilename; // Sugere o nome do arquivo para download
    // downloadLink.setAttribute('data-tooltip-text', `Baixar ${downloadFilename}`); // Atualiza tooltip se necessário
  }

  closeButton.style.display = showClose ? "inline-flex" : "none";

  modalBackdrop.classList.remove("hidden");
  requestAnimationFrame(() => {
    const modalContent = modalBackdrop.querySelector(".modal-content-backup");
    if (modalContent) {
      modalContent.classList.remove("opacity-0", "scale-95");
      modalContent.classList.add("opacity-100", "scale-100");
    }
  });
}

function updateBackupProgress(percentage, message = null) {
  const progressBar = document.getElementById("backup-progress-bar");
  const modalMessage = document.getElementById("backup-modal-message");
  if (progressBar) {
    progressBar.style.width = `${percentage}%`;
    progressBar.textContent = `${percentage}%`;
  }
  if (message && modalMessage) {
    modalMessage.textContent = message;
  }
}

function hideBackupModal() {
  const modalBackdrop = document.getElementById("backup-modal-backdrop");
  if (modalBackdrop) {
    const modalContent = modalBackdrop.querySelector(".modal-content-backup");
    if (modalContent) {
      modalContent.classList.remove("opacity-100", "scale-100");
      modalContent.classList.add("opacity-0", "scale-95");
      modalContent.addEventListener(
        "transitionend",
        () => {
          modalBackdrop.classList.add("hidden");
        },
        { once: true }
      );
    } else {
      modalBackdrop.classList.add("hidden");
    }
  }
}

export function initBackupHandler() {
  const backupDbBtn = document.getElementById("backup-db-btn");
  const csrfTokenBackupInput = document.getElementById("csrf-token-backup"); // Input hidden na sidebar

  if (backupDbBtn && csrfTokenBackupInput) {
    backupDbBtn.addEventListener("click", async function (event) {
      event.preventDefault();
      console.log("[DEBUG] Botão de Backup BD clicado.");

      const csrfToken = csrfTokenBackupInput.value;
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token backup ausente). Recarregue a página.",
          "error"
        );
        return;
      }

      showBackupModal(
        "Backup do Banco de Dados",
        "Iniciando o processo de backup...",
        true
      );
      updateBackupProgress(10, "Preparando backup...");

      // Simulação de progresso antes da chamada real
      let progress = 10;
      const महिलाओंinterval = setInterval(() => {
        progress += Math.floor(Math.random() * 10) + 5;
        if (progress >= 70 && progress < 90) {
          // Pausa antes de realmente chamar a API
          updateBackupProgress(progress, "Quase lá, finalizando preparação...");
        } else if (progress < 90) {
          updateBackupProgress(progress, "Processando backup...");
        } else {
          // não atualiza aqui para não passar de 90 antes da resposta da API
        }
        if (progress >= 90) clearInterval(interval); // Para de simular antes de 90%
      }, 300);

      try {
        // Adiciona o token CSRF à URL da API de backup
        const apiUrl = `${
          window.BASE_URL
        }/api/backup_database.php?csrf_token=${encodeURIComponent(csrfToken)}`;

        // A API backup_database.php espera um GET com o token CSRF
        const response = await fetch(apiUrl, {
          method: "GET", // Ou POST se a API estiver esperando POST com corpo JSON
          headers: {
            Accept: "application/json",
            // Se fosse POST com JSON: 'Content-Type': 'application/json'
          },
          // Se fosse POST com JSON: body: JSON.stringify({ csrf_token: csrfToken })
        });

        clearInterval(interval); // Para a simulação de progresso
        const data = await response.json();

        if (response.ok && data.success) {
          updateBackupProgress(100, "Backup concluído com sucesso!");
          showToast(data.message || "Backup realizado com sucesso!", "success");
          showBackupModal(
            "Backup Concluído",
            `O arquivo de backup <strong>${
              data.fileName || "backup.bak"
            }</strong> está pronto para download.`,
            false, // hide progress
            true, // show close button
            true, // show download button
            data.download_url,
            data.fileName
          );
          // Atualiza o token CSRF na página se um novo foi enviado pela API
          if (data.csrf_token && csrfTokenBackupInput) {
            csrfTokenBackupInput.value = data.csrf_token;
          }
        } else {
          updateBackupProgress(
            progress > 10 ? progress : 50,
            `Falha no backup: ${data.message || "Erro desconhecido."}`
          ); // Mostra progresso até o ponto da falha
          showToast(
            `Falha no backup: ${data.message || "Erro desconhecido."}`,
            "error"
          );
          showBackupModal(
            "Falha no Backup",
            `Ocorreu um erro: ${
              data.message || "Não foi possível completar o backup."
            }`,
            false,
            true
          );
          if (data.csrf_token && csrfTokenBackupInput) {
            // Mesmo em erro, o token pode ter sido regenerado
            csrfTokenBackupInput.value = data.csrf_token;
          }
        }
      } catch (error) {
        clearInterval(interval);
        updateBackupProgress(
          progress > 10 ? progress : 50,
          "Erro de comunicação ao realizar o backup."
        );
        console.error("Erro ao realizar o backup:", error);
        showToast("Erro de comunicação ao realizar o backup.", "error");
        showBackupModal(
          "Erro de Backup",
          "Não foi possível conectar ao servidor para realizar o backup. Verifique sua conexão ou contate o suporte.",
          false,
          true
        );
      }
    });

    // Listener para o botão de fechar do modal de backup
    const modalCloseButton = document.getElementById("backup-modal-close-btn");
    if (modalCloseButton) {
      modalCloseButton.addEventListener("click", hideBackupModal);
    }
    // Opcional: fechar modal ao clicar no backdrop
    const modalBackdrop = document.getElementById("backup-modal-backdrop");
    if (modalBackdrop) {
      modalBackdrop.addEventListener("click", function (event) {
        if (event.target === modalBackdrop) {
          hideBackupModal();
        }
      });
    }
  } else {
    if (!backupDbBtn && window.APP_USER_ROLE === "admin") {
      // Só loga se for admin e o botão não existir (erro de HTML)
      console.warn(
        "[DEBUG] Botão de backup (backup-db-btn) não encontrado, funcionalidade não inicializada (backupHandler.js)."
      );
    } else if (!csrfTokenBackupInput && window.APP_USER_ROLE === "admin") {
      console.warn(
        "[DEBUG] Input CSRF para backup (csrf-token-backup) não encontrado, funcionalidade não inicializada (backupHandler.js)."
      );
    }
    // Se não for admin, é esperado que o botão/input não existam, então não loga como warning.
  }
}
