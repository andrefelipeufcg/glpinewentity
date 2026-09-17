<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Menu.php
 * Define a entrada do menu no GLPI para o plugin e gerencia os links de navegação.
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity;

use CommonGLPI;
use Session;
use Toolbox;

class Menu extends CommonGLPI {

    public static $rightname = 'entity';

    public static function getMenuName() {
        return __('GLPI New Entity', 'glpinewentity');
    }

    public static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => Sector::getSearchURL(false),
            'icon'  => 'ti ti-building-community',
            // Os links da entrada raiz alimentam as ações nativas do breadcrumb.
            'links' => [
                'search' => Sector::getSearchURL(false),
            ],
        ];

        if (Session::haveRight('plugin_glpinewentity', READ)) {
            // A inclusão exige o direito CREATE atribuído ao Super-Admin na instalação.
            if (Sector::canCreate()) {
                $menu['links']['add'] = Sector::getFormURL(false);
            }

            $menu['options'] = [
                'sector' => [
                    'icon'  => Sector::getIcon(),
                    'links' => []
                ]
            ];

            if (Session::haveRight('plugin_glpinewentity', READ)) {
                $menu['options']['sector']['title'] = Sector::getTypeName(Session::getPluralNumber());
                $menu['options']['sector']['page'] = Sector::getSearchURL(false);
                $menu['options']['sector']['links']['search'] = Sector::getSearchURL(false);
            }

        }

        return $menu;
    }
}
