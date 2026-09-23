<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — Plugin GLPI 11
 * Wizard de onboarding: cria Entidade, Admin local, Grupos, Técnicos Atendentes
 * e Catálogo de Serviços (ITILCategory) em poucos cliques.
 * -----------------------------------------------------------------------
 * @package   glpinewentity
 * @author    andrefelipeufcg
 * @license   GPLv3+
 * @link      https://github.com/andrefelipeufcg/glpinewentity
 * -----------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_GLPINEWENTITY_VERSION', '1.1.0');
define('PLUGIN_GLPINEWENTITY_MIN_GLPI', '11.0.0');

function plugin_init_glpinewentity(): void {
    global $PLUGIN_HOOKS;

    include_once __DIR__ . '/hook.php';

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['glpinewentity'] = true;
    // A engrenagem do plugin inicia diretamente o wizard de inclusão.
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['glpinewentity'] = 'front/sector.form.php';
    $PLUGIN_HOOKS[Hooks::UNDISCLOSED_CONFIG_VALUE]['glpinewentity'] = 'plugin_glpinewentity_undisclosed_config_value';

    Plugin::registerClass('GlpiPlugin\Glpinewentity\Wizard');
    Plugin::registerClass('GlpiPlugin\Glpinewentity\Sector');
    Plugin::registerClass('GlpiPlugin\Glpinewentity\Menu');

    $plugin = new Plugin();
    if ($plugin->isActivated('glpinewentity')) {
        if (Session::haveRight('plugin_glpinewentity', READ)) {
            // O menu usa a classe própria para manter o título "GLPI New Entity"
            // e abrir a listagem com o botão de inclusão.
            $PLUGIN_HOOKS[Hooks::MENU_TOADD]['glpinewentity'] = [
                'config' => 'GlpiPlugin\Glpinewentity\Menu',
            ];
        }
    }
}

function plugin_version_glpinewentity(): array {
    return [
        'name'         => __('GLPI New Entity', 'glpinewentity'),
        'version'      => PLUGIN_GLPINEWENTITY_VERSION,
        'author'       => 'andrefelipeufcg',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/andrefelipeufcg/glpinewentity',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GLPINEWENTITY_MIN_GLPI,
            ],
        ],
    ];
}

function plugin_glpinewentity_check_prerequisites(): bool {
    if (version_compare(GLPI_VERSION, PLUGIN_GLPINEWENTITY_MIN_GLPI, '<')) {
        echo "<p class='error'>" . __('Este plugin requer GLPI 11.0.0 ou superior.', 'glpinewentity') . "</p>";
        return false;
    }
    return true;
}

function plugin_glpinewentity_undisclosed_config_value(array $config): array {
    // Retornar mascarado se houver chaves de segredo no futuro
    return $config;
}

function plugin_glpinewentity_check_config(): bool {
    return true;
}
