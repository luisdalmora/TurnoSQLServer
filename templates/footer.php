<?php
// templates/footer.php
?>
            </main>
        </div> 
    </div>

    <div id="password-generator-modal-backdrop" class="fixed inset-0 bg-gray-800 bg-opacity-75 backdrop-blur-sm flex items-center justify-center hidden z-[1050] transition-opacity duration-300 ease-in-out opacity-0">
        <div id="password-generator-modal-content" class="bg-white p-6 md:p-8 rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 ease-in-out scale-95 opacity-0">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-800 flex items-center">
                    <i data-lucide="key-round" class="w-6 h-6 mr-2 text-sky-600"></i>Gerador de Senhas
                </h3>
                <button type="button" id="close-password-generator-modal-btn" class="p-2 -m-2 text-slate-400 hover:text-slate-600 rounded-full transition-colors" aria-label="Fechar modal">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="relative">
                    <input type="text" id="pg-senhaGerada" readonly
                           class="w-full p-3 pr-12 border border-slate-300 rounded-lg text-slate-700 bg-slate-50 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-lg"
                           placeholder="Sua senha segura aparecerá aqui...">
                    <button type="button" id="pg-copiarSenhaBtn" title="Copiar Senha"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-500 hover:text-sky-600 transition-colors rounded-r-lg"
                            data-tooltip-text="Copiar Senha Gerada">
                        <i data-lucide="copy" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div>
                        <label for="pg-comprimento" class="block text-sm font-medium text-slate-700 mb-1">Comprimento: <span id="pg-comprimentoValor" class="font-semibold text-sky-600">16</span></label>
                        <input type="range" id="pg-comprimento" min="8" max="32" value="16"
                               class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-600">
                    </div>
                    <button type="button" id="pg-gerarSenhaBtn"
                            class="w-full sm:w-auto sm:ml-auto px-5 py-2.5 bg-sky-600 text-white font-medium rounded-lg hover:bg-sky-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 flex items-center justify-center">
                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>Gerar Nova
                    </button>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" id="pg-incluirMaiusculas" checked class="form-checkbox h-4 w-4 text-sky-600 border-slate-300 rounded focus:ring-sky-500">
                        <span class="text-slate-700">Maiúsculas (A-Z)</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" id="pg-incluirNumeros" checked class="form-checkbox h-4 w-4 text-sky-600 border-slate-300 rounded focus:ring-sky-500">
                        <span class="text-slate-700">Números (0-9)</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" id="pg-incluirSimbolos" checked class="form-checkbox h-4 w-4 text-sky-600 border-slate-300 rounded focus:ring-sky-500">
                        <span class="text-slate-700">Símbolos (!@#$)</span>
                    </label>
                </div>
                <div id="pg-forcaSenha" class="text-sm mt-2 h-6"></div> 
            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/main.js" type="module"></script>
    <?php
    if (isset($pageSpecificJs) && is_array($pageSpecificJs)) {
        foreach ($pageSpecificJs as $jsFile) {
            if (preg_match('/^(http:\/\/|https:\/\/|\/\/)/i', $jsFile)) {
                 echo '<script src="' . htmlspecialchars($jsFile) . '" type="module"></script>' . "\n";
            } else {
                echo '<script src="' . BASE_URL . '/' . ltrim(htmlspecialchars($jsFile), '/') . '" type="module"></script>' . "\n";
            }
        }
    }
    ?>
     <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        // Fallback para lucide.js caso o DOMContentLoaded do main.js não pegue todos os ícones tardios
        const observer = new MutationObserver(mutations => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    </script>
</body>
</html>