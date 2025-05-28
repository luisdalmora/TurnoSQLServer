// public/js/page_specific/gerador_senhas_script.js
import { showToast } from "../modules/utils.js";

function gerarSenha(automacao) {
  var dataAtual = new Date();
  var valorSenha =
    dataAtual.getFullYear() -
    (dataAtual.getMonth() + 1) -
    dataAtual.getDate() -
    dataAtual.getHours();

  if (!automacao) {
    valorSenha -= 3;
  }

  var senhaDebugInfo =
    dataAtual.getFullYear() +
    "-" +
    (dataAtual.getMonth() + 1) +
    "-" +
    dataAtual.getDate() +
    "-" +
    dataAtual.getHours() +
    (automacao ? "-Auto" : "-Pay");
  // console.log("Debug Info Senha:", senhaDebugInfo);

  setTimeout(() => {
    const displayContainer = document.getElementById(
      "senhaGeradaDisplayContainer"
    );
    const copiarBtn = document.getElementById("copiarSenha");
    if (displayContainer) displayContainer.classList.add("hidden");
    if (copiarBtn) copiarBtn.classList.add("hidden");
  }, 5000);

  showToast("Senha gerada com sucesso!", "success");

  const displayElement = document.getElementById("senhaGeradaDisplay");
  const displayContainer = document.getElementById(
    "senhaGeradaDisplayContainer"
  );
  const copiarBtn = document.getElementById("copiarSenha");

  if (displayElement) displayElement.textContent = valorSenha;
  if (displayContainer) displayContainer.classList.remove("hidden");
  if (copiarBtn) copiarBtn.classList.remove("hidden");
}

const senhaPayButton = document.getElementById("senhaPay");
if (senhaPayButton) {
  senhaPayButton.addEventListener("click", function () {
    gerarSenha(false);
  });
}

const senhaAutomacaoButton = document.getElementById("senhaAutomacao");
if (senhaAutomacaoButton) {
  senhaAutomacaoButton.addEventListener("click", function () {
    gerarSenha(true);
  });
}

const copiarSenhaButton = document.getElementById("copiarSenha");
if (copiarSenhaButton) {
  copiarSenhaButton.addEventListener("click", function () {
    const senhaTextoElement = document.getElementById("senhaGeradaDisplay");
    const senhaTexto = senhaTextoElement ? senhaTextoElement.textContent : null;

    if (senhaTexto) {
      navigator.clipboard
        .writeText(senhaTexto)
        .then(() => {
          showToast(`Senha "${senhaTexto}" copiada!`, "success");
        })
        .catch((err) => {
          console.error("Erro ao copiar senha: ", err);
          showToast("Erro ao copiar senha.", "error");

          try {
            var tempInput = document.createElement("input");
            tempInput.value = senhaTexto;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            showToast(`Senha "${senhaTexto}" copiada! (fallback)`, "success");
          } catch (e) {
            console.error("Erro no fallback de copiar senha: ", e);
            showToast("Erro ao copiar senha (fallback).", "error");
          }
        });
    } else {
      showToast("Nenhuma senha gerada para copiar.", "warning");
    }
  });
}

document.querySelectorAll("footer a").forEach((link) => {
  if (link.target !== "_blank") {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      window.open(link.href, "_blank");
    });
  }
});
