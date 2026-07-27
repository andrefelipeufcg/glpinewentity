<?php

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
            'page'  => Toolbox::getItemTypeSearchUrl(Sector::class, false),
            'icon'  => 'ti ti-building-community',
        ];

        if (Session::haveRight('plugin_glpinewentity', READ)) {
            $menu['options'] = [
                'sector' => [
                    'icon'  => Sector::getIcon(),
                    'links' => []
                ]
            ];

            if (Session::haveRight('plugin_glpinewentity', READ)) {
                $menu['options']['sector']['title'] = Sector::getTypeName(Session::getPluralNumber());
                $menu['options']['sector']['page'] = Toolbox::getItemTypeSearchUrl(Sector::class, false);
                $menu['options']['sector']['links']['search'] = Toolbox::getItemTypeSearchUrl(Sector::class, false);
            }

            if (Sector::canCreate()) {
                $menu['options']['sector']['links']['add'] = Sector::getFormURL(false);
            }
        }

        return $menu;
    }
}
