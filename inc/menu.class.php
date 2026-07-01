<?php

class PluginGlpinewentityMenu extends CommonGLPI {
    public static $rightname = 'config';

    public static function getMenuName() {
        return "GLPI New Entity";
    }

    public static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => Toolbox::getItemTypeSearchUrl('PluginGlpinewentitySector', false),
            'icon'  => 'ti ti-building-community',
            'options' => [
                'sector' => [
                    'title' => PluginGlpinewentitySector::getTypeName(Session::getPluralNumber()),
                    'page'  => Toolbox::getItemTypeSearchUrl('PluginGlpinewentitySector', false),
                    'links' => [
                        'search' => Toolbox::getItemTypeSearchUrl('PluginGlpinewentitySector', false),
                        'add'    => PluginGlpinewentitySector::getFormURL(false)
                    ]
                ]
            ]
        ];

        return $menu;
    }
}
