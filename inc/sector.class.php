<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — inc/sector.class.php
 * Model principal para armazenamento dos setores criados.
 * -----------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginGlpinewentitySector extends CommonDBTM {
    
    public static function canCreate(): bool {
        return true;
    }

    public static function canView(): bool {
        return true;
    }

    public static function canUpdate(): bool {
        return true;
    } 

    public static function getFormURL($full = true) {
        return Plugin::getWebDir('glpinewentity', $full) . '/front/sector.form.php';
    }

    public static function getSearchURL($full = true) {
        return Plugin::getWebDir('glpinewentity', $full) . '/front/sector.php';
    } 

    /**
     * Nome que aparece na interface do GLPI
     */
    static function getTypeName($nb = 0) {
        return _n('Infraestrutura de Setor', 'Infraestruturas de Setores', $nb, 'glpinewentity');
    }

    /**
     * Define o campo usado como 'nome' para que o GLPI crie os links corretamente.
     */
    public static function getNameField() {
        return 'sector_abbr';
    }

    /**
     * Define as colunas que aparecem na tela de busca (Grid)
     */
    public function rawSearchOptions() {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics')
        ];

        // 1. Sigla
        $tab[] = [
            'id'                 => '1',
            'table'              => $this->getTable(),
            'field'              => 'sector_abbr',
            'name'               => 'Sigla',
            'datatype'           => 'itemlink',
        ];

        // 2. Nome do Setor
        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'sector_name',
            'name'               => 'Nome do Setor',
            'datatype'           => 'string',
        ];

        // 3. Entidade Pai (Ligação com glpi_entities através do campo entities_id da nossa tabela)
        $tab[] = [
            'id'                 => '3',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => 'Entidade Pai',
            'datatype'           => 'itemlink',
        ];

        return $tab;
    }
}
