// public/js/modules/tooltipManager.js
console.log("[DEBUG] tooltipManager.js: Módulo carregado.");

let tooltipElement;

function createTooltipElement() {
  if (!tooltipElement) {
    tooltipElement = document.createElement("div");
    tooltipElement.id = "custom-tooltip";
    // As classes Tailwind serão aplicadas aqui, ou você pode usar uma classe customizada definida no seu CSS.
    tooltipElement.className =
      "fixed z-[9999] px-2 py-1 text-xs font-medium text-white bg-gray-800 rounded-md shadow-sm opacity-0 invisible transition-all duration-200 ease-in-out pointer-events-none transform";
    document.body.appendChild(tooltipElement);
  }
}

function showTooltip(event) {
  const target = event.currentTarget;
  const tooltipText = target.getAttribute("data-tooltip-text");

  if (!tooltipText || !tooltipElement) return;

  tooltipElement.textContent = tooltipText;
  tooltipElement.classList.remove("invisible", "opacity-0", "scale-90");
  tooltipElement.classList.add("visible", "opacity-100", "scale-100");
  positionTooltip(event);
}

function hideTooltip() {
  if (!tooltipElement) return;
  tooltipElement.classList.remove("visible", "opacity-100", "scale-100");
  tooltipElement.classList.add("invisible", "opacity-0", "scale-90");
}

function positionTooltip(event) {
  if (!tooltipElement) return;

  const target = event.currentTarget;
  const rect = target.getBoundingClientRect();
  const tooltipRect = tooltipElement.getBoundingClientRect();

  let top = event.clientY + 15; // Abaixo do cursor
  let left = event.clientX;

  // Ajustar para não sair da tela
  if (top + tooltipRect.height > window.innerHeight) {
    top = event.clientY - tooltipRect.height - 10; // Acima do cursor
  }
  if (left + tooltipRect.width > window.innerWidth) {
    left = window.innerWidth - tooltipRect.width - 10;
  }
  if (left < 0) {
    left = 10;
  }

  tooltipElement.style.left = `${left}px`;
  tooltipElement.style.top = `${top}px`;
}

export function initTooltips() {
  createTooltipElement();

  const elementsWithTooltip = document.querySelectorAll("[data-tooltip-text]");
  elementsWithTooltip.forEach((element) => {
    element.addEventListener("mouseenter", showTooltip);
    element.addEventListener("mouseleave", hideTooltip);
    element.addEventListener("mousemove", positionTooltip); // Para seguir o mouse, opcional
  });
  console.log(
    `[DEBUG] Tooltips inicializados para ${elementsWithTooltip.length} elementos.`
  );
}
