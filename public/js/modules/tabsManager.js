// public/js/modules/tabsManager.js
console.log("[DEBUG] tabsManager.js: Módulo carregado.");

export function initMainTabs() {
  const tabButtons = document.querySelectorAll('#main-tabs [role="tab"]');
  const tabContents = document.querySelectorAll(
    '#main-tabs-content [role="tabpanel"]'
  );

  if (!tabButtons.length || !tabContents.length) {
    // console.warn("[DEBUG] Elementos de abas principais não encontrados (tabsManager.js).");
    return;
  }

  // Função para definir o estado inicial ou após navegação de mês
  function setActiveTab(targetTabId) {
    let initialTabButton = null;
    let initialTabContent = null;

    if (targetTabId) {
      initialTabButton = document.getElementById(targetTabId);
      if (initialTabButton) {
        const targetContentId =
          initialTabButton.getAttribute("data-tabs-target");
        initialTabContent = document.querySelector(targetContentId);
      }
    }

    // Se nenhum target específico ou não encontrado, usa o primeiro como padrão
    if (!initialTabButton || !initialTabContent) {
      initialTabButton = tabButtons[0];
      const initialContentId =
        initialTabButton.getAttribute("data-tabs-target");
      initialTabContent = document.querySelector(initialContentId);
    }

    tabButtons.forEach((button) => {
      button.setAttribute("aria-selected", "false");
      button.classList.remove(
        "text-blue-600",
        "border-blue-600",
        "dark:text-blue-500",
        "dark:border-blue-500"
      );
      button.classList.add(
        "border-transparent",
        "hover:text-gray-600",
        "hover:border-gray-300",
        "dark:hover:text-gray-300"
      );
    });

    tabContents.forEach((content) => {
      content.classList.add("hidden");
    });

    if (initialTabButton && initialTabContent) {
      initialTabButton.setAttribute("aria-selected", "true");
      initialTabButton.classList.add(
        "text-blue-600",
        "border-blue-600",
        "dark:text-blue-500",
        "dark:border-blue-500"
      );
      initialTabButton.classList.remove(
        "border-transparent",
        "hover:text-gray-600",
        "hover:border-gray-300"
      );
      initialTabContent.classList.remove("hidden");
    }
  }

  tabButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      const targetContentId = button.getAttribute("data-tabs-target");
      const targetContent = document.querySelector(targetContentId);

      // Esconde todos os painéis de conteúdo
      tabContents.forEach((content) => {
        content.classList.add("hidden");
      });

      // Desmarca todos os botões de aba
      tabButtons.forEach((btn) => {
        btn.setAttribute("aria-selected", "false");
        // Classes para aba inativa (Tailwind CSS - ajuste conforme seu design)
        btn.classList.remove(
          "text-blue-600",
          "border-blue-600",
          "dark:text-blue-500",
          "dark:border-blue-500"
        );
        btn.classList.add(
          "border-transparent",
          "hover:text-gray-600",
          "hover:border-gray-300",
          "dark:hover:text-gray-300"
        );
      });

      // Mostra o painel de conteúdo da aba clicada
      if (targetContent) {
        targetContent.classList.remove("hidden");
      }

      // Marca o botão da aba clicada como ativo
      button.setAttribute("aria-selected", "true");
      button.classList.add(
        "text-blue-600",
        "border-blue-600",
        "dark:text-blue-500",
        "dark:border-blue-500"
      );
      button.classList.remove(
        "border-transparent",
        "hover:text-gray-600",
        "hover:border-gray-300"
      );

      // Armazena a aba ativa no localStorage
      localStorage.setItem("activeMainTab", button.id);
    });
  });

  // Verifica se há uma aba ativa armazenada no localStorage
  const storedActiveTabId = localStorage.getItem("activeMainTab");
  if (storedActiveTabId) {
    const activeTabButton = document.getElementById(storedActiveTabId);
    if (activeTabButton) {
      setActiveTab(storedActiveTabId); // Usa a função para definir corretamente
    } else {
      setActiveTab(tabButtons[0].id); // Fallback para a primeira aba se a armazenada não existir
    }
  } else {
    setActiveTab(tabButtons[0].id); // Define a primeira aba como ativa por padrão
  }
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
}

// Função para ser chamada quando a navegação de mês mudar, para manter a aba ativa
export function refreshActiveTabState() {
  const storedActiveTabId = localStorage.getItem("activeMainTab");
  if (storedActiveTabId) {
    const tabButton = document.getElementById(storedActiveTabId);
    if (tabButton) {
      // Re-simula o clique para garantir que o estado visual e aria seja atualizado
      // e o conteúdo correto seja exibido.
      // Em vez de simular o clique, vamos apenas redefinir o estado visual:
      const tabButtons = document.querySelectorAll('#main-tabs [role="tab"]');
      const tabContents = document.querySelectorAll(
        '#main-tabs-content [role="tabpanel"]'
      );

      tabButtons.forEach((btn) => {
        btn.setAttribute("aria-selected", "false");
        btn.classList.remove(
          "text-blue-600",
          "border-blue-600",
          "dark:text-blue-500",
          "dark:border-blue-500"
        );
        btn.classList.add(
          "border-transparent",
          "hover:text-gray-600",
          "hover:border-gray-300",
          "dark:hover:text-gray-300"
        );
      });

      tabContents.forEach((content) => {
        content.classList.add("hidden");
      });

      tabButton.setAttribute("aria-selected", "true");
      tabButton.classList.add(
        "text-blue-600",
        "border-blue-600",
        "dark:text-blue-500",
        "dark:border-blue-500"
      );
      tabButton.classList.remove(
        "border-transparent",
        "hover:text-gray-600",
        "hover:border-gray-300"
      );

      const targetContentId = tabButton.getAttribute("data-tabs-target");
      const targetContent = document.querySelector(targetContentId);
      if (targetContent) {
        targetContent.classList.remove("hidden");
      }
    }
  }
}
