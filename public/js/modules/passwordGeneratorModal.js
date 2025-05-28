// public/js/modules/passwordGeneratorModal.js
import { showToast } from "./utils.js";

const pgSenhaGeradaInput = document.getElementById("pg-senhaGerada");
const pgGerarSenhaBtn = document.getElementById("pg-gerarSenhaBtn");
const pgCopiarSenhaBtn = document.getElementById("pg-copiarSenhaBtn");
const pgComprimentoInput = document.getElementById("pg-comprimento");
const pgComprimentoValor = document.getElementById("pg-comprimentoValor");
const pgIncluirMaiusculasInput = document.getElementById(
  "pg-incluirMaiusculas"
);
const pgIncluirNumerosInput = document.getElementById("pg-incluirNumeros");
const pgIncluirSimbolosInput = document.getElementById("pg-incluirSimbolos");
const pgForcaSenhaDiv = document.getElementById("pg-forcaSenha");

const caracteres = {
  minusculas: "abcdefghijklmnopqrstuvwxyz",
  maiusculas: "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
  numeros: "0123456789",
  simbolos: "!@#$%^&*()_+-=[]{}|;:,.<>?",
};

function gerarSenha() {
  if (
    !pgComprimentoInput ||
    !pgIncluirMaiusculasInput ||
    !pgIncluirNumerosInput ||
    !pgIncluirSimbolosInput ||
    !pgSenhaGeradaInput
  ) {
    // console.warn("Elementos do gerador de senha no modal não encontrados.");
    return;
  }

  const comprimento = parseInt(pgComprimentoInput.value);
  let charset = caracteres.minusculas;
  if (pgIncluirMaiusculasInput.checked) charset += caracteres.maiusculas;
  if (pgIncluirNumerosInput.checked) charset += caracteres.numeros;
  if (pgIncluirSimbolosInput.checked) charset += caracteres.simbolos;

  if (
    charset === caracteres.minusculas &&
    !pgIncluirMaiusculasInput.checked &&
    !pgIncluirNumerosInput.checked &&
    !pgIncluirSimbolosInput.checked
  ) {
    // Se só minúsculas estiver selecionado por padrão (ou se o usuário desmarcar tudo e sobrar só minúsculas implícito)
    // É bom garantir que pelo menos um conjunto de caracteres esteja ativo.
    // Para simplificar, se nada explícito estiver marcado, usamos apenas minúsculas.
    // Poderia forçar um tipo se o charset ficar vazio, mas o atual sempre terá minúsculas.
  }
  if (charset.length === 0) {
    showToast("Selecione ao menos um tipo de caractere!", "warning");
    pgSenhaGeradaInput.value = "";
    avaliarForcaSenha("");
    return;
  }

  let senha = "";
  for (let i = 0; i < comprimento; i++) {
    senha += charset.charAt(Math.floor(Math.random() * charset.length));
  }
  pgSenhaGeradaInput.value = senha;
  avaliarForcaSenha(senha);
}

function avaliarForcaSenha(senha) {
  if (!pgForcaSenhaDiv) return;

  let forca = 0;
  if (senha.length >= 8) forca++;
  if (senha.length >= 12) forca++;
  if (/[A-Z]/.test(senha)) forca++;
  if (/[0-9]/.test(senha)) forca++;
  if (/[^A-Za-z0-9]/.test(senha)) forca++;

  let textoForca = "";
  let corForca = "text-slate-500"; // Cor suave

  switch (forca) {
    case 0:
    case 1:
      textoForca = "Muito Fraca";
      corForca = "text-red-500";
      break;
    case 2:
      textoForca = "Fraca";
      corForca = "text-orange-500";
      break;
    case 3:
      textoForca = "Média";
      corForca = "text-yellow-500";
      break;
    case 4:
      textoForca = "Forte";
      corForca = "text-green-500";
      break;
    case 5:
      textoForca = "Muito Forte";
      corForca = "text-emerald-600"; // Cor mais forte para "Muito Forte"
      break;
    default:
      textoForca = "";
  }
  pgForcaSenhaDiv.textContent = `Força: ${textoForca}`;
  pgForcaSenhaDiv.className = `text-sm mt-2 h-6 ${corForca} font-medium`;
}

function copiarSenha() {
  if (!pgSenhaGeradaInput) return;
  const senha = pgSenhaGeradaInput.value;
  if (senha) {
    navigator.clipboard
      .writeText(senha)
      .then(() =>
        showToast("Senha copiada para a área de transferência!", "success")
      )
      .catch((err) => {
        console.error("Erro ao copiar senha: ", err);
        showToast("Erro ao copiar senha.", "error");
      });
  } else {
    showToast("Gere uma senha primeiro para copiar.", "info");
  }
}

export function initPasswordGeneratorModal() {
  if (
    !pgComprimentoInput ||
    !pgGerarSenhaBtn ||
    !pgCopiarSenhaBtn ||
    !pgComprimentoValor
  ) {
    // console.warn("Não foi possível inicializar todos os listeners do modal gerador de senhas.");
    return;
  }

  pgComprimentoInput.addEventListener("input", () => {
    if (pgComprimentoValor)
      pgComprimentoValor.textContent = pgComprimentoInput.value;
    gerarSenha(); // Gera nova senha ao mudar comprimento
  });

  [
    pgIncluirMaiusculasInput,
    pgIncluirNumerosInput,
    pgIncluirSimbolosInput,
  ].forEach((input) => {
    if (input) {
      input.addEventListener("change", gerarSenha); // Gera nova senha ao mudar opções
    }
  });

  pgGerarSenhaBtn.addEventListener("click", gerarSenha);
  pgCopiarSenhaBtn.addEventListener("click", copiarSenha);

  // Gera uma senha inicial ao abrir o modal (ou ao carregar o script)
  gerarSenha();
  console.log("Modal Gerador de Senhas inicializado.");
}
