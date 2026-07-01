<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — hook.php
 * Rotinas de instalação e desinstalação do plugin.
 * V1 não cria tabelas próprias (usa apenas tabelas nativas do GLPI).
 * -----------------------------------------------------------------------
 */

// -----------------------------------------------------------------------
// INSTALL — Criar tabela para armazenar as infraestruturas geradas
// -----------------------------------------------------------------------
function plugin_glpinewentity_install(): bool {
    global $DB;

    $migration = new Migration(PLUGIN_GLPINEWENTITY_VERSION);

    if (!$DB->tableExists('glpi_plugin_glpinewentity_sectors')) {
        $query = "CREATE TABLE `glpi_plugin_glpinewentity_sectors` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `entities_id` int(11) NOT NULL DEFAULT '0' COMMENT 'Entidade Pai',
            `sector_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `sector_abbr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `metadata` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON com dados da infra (subgrupos, categorias, ids GLPI criados)',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $stmt = $DB->prepare($query);
        $DB->executeStatement($stmt);
    }

    $migration->executeMigration();

    return true;
}

// -----------------------------------------------------------------------
// UNINSTALL — Remover a tabela
// -----------------------------------------------------------------------
function plugin_glpinewentity_uninstall(): bool {
    global $DB;

    $tables = [
        'glpi_plugin_glpinewentity_sectors'
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->dropTable($table);
        }
    }

    return true;
}
