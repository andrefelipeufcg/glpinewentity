<?php
namespace GlpiPlugin\Glpinewentity;

if ((new \ReflectionProperty('\CommonDBTM', 'rightname'))->hasType()) {
    class ProfileBase extends \CommonDBTM {
        public static string $rightname = 'plugin_glpinewentity';
    }
} else {
    class ProfileBase extends \CommonDBTM {
        public static $rightname = 'plugin_glpinewentity';
    }
}

class Profile extends ProfileBase {
    public static function getTypeName($nb = 0) {
        return __('GLPI New Entity', 'glpinewentity');
    }

    public static function getIcon() {
        return 'fas fa-building';
    }

    public static function getAllRights() {
        return [
            READ   => __('Ler', 'glpinewentity'),
            UPDATE => __('Atualizar', 'glpinewentity'),
            CREATE => __('Criar', 'glpinewentity'),
            PURGE  => __('Excluir', 'glpinewentity')
        ];
    }

    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            return self::getTypeName(2);
        }
        return '';
    }

    public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            \Profile::showForm($item->getID(), self::class);
        }
        return true;
    }
}
