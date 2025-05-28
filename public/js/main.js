// public/js/main.js (Versão de Diagnóstico MÍNIMO ABSOLUTO)

console.log(
  "[DIAGNÓSTICO MÍNIMO] main.js: Script iniciado (nível mais básico)."
);

document.addEventListener("DOMContentLoaded", function () {
  console.log("[DIAGNÓSTICO MÍNIMO] DOMContentLoaded disparado.");

  if (document.body) {
    document.body.classList.add("body-visible");
    console.log("[DIAGNÓSTICO MÍNIMO] Body class 'body-visible' adicionada.");
  } else {
    console.error("[DIAGNÓSTICO MÍNIMO] document.body não encontrado!");
  }

  if (typeof lucide !== "undefined") {
    try {
      lucide.createIcons();
      console.log("[DIAGNÓSTICO MÍNIMO] Lucide icons criados.");
    } catch (e) {
      console.error("[DIAGNÓSTICO MÍNIMO] Erro ao criar ícones Lucide:", e);
    }
  } else {
    console.warn(
      "[DIAGNÓSTICO MÍNIMO] Lucide não definido no DOMContentLoaded."
    );
  }

  alert("Teste de Diagnóstico Mínimo do main.js CONCLUÍDO!");
  console.log("[DIAGNÓSTICO MÍNIMO] DOMContentLoaded concluído.");
});

console.log("[DIAGNÓSTICO MÍNIMO] main.js: Fim do script (nível mais básico).");
