<?php

namespace GlpiPlugin\Glpinewentity;

use CommonGLPI;
use Session;
use Toolbox;

class Menu extends CommonGLPI {

    public static function canView(): bool {
        return Session::haveRight('entity', READ);
    }

    public static function getMenuName() {
        return __('GLPI New Entity', 'glpinewentity');
    }

    public static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => Toolbox::getItemTypeSearchUrl(Sector::class, false),
            'icon'  => 'ti ti-building-community',
            'options' => [
                'sector' => [
                    'title' => Sector::getTypeName(Session::getPluralNumber()),
                    'page'  => Toolbox::getItemTypeSearchUrl(Sector::class, false),
                    'links' => [
                        'search' => Toolbox::getItemTypeSearchUrl(Sector::class, false),
                        'add'    => Sector::getFormURL(false)
                    ]
                ]
            ]
        ];

        return $menu;
    }
}
