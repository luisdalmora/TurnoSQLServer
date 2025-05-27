// public/js/page_specific/auth_forms.js
import { showToast } from "../modules/utils.js"; // Importa showToast

document.addEventListener("DOMContentLoaded", () => {
  // Efeito flutuante do placeholder
  document.querySelectorAll(".input-field").forEach((input) => {
    const checkValue = () => {
      input.classList.toggle("has-val", input.value.trim() !== "");
    };
    input.addEventListener("blur", checkValue);
    input.addEventListener("input", checkValue);
    checkValue();
  });

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  // Exibir mensagem de erro do login ou status (se houver via GET)
  const urlParams = new URLSearchParams(window.location.search);
  const erroLogin = urlParams.get("erro");
  if (erroLogin) {
    showToast(decodeURIComponent(erroLogin), "error");
    // Opcional: remover o parâmetro da URL após exibir o erro
    // window.history.replaceState({}, document.title, window.location.pathname.split('?')[0]);
  }
  const statusMsg = urlParams.get("status");
  if (statusMsg) {
    let message = "";
    let type = "info";
    if (statusMsg === "logout_success") {
      message = "Logout realizado com sucesso!";
      type = "success";
    } else if (statusMsg === "cadastro_sucesso_email_enviado") {
      message = "Cadastro realizado com sucesso! Verifique seu e-mail.";
      type = "success";
    } else if (statusMsg === "cadastro_sucesso") {
      message = "Cadastro realizado com sucesso!";
      type = "success";
    }

    if (message) {
      showToast(message, type, 5000);
    }
    // Opcional: remover o parâmetro da URL após exibir o erro
    // window.history.replaceState({}, document.title, window.location.pathname.split('?')[0]);
  }
});
