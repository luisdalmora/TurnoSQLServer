function gerarSenha(automacao) {
  // Gera a senha
  var dataAtual = new Date();
  var valorSenha =
    dataAtual.getFullYear() -
    (dataAtual.getMonth() + 1) -
    dataAtual.getDate() -
    dataAtual.getHours();

  if (!automacao) {
    valorSenha -= 3;
  }

  // A variável 'senha' com o formato de data/hora não está sendo usada para exibição,
  // mas pode ser útil para debug ou logs futuros, se necessário.
  var senhaDebugInfo =
    dataAtual.getFullYear() +
    "-" +
    (dataAtual.getMonth() + 1) +
    "-" +
    dataAtual.getDate() +
    "-" +
    dataAtual.getHours() +
    (automacao ? "-Auto" : "-Pay");
  // console.log("Debug Info Senha:", senhaDebugInfo); // Descomente para debug

  // Define um temporizador de 5 segundos
  setTimeout(() => {
    // Apaga o resultado
    $('#senhaGeradaDisplayContainer').addClass('hidden');
    $('#copiarSenha').addClass('hidden');
  }, 5000);

  // Mostra uma notificação de sucesso com Toastr
  toastr.success('Senha gerada com sucesso!');

  // Mostra a senha
  document.getElementById("senhaGeradaDisplay").textContent = valorSenha;
  $('#senhaGeradaDisplayContainer').removeClass('hidden');
  $('#copiarSenha').removeClass('hidden');
}

// ------ ESTES EVENT LISTENERS DEVEM ESTAR FORA DA FUNÇÃO gerarSenha ------
document.getElementById("senhaPay").addEventListener("click", function () {
  gerarSenha(false);
});

document
  .getElementById("senhaAutomacao")
  .addEventListener("click", function () {
    gerarSenha(true);
  });
// -----------------------------------------------------------------------

// Função para copiar a senha
document.getElementById("copiarSenha").addEventListener("click", function () {
  var senhaTexto = document.getElementById("senhaGeradaDisplay").textContent;

  if (senhaTexto) {
    var tempInput = document.createElement("input");
    tempInput.value = senhaTexto;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);

    toastr.success(senhaTexto, "Senha Copiada");
  } else {
    toastr.error("Nenhuma senha gerada para copiar.");
  }
});

// Lógica para abrir links em nova aba.
// É uma boa prática tornar isso mais específico para não afetar todos os links da página,
// especialmente se este script for usado em páginas com navegação interna.
// Se os links no footer já possuem target="_blank", este código é redundante para eles.
// Para o contexto atual da página gerador_senhas.php, que tem links no footer:
document.querySelectorAll("footer a").forEach((link) => { // Alterado para ser mais específico ao footer
  if (link.target !== "_blank") { // Só adiciona o listener se não tiver target="_blank"
    link.addEventListener("click", function (event) {
      event.preventDefault(); // Previne o comportamento padrão
      window.open(link.href, "_blank"); // Abre em nova aba
    });
  }
});