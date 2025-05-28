<?php
// templates/footer.php
?>
            </main>
        </div> 
    </div> 


    <script src="<?php echo BASE_URL; ?>/public/js/main.js" type="module"></script>
    <?php
    if (isset($pageSpecificJs) && is_array($pageSpecificJs)) {
        foreach ($pageSpecificJs as $jsFile) {
            // Verifica se é uma URL completa (CDN) ou um caminho local
             if (preg_match('/^(http:\/\/|https:\/\/|\/\/)/i', $jsFile)) {
                echo '<script src="' . htmlspecialchars($jsFile) . '" type="module"></script>' . "\n"; // Adicionado type="module" para consistência
             } else {
                // Garante que caminhos locais comecem com / se não forem URLs completas
                $jsFilePath = (strpos($jsFile, '/') === 0) ? $jsFile : '/' . ltrim($jsFile, '/');
                echo '<script src="' . BASE_URL . htmlspecialchars($jsFilePath) . '" type="module"></script>' . "\n";
             }
        }
    }
    ?>
     <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            <?php
            if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
                $flash = $_SESSION['flash_message'];
                echo "if(typeof window.showGlobalToast === 'function'){ window.showGlobalToast('" . addslashes(htmlspecialchars($flash['message'])) . "', '" . addslashes(htmlspecialchars($flash['type'])) . "', 5000); } else { console.warn('showGlobalToast not defined. Flash message: " . addslashes(htmlspecialchars($flash['message'])) . "'); alert('" . addslashes(ucfirst(htmlspecialchars($flash['type']))) . ": " . addslashes(htmlspecialchars($flash['message'])) . "'); }";
                unset($_SESSION['flash_message']);
            }

            echo "
            if (typeof window.showGlobalToast === 'function') {
                const urlParams = new URLSearchParams(window.location.search);
                const erroParam = urlParams.get('erro');
                if (erroParam) {
                    window.showGlobalToast(decodeURIComponent(erroParam), 'error');
                }
                const statusParam = urlParams.get('status');
                if (statusParam) {
                    let message = '';
                    let type = 'info';
                    if (statusParam === 'logout_success') {
                        message = 'Logout realizado com sucesso!';
                        type = 'success';
                    } else if (statusParam === 'cadastro_sucesso_email_enviado') {
                        message = 'Cadastro realizado com sucesso! Verifique seu e-mail.';
                        type = 'success';
                    } else if (statusParam === 'cadastro_sucesso') {
                         message = 'Cadastro realizado com sucesso!';
                         type = 'success';
                    }
                    // Adicione outros status conforme necessário
                    if (message) {
                        window.showGlobalToast(message, type, 5000);
                    }
                }
            }
            ";
            ?>
        });
        // Fallback para lucide.js
        const observer = new MutationObserver(mutations => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    </script>
</body>
</html>