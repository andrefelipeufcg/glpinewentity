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

define('PLUGIN_GLPINEWENTITY_VERSION', '1.0.0');
define('PLUGIN_GLPINEWENTITY_MIN_GLPI', '11.0.0');

function plugin_init_glpinewentity(): void {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['glpinewentity'] = true;
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['glpinewentity'] = 'front/sector.form.php';

    Plugin::registerClass('PluginGlpinewentityWizard');
    Plugin::registerClass('PluginGlpinewentitySector');

    $plugin = new Plugin();
    if ($plugin->isActivated('glpinewentity')) {
        if (isset($_SESSION['glpiactiveprofile']['id']) && $_SESSION['glpiactiveprofile']['id'] == 4) {
            $PLUGIN_HOOKS['menu_toadd']['glpinewentity'] = ['config' => 'PluginGlpinewentityMenu'];
        }
    }
}

function plugin_version_glpinewentity(): array {
    return [
        'name'         => 'GLPI New Entity',
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
        echo 'Este plugin requer GLPI 11.0.0 ou superior.';
        return false;
    }
    return true;
}

function plugin_glpinewentity_check_config(): bool {
    return true;
}
