<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — hook.php
 * Rotinas de instalação e desinstalação do plugin.
 * V1 não cria tabelas próprias (usa apenas tabelas nativas do GLPI).
 * -----------------------------------------------------------------------
 */

// -----------------------------------------------------------------------
// INSTALL — Nenhuma tabela própria na V1
// -----------------------------------------------------------------------
function plugin_glpinewentity_install(): bool {
    return true;
}

// -----------------------------------------------------------------------
// UNINSTALL — Nenhuma tabela própria na V1
// -----------------------------------------------------------------------
function plugin_glpinewentity_uninstall(): bool {
    return true;
}
