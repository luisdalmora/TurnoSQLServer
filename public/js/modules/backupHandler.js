// src/js/modules/backupHandler.js
import { showToast } from "./utils.js";
// const IS_USER_ADMIN_BACKUP = window.APP_USER_ROLE === 'admin'; // Não estritamente necessário aqui

console.log("[DEBUG] backupHandler.js: Módulo carregado.");

// ... (função showBackupModal como estava) ...

export function initBackupHandler() {
  const backupDbBtn = document.getElementById("backup-db-btn");
  const csrfTokenBackupInput = document.getElementById("csrf-token-backup");
  // ... (outros elementos do modal)

  // Se o botão backupDbBtn não existe (porque o usuário não é admin e o PHP o removeu),
  // esta função não fará nada, o que é o comportamento correto.
  if (backupDbBtn && csrfTokenBackupInput) {
    // ... (toda a lógica de initBackupHandler como estava) ...
    // O addEventListener só será adicionado se o botão existir.
  } else {
    if (!backupDbBtn)
      console.log(
        "[DEBUG] Botão de backup não encontrado, funcionalidade não inicializada (backupHandler.js)."
      );
    // Isso é esperado se o usuário não for admin.
  }
}
