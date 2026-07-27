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

    // Concede acesso apenas ao perfil Super-Admin (requisito de segurança do Marketplace)
    $superadmin_ids = [];
    if (method_exists('\Profile', 'getSuperAdminProfilesId')) {
        $superadmin_ids = \Profile::getSuperAdminProfilesId();
    } else {
        $superadmin_ids = [4]; // Perfil Super-Admin padrão no GLPI 10
    }
    
    foreach ((array)$superadmin_ids as $superadmin_id) {
        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $superadmin_id,
                'name'        => 'plugin_glpinewentity'
            ]
        ]);
        
        if (count($iterator) == 0) {
            $DB->insert('glpi_profilerights', [
                'profiles_id' => $superadmin_id,
                'name'        => 'plugin_glpinewentity',
                'rights'      => 1
            ]);
        }
    }

    $migration = new Migration(PLUGIN_GLPINEWENTITY_VERSION);

    if (!$DB->tableExists('glpi_plugin_glpinewentity_sectors')) {
        $query = "CREATE TABLE `glpi_plugin_glpinewentity_sectors` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Entidade Pai',
            `sector_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `sector_abbr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `metadata` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON com dados da infra (subgrupos, categorias, ids GLPI criados)',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $migration->displayMessage("Creating glpi_plugin_glpinewentity_sectors table");
        $DB->doQuery($query);
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

    ProfileRight::deleteByPlugin('glpinewentity');
    $DB->delete('glpi_profilerights', ['name' => 'plugin_glpinewentity']);

    return true;
}

