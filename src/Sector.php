<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Sector.php
 * Model principal para armazenamento dos setores criados.
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity;

use CommonDBTM;
use Plugin;
use Session;
use Entity;
use Html;
use Profile;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class Sector extends CommonDBTM {
    
    public static string $rightname = 'plugin_glpinewentity';
    // Permite que o endpoint nativo das abas carregue o registro durante a edição.
    public bool $get_item_to_display_tab = true;

    public static function canCreate(): bool {
        return Session::haveRight('plugin_glpinewentity', CREATE);
    }

    public static function canView(): bool {
        return Session::haveRight('plugin_glpinewentity', READ);
    }

    public static function canUpdate(): bool {
        return Session::haveRight('plugin_glpinewentity', UPDATE);
    }

    public static function canDelete(): bool {
        return Session::haveRight('plugin_glpinewentity', PURGE);
    }

    public function canCreateItem(): bool {
        return self::canCreate();
    }

    public function canViewItem(): bool {
        return self::canView();
    }

    public function canUpdateItem(): bool {
        return self::canUpdate();
    }

    public function canDeleteItem(): bool {
        return self::canDelete();
    }

    /**
     * Nome que aparece na interface do GLPI
     */

    public static function getIcon() {
        return 'ti ti-building-community';
    }

    /**
     * Usa as páginas públicas do plugin para evitar URLs inferidas diferentes no servidor.
     */
    public static function getSearchURL($full = true): string {
        global $CFG_GLPI;
        $path = \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.php';
        return $full ? $CFG_GLPI['root_doc'] . $path : $path;
    }

    public static function getFormURL($full = true): string {
        global $CFG_GLPI;
        $path = \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php';
        return $full ? $CFG_GLPI['root_doc'] . $path : $path;
    }

    public static function getTypeName($nb = 0) {
        return _n('Infraestrutura da entidade', 'Infraestruturas da entidade', $nb, 'glpinewentity');
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
            'name'               => __('Sigla', 'glpinewentity'),
            'datatype'           => 'itemlink',
        ];

        // 2. Nome do Setor
        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'sector_name',
            'name'               => __('Nome do Setor', 'glpinewentity'),
            'datatype'           => 'string',
        ];

        // 3. Entidade Pai (Ligação com glpi_entities através do campo entities_id da nossa tabela)
        $tab[] = [
            'id'                 => '3',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => __('Entidade Pai', 'glpinewentity'),
            'datatype'           => 'itemlink',
        ];

        return $tab;
    }

    public function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong); // Aba principal
        if ($this->fields['id'] > 0) {
            $ong['GlpiPlugin\Glpinewentity\Sector$1'] = self::createTabEntry(__('Modelos de Chamado', 'glpinewentity'), 0, __CLASS__, 'ti ti-ticket');
            $ong['GlpiPlugin\Glpinewentity\Sector$2'] = self::createTabEntry(__('Respostas Básicas', 'glpinewentity'), 0, __CLASS__, 'ti ti-messages');
            $ong['GlpiPlugin\Glpinewentity\Sector$3'] = self::createTabEntry(__('Soluções Básicas', 'glpinewentity'), 0, __CLASS__, 'ti ti-bulb');
            $ong['GlpiPlugin\Glpinewentity\Sector$4'] = self::createTabEntry(__('Motivos de Pendências', 'glpinewentity'), 0, __CLASS__, 'ti ti-clock-pause');
            $ong['GlpiPlugin\Glpinewentity\Sector$5'] = self::createTabEntry(__('Notificações', 'glpinewentity'), 0, __CLASS__, 'ti ti-bell');
            $ong['GlpiPlugin\Glpinewentity\Sector$6'] = self::createTabEntry(__('Formulário Padrão', 'glpinewentity'), 0, __CLASS__, 'ti ti-clipboard-list');
        }
        return $ong;
    }

    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() == __CLASS__) {
            $ong = [];
            $ong[1] = self::createTabEntry(__('Modelos de Chamado', 'glpinewentity'), 0, $item::class, 'ti ti-ticket');
            $ong[2] = self::createTabEntry(__('Respostas Básicas', 'glpinewentity'), 0, $item::class, 'ti ti-messages');
            $ong[3] = self::createTabEntry(__('Soluções Básicas', 'glpinewentity'), 0, $item::class, 'ti ti-bulb');
            $ong[4] = self::createTabEntry(__('Motivos de Pendências', 'glpinewentity'), 0, $item::class, 'ti ti-clock-pause');
            $ong[5] = self::createTabEntry(__('Notificações', 'glpinewentity'), 0, $item::class, 'ti ti-bell');
            $ong[6] = self::createTabEntry(__('Formulário Padrão', 'glpinewentity'), 0, $item::class, 'ti ti-clipboard-list');
            return $ong;
        }
        return '';
    }

    public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() != __CLASS__) {
            return false;
        }

        $titles = [
            1 => 'Modelos de Chamado',
            2 => 'Respostas Básicas',
            3 => 'Soluções Básicas',
            4 => 'Motivos de Pendências',
            5 => 'Notificações',
            6 => 'Formulário Padrão',
        ];
        $title = $titles[$tabnum] ?? '';

        global $DB, $CFG_GLPI;

        // Recupera metadata
        $meta = json_decode($item->fields['metadata'] ?? '{}', true) ?: [];
        $tabKey = 'tab_' . $tabnum;
        $savedConfigs = $meta['configs'][$tabKey] ?? [];

        // Definições por aba
        $tableMapping = [
            1 => 'glpi_tickettemplates',
            2 => 'glpi_itilfollowuptemplates',
            3 => 'glpi_solutiontemplates',
            4 => 'glpi_pendingreasons',
            5 => 'glpi_notifications', // Para notificações, copiamos de outras notifications
            6 => 'glpi_plugin_formcreator_forms', // Se tiver Formcreator (mas no Builder usamos nativo glpi_forms, entao testamos)
        ];

        // Verifica glpi_forms vs glpi_plugin_formcreator_forms
        if ($tabnum == 6) {
            if ($DB->tableExists('glpi_forms_forms')) {
                $tableMapping[6] = 'glpi_forms_forms';
            }
        }

        // Busca modelos existentes no GLPI para o select "Copiar de..."
        $existingModels = [];
        $tableName = $tableMapping[$tabnum] ?? '';
        if ($tableName && $DB->tableExists($tableName)) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => $tableName,
                'ORDER'  => 'name ASC'
            ]);
            $modelsPadrao = [];
            $modelsNormal = [];
            foreach ($iterator as $row) {
                if (preg_match('/^\[padr[aã]o\]/i', $row['name'])) {
                    $modelsPadrao[$row['id']] = $row['name'];
                } else {
                    $modelsNormal[$row['id']] = $row['name'];
                }
            }
            $existingModels = $modelsPadrao + $modelsNormal;
        }

        // Determina os campos que serão exibidos baseado na aba
        $hasContentField = in_array($tabnum, [1, 2, 3]); // Modelos, Respostas, Soluções

        $extraOptions = [];
        if ($tabnum == 1 && $DB->tableExists('glpi_itilcategories')) {
            $catIter = $DB->request([
                'SELECT' => ['id', 'completename'],
                'FROM' => 'glpi_itilcategories',
                'ORDER' => 'completename ASC'
            ]);
            foreach ($catIter as $row) {
                $extraOptions['categories'][$row['id']] = $row['completename'];
            }
        }
        if ($tabnum == 4) {
            $extraOptions['calendars'] = [];
            if ($DB->tableExists('glpi_calendars')) {
                foreach ($DB->request(['FROM' => 'glpi_calendars']) as $row) {
                    $extraOptions['calendars'][$row['id']] = $row['name'];
                }
            }
            $extraOptions['itilfollowuptemplates'] = [];
            if ($DB->tableExists('glpi_itilfollowuptemplates')) {
                foreach ($DB->request(['FROM' => 'glpi_itilfollowuptemplates']) as $row) {
                    $extraOptions['itilfollowuptemplates'][$row['id']] = $row['name'];
                }
            }
            $extraOptions['solutiontemplates'] = [];
            if ($DB->tableExists('glpi_solutiontemplates')) {
                foreach ($DB->request(['FROM' => 'glpi_solutiontemplates']) as $row) {
                    $extraOptions['solutiontemplates'][$row['id']] = $row['name'];
                }
            }
            $extraOptions['frequencies'] = [];
            if (class_exists('PendingReason')) {
                $extraOptions['frequencies'] = \PendingReason::getFollowupFrequencyValues();
                $extraOptions['res_limits'] = \PendingReason::getFollowupsBeforeResolutionValues();
            }
        }
        if ($tabnum == 5) {
            $extraOptions['templates'] = [];
            if ($DB->tableExists('glpi_notificationtemplates')) {
                foreach ($DB->request(['FROM' => 'glpi_notificationtemplates']) as $row) {
                    $extraOptions['templates'][$row['id']] = $row['name'];
                }
            }
            $extraOptions['itemtypes'] = [];
            global $CFG_GLPI;
            if (isset($CFG_GLPI['notificationtemplates_types']) && is_array($CFG_GLPI['notificationtemplates_types'])) {
                foreach ($CFG_GLPI['notificationtemplates_types'] as $type) {
                    if ($itemObj = \getItemForItemtype($type)) {
                        $extraOptions['itemtypes'][$type] = $itemObj->getTypeName();
                    } else {
                        $extraOptions['itemtypes'][$type] = $type;
                    }
                }
            } else {
                $extraOptions['itemtypes'] = ['Ticket' => 'Ticket'];
            }
            $extraOptions['events'] = [];
            if (class_exists('NotificationTarget')) {
                $target = \NotificationTarget::getInstanceByType('Ticket');
                if ($target) {
                    $extraOptions['events'] = $target->getAllEvents();
                    $extraOptions['all_targets'] = [];
                    $extraOptions['all_exclusions'] = [];
                    $allowed_exclusion_types = [\Notification::PROFILE_TYPE, \Notification::GROUP_TYPE];

                    // Instanciar o NotificationTarget para CADA evento possível,
                    // pois targets são condicionais ao evento e os plugins (ex: Behaviors)
                    // adicionam targets via hook ITEM_ADD_TARGETS no construtor.
                    $targetClass = get_class($target);
                    foreach (array_keys($extraOptions['events']) as $evt) {
                        try {
                            $evtTarget = new $targetClass(null, $evt);
                            if (isset($evtTarget->notification_targets) && isset($evtTarget->notification_targets_labels)) {
                                foreach ($evtTarget->notification_targets as $key => $val) {
                                    if (isset($extraOptions['all_targets'][$key])) {
                                        continue; // já adicionado por outro evento
                                    }
                                    $parts = explode('_', $key);
                                    if (count($parts) >= 2) {
                                        $type = $parts[0];
                                        $id = $parts[1];
                                        if (isset($evtTarget->notification_targets_labels[$type][$id])) {
                                            $label = $evtTarget->notification_targets_labels[$type][$id];
                                            $extraOptions['all_targets'][$key] = $label;
                                            if (in_array((int)$type, $allowed_exclusion_types, true)) {
                                                $extraOptions['all_exclusions'][$key] = $label;
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // ignorar erros de instanciação por evento
                        }
                    }
                    asort($extraOptions['all_targets']);
                    asort($extraOptions['all_exclusions']);
                }
            }
        }

        if ($tabnum == 6 && $DB->tableExists('glpi_forms_categories')) {
            $catIter = $DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM'   => 'glpi_forms_categories',
                'ORDER'  => 'completename ASC'
            ]);
            foreach ($catIter as $row) {
                $extraOptions['form_categories'][$row['id']] = !empty($row['completename']) ? $row['completename'] : $row['name'];
            }
        }

        $ajax_save_url = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/ajax/save_draft_configs.php';
        $ajax_generate_url = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/ajax/generate_configs.php';

        echo "<div class='center' style='margin-top: 20px;'>";
        echo "<form id='form_configs_tab_{$tabnum}'>";
        echo "<input type='hidden' name='action' value='save_draft'>";
        echo "<input type='hidden' name='sector_id' value='{$item->getID()}'>";
        echo "<input type='hidden' name='tabnum' value='{$tabnum}'>";

        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr><th colspan='2' style='font-size: 1.2em;'>Configuração de {$title}</th></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2' style='padding: 20px;'>";
        echo "<div style='margin-bottom: 20px; text-align: left; padding: 10px;  border-radius: 5px;'>";
        echo "Crie ou edite os itens abaixo. Você pode selecionar um modelo existente do GLPI em <strong>'Copiar de...'</strong> para que a padronização use as mesmas configurações (campos, descrições, etc). Se não selecionar, será criado um item básico com o Nome (e Conteúdo, se aplicável) informados.";
        echo "</div>";

        echo "<style>
            .config-block .select2-container, 
            .config-block .select2-selection--single {
                max-width: none !important;
            }
        </style>";

        echo "<div id='items-container-tab-{$tabnum}'>";

        // Template oculto para adicionar novos
        echo self::renderConfigBlockTemplate($tabnum, $hasContentField, $existingModels, $extraOptions);

        // Renderiza existentes (salvos no rascunho) ou padrão se for vazio
        if (empty($savedConfigs)) {
            // Valores padrão iniciais para mostrar algo
            $savedConfigs = self::getDefaultConfigsForTab($tabnum);
        }

        foreach ($savedConfigs as $idx => $config) {
            echo self::renderConfigBlock($tabnum, $hasContentField, $existingModels, $config, $idx, $extraOptions);
        }
        echo "</div>";

        echo "<div style='text-align: left; padding: 10px 0;'>";
        echo "<button type='button' class='btn btn-success btn-sm' style='color: white !important;' onclick='addConfigItem({$tabnum})'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar Item</button>";
        echo "</div>";

        echo "</td></tr>";

        // Botões de Ação
        echo "<tr class='tab_bg_2'><td class='center' colspan='2' style='padding: 20px;'>";

        echo "<button type='button' id='btn_save_draft_{$tabnum}' class='btn btn-primary' onclick='saveDraftConfigs({$tabnum})' style='margin-right: 15px;'>";
        echo "<i class='fas fa-save' style='margin-right: 5px;'></i> Salvar Rascunho";
        echo "</button>";

        echo "<button type='button' id='btn_generate_{$tabnum}' class='btn btn-success' style='color: white !important;' onclick='generateSectorConfigs({$item->getID()}, {$tabnum})'>";
        echo "<i class='fas fa-magic' style='margin-right: 5px;'></i> Aplicar Padronização para esta Entidade";
        echo "</button>";

        echo "</td></tr>";

        echo "</table>";
        echo "</form>";
        echo "</div>";

        // JavaScript
        $ajax_get_template_url = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/ajax/get_template_data.php';
        $ajax_render_richtext_url = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/ajax/render_richtext.php';
        $ajax_render_form_category_url = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/ajax/render_form_category.php';
        $default_illustration_preview = json_encode((new \Glpi\UI\IllustrationManager())->renderIcon('request-service', 100));

        echo "<script>
        function decodeBase64Utf8(value) {
            let binary = atob(value);
            let bytes = Uint8Array.from(binary, function(character) {
                return character.charCodeAt(0);
            });
            return new TextDecoder('utf-8').decode(bytes);
        }

        function addConfigItem(tabnum) {
            let container = $('#items-container-tab-' + tabnum);
            let template = container.find('.config-block.template').clone();
            template.removeClass('template');
            template.show();
            
            // Reseta inputs
            template.find('input[type=\"text\"], textarea').val('');
            template.find('select').val('0');

            // Limpa Select2 para recriar
            template.find('.select2-container').remove();
            let selects = template.find('select');
            selects.removeClass('select2-hidden-accessible').removeAttr('data-select2-id').removeAttr('tabindex').removeAttr('aria-hidden').show();
            selects.find('option').removeAttr('data-select2-id');

            container.append(template);
            
            // Inicializa Select2
            template.find('.select2-copy-from').select2({ width: '100%' });
            if(template.find('.select2-cat').length > 0) {
                template.find('.select2-cat').select2({ width: '100%' });
            }

            // Substituir textarea simples por Rich Text (abas 1, 2, 3)
            if ([1, 2, 3].indexOf(tabnum) !== -1) {
                let plainTextarea = template.find('textarea.input-content');
                if (plainTextarea.length > 0) {
                    let wrapper = $('<div class=\"input-content-wrapper\"></div>');
                    plainTextarea.replaceWith(wrapper);
                    
                    $.ajax({
                        url: '{$ajax_render_richtext_url}',
                        type: 'POST',
                        data: { name: 'items_content[]', value: '' },
                        success: function(html) {
                            wrapper.html(html);
                        }
                    });
                }
            }
            
            // Buscar categoria de form via AJAX para tab 6 (mantém macro/botões nativos do GLPI)
            if (tabnum == 6) {
                let categoryWrapper = template.find('.form-category-wrapper');
                if (categoryWrapper.length > 0) {
                    $.ajax({
                        url: '{$ajax_render_form_category_url}',
                        type: 'POST',
                        data: { category_id: 0 },
                        success: function(html) {
                            categoryWrapper.html(html);
                        }
                    });
                }
                
                // Inicializa Rich Text para a descrição
                let plainDesc = template.find('textarea.input-description');
                if (plainDesc.length > 0) {
                    let descWrapper = $('<div class=\"input-description-wrapper\"></div>');
                    plainDesc.replaceWith(descWrapper);
                    
                    $.ajax({
                        url: '{$ajax_render_richtext_url}',
                        type: 'POST',
                        data: { name: 'items_description[]', value: '' },
                        success: function(html) {
                            descWrapper.html(html);
                        }
                    });
                }
            }
        }

        $(document).on('click', '.btn-remove-config', function() {
            let block = $(this).closest('.config-block');
            let editorId = block.find('.input-content-wrapper textarea').attr('id');
            if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.get(editorId).remove();
            }
            block.remove();
        });

        $(document).on('change', '.select2-copy-from', function() {
            let id = $(this).val();
            let tabnum = $(this).data('tab');
            let block = $(this).closest('.config-block');
            
            if (id > 0) {
                $.ajax({
                    url: '{$ajax_get_template_url}',
                    type: 'POST',
                    data: { id: id, tabnum: tabnum },
                    success: function(response) {
                        if (response.success && response.data) {
                            block.find('.input-name').val(response.data.name || '');
                            
                            // Busca o editor TinyMCE no wrapper (blocos renderizados pelo servidor) ou pelo textarea simples (blocos clonados)
                            let contentTextarea = block.find('.input-content-wrapper textarea').first();
                            if (contentTextarea.length === 0) {
                                contentTextarea = block.find('textarea.input-content');
                            }
                            let contentEditorId = contentTextarea.attr('id');
                            if (contentEditorId && typeof tinymce !== 'undefined' && tinymce.get(contentEditorId)) {
                                tinymce.get(contentEditorId).setContent(response.data.content || '');
                            } else {
                                contentTextarea.val(response.data.content || '');
                            }
                            
                            if (response.data.type) {
                                block.find('.input-type').val(response.data.type);
                            } else {
                                block.find('.input-type').val('1');
                            }
                            
                            if (response.data.itilcategories_id !== undefined) {
                                block.find('.input-category').val(response.data.itilcategories_id);
                            } else {
                                block.find('.input-category').val('0');
                            }

                            if (tabnum == 4) {
                                block.find('.input-is-default').val(response.data.is_default || '0');
                                block.find('.input-is-pending-per-default').val(response.data.is_pending_per_default || '0');
                                block.find('.input-calendars-id').val(response.data.calendars_id || '0').trigger('change');
                                block.find('.input-followup-frequency').val(response.data.followup_frequency || '0').trigger('change');
                                block.find('.input-itilfollowuptemplates-id').val(response.data.itilfollowuptemplates_id || '0').trigger('change');
                                block.find('.input-followups-before-resolution').val(response.data.followups_before_resolution || '0').trigger('change');
                                block.find('.input-solutiontemplates-id').val(response.data.solutiontemplates_id || '0').trigger('change');
                                block.find('.input-comment').val(response.data.comment || '');
                            }

                            if (tabnum == 5) {
                                block.find('.input-is-active').val(response.data.is_active || '1');
                                block.find('.input-itemtype').val(response.data.itemtype || 'Ticket').trigger('change');
                                block.find('.input-event').val(response.data.event || 'new').trigger('change');
                                block.find('.input-attach-documents').val(response.data.attach_documents !== undefined ? response.data.attach_documents : '-2');
                                block.find('.input-allow-response').val(response.data.allow_response !== undefined ? response.data.allow_response : '1');
                                block.find('.input-notificationtemplates-id').val(response.data.notificationtemplates_id || '0').trigger('change');
                                block.find('.input-comment').val(response.data.comment || '');
                                
                                if (response.data.target) {
                                    block.find('.input-target').val(response.data.target).trigger('change');
                                } else {
                                    block.find('.input-target').val('').trigger('change');
                                }
                                
                                if (response.data.exclusion) {
                                    block.find('.input-exclusion').val(response.data.exclusion).trigger('change');
                                } else {
                                    block.find('.input-exclusion').val('').trigger('change');
                                }
                            }

                            if (tabnum == 6) {
                                let textarea = block.find('.input-description-wrapper textarea');
                                let editorId = textarea.attr('id');
                                if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                                    tinymce.get(editorId).setContent(response.data.description || '');
                                } else {
                                    textarea.val(response.data.description || '');
                                }
                                let formsCatSelect = block.find('select[name=\"items_forms_categories_id[]\"]');
                                if (formsCatSelect.length > 0) {
                                    // O dropdown nativo busca a opção remota antes de selecioná-la.
                                    formsCatSelect.trigger('setValue', response.data.forms_categories_id || '0');
                                }
                                
                                let container = block.find('.illustration-wrapper');
                                if (container.length > 0) {
                                    // A ilustração copiada pertence ao formulário de origem.
                                    let illustrationVal = response.data.illustration || 'request-service';
                                    let hiddenInput = container.find('[data-glpi-icon-picker-value]');
                                    hiddenInput.val(illustrationVal);
                                    
                                    let nativePreview = container.find('[data-glpi-icon-picker-value-preview-native]');
                                    let customPreview = container.find('[data-glpi-icon-picker-value-preview-custom]');
                                    
                                    if (response.data.illustration_preview) {
                                        nativePreview.html(response.data.illustration_preview);
                                        nativePreview.removeClass('d-none');
                                        customPreview.addClass('d-none');
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                block.find('.input-name').val('');
                
                // Limpar editor das abas 1, 2 e 3 se existir
                let contentTextarea = block.find('.input-content-wrapper textarea').first();
                if (contentTextarea.length === 0) {
                    contentTextarea = block.find('textarea.input-content');
                }
                let contentEditorId = contentTextarea.attr('id');
                if (contentEditorId && typeof tinymce !== 'undefined' && tinymce.get(contentEditorId)) {
                    tinymce.get(contentEditorId).setContent('');
                } else {
                    contentTextarea.val('');
                }

                block.find('.input-type').val('1');
                block.find('.input-category').val('0');
                
                if (tabnum == 4) {
                    block.find('.input-is-default').val('0');
                    block.find('.input-is-pending-per-default').val('0');
                    block.find('.input-calendars-id').val('0').trigger('change');
                    block.find('.input-followup-frequency').val('0').trigger('change');
                    block.find('.input-itilfollowuptemplates-id').val('0').trigger('change');
                    block.find('.input-followups-before-resolution').val('0').trigger('change');
                    block.find('.input-solutiontemplates-id').val('0').trigger('change');
                    block.find('.input-comment').val('');
                }
                
                if (tabnum == 5) {
                    block.find('.input-is-active').val('1');
                    block.find('.input-itemtype').val('Ticket').trigger('change');
                    block.find('.input-event').val('new').trigger('change');
                    block.find('.input-attach-documents').val('-2');
                    block.find('.input-allow-response').val('1');
                    block.find('.input-notificationtemplates-id').val('0').trigger('change');
                    block.find('.input-comment').val('');
                    block.find('.input-target').val('').trigger('change');
                    block.find('.input-exclusion').val('').trigger('change');
                }

                if (tabnum == 6) {
                    let textarea = block.find('.input-description-wrapper textarea');
                    let editorId = textarea.attr('id');
                    if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                        tinymce.get(editorId).setContent('');
                    } else {
                        textarea.val('');
                    }
                    let formsCatSelect = block.find('select[name=\"items_forms_categories_id[]\"]');
                    if (formsCatSelect.length > 0) {
                        formsCatSelect.trigger('setValue', '0');
                    }
                    
                    let container = block.find('.illustration-wrapper');
                    if (container.length > 0) {
                        let hiddenInput = container.find('[data-glpi-icon-picker-value]');
                        hiddenInput.val('request-service');
                        let nativePreview = container.find('[data-glpi-icon-picker-value-preview-native]');
                        nativePreview.html({$default_illustration_preview});
                        nativePreview.removeClass('d-none');
                        container.find('[data-glpi-icon-picker-value-preview-custom]').addClass('d-none');
                    }
                }
            }
        });

        function saveDraftConfigs(tabnum) {
            let btn = $('#btn_save_draft_' + tabnum);
            let originalHtml = btn.html();
            btn.prop('disabled', true);
            btn.html('<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 5px;\"></i> Salvando...');

            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
            let formData = $('#form_configs_tab_' + tabnum).serialize();
            $.ajax({
                url: '{$ajax_save_url}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if(response.success) {
                        window.location.reload();
                    } else {
                        btn.prop('disabled', false).html(originalHtml);
                        alert('Erro ao salvar rascunho: ' + (response.error || 'Erro desconhecido'));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html(originalHtml);
                    alert('Erro na requisição.');
                }
            });
        }

        function generateSectorConfigs(sectorId, tabnum) {
            if(confirm('Atenção: Isso irá criar os registros definitivos no GLPI vinculados a esta entidade. O rascunho atual será salvo automaticamente.\\nDeseja prosseguir?')) {
                
                let btn = $('#btn_generate_' + tabnum);
                let originalHtml = btn.html();
                btn.prop('disabled', true);
                btn.html('<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 5px;\"></i> Aplicando...');

                // Primeiro salva o rascunho, depois gera
                if (typeof tinymce !== 'undefined') {
                    tinymce.triggerSave();
                }
                let formData = $('#form_configs_tab_' + tabnum).serialize();
                $.ajax({
                    url: '{$ajax_save_url}',
                    type: 'POST',
                    data: formData,
                    success: function(saveResp) {
                        if(saveResp.success) {
                            $.ajax({
                                url: '{$ajax_generate_url}',
                                type: 'POST',
                                data: {
                                    action: 'generate',
                                    sector_id: sectorId,
                                    tabnum: tabnum
                                },
                                success: function(genResp) {
                                    if(genResp.success) {
                                        window.location.reload();
                                    } else {
                                        btn.prop('disabled', false).html(originalHtml);
                                        alert('Erro ao gerar configurações: ' + (genResp.error || 'Erro desconhecido'));
                                    }
                                },
                                error: function() {
                                    btn.prop('disabled', false).html(originalHtml);
                                    alert('Erro na requisição de geração.');
                                }
                            });
                        } else {
                            btn.prop('disabled', false).html(originalHtml);
                            alert('Erro ao salvar rascunho antes de aplicar.');
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).html(originalHtml);
                        alert('Erro na requisição de salvamento.');
                    }
                });
            }
        }

        // Inicializa Rich Text (TinyMCE) via AJAX para os blocos marcados como pendentes
        // (evita o problema de DOMEval do jQuery ao carregar conteúdo de aba via AJAX)
        $(function() {
            $('.richtext-pending').each(function() {
                let wrapper = $(this);
                let fieldName = wrapper.data('field-name');
                let encodedValue = wrapper.data('field-value') || '';
                let decodedValue = '';
                try { decodedValue = decodeBase64Utf8(encodedValue); } catch(e) { decodedValue = ''; }

                $.ajax({
                    url: '{$ajax_render_richtext_url}',
                    type: 'POST',
                    data: { name: fieldName, value: decodedValue },
                    success: function(html) {
                        wrapper.html(html);
                        wrapper.removeClass('richtext-pending');
                    }
                });
            });
            $('.select2-copy-from').select2({ width: '100%' });
            $('.select2-cat').select2({ width: '100%' });
            $('.select2-calendar').select2({ width: '100%' });
            $('.select2-freq').select2({ width: '100%' });
            $('.select2-foltpl').select2({ width: '100%' });
            $('.select2-fbr').select2({ width: '100%' });
            $('.select2-soltpl').select2({ width: '100%' });
            $('.select2-itemtype').select2({ width: '100%' });
            $('.select2-event').select2({ width: '100%' });
            $('.select2-tpl').select2({ width: '100%' });
            $('.select2-target').select2({ width: '100%' });
        });
        </script>";

        return true;
    }

    private static function renderConfigBlockTemplate($tabnum, $hasContentField, $existingModels, $extraOptions = []) {
        return self::renderConfigBlock($tabnum, $hasContentField, $existingModels, ['name' => '', 'content' => '', 'copy_from' => 0, 'type' => 1, 'itilcategories_id' => 0], -1, $extraOptions, true);
    }

    private static function renderConfigBlock($tabnum, $hasContentField, $existingModels, $config, $idx, $extraOptions = [], $isTemplate = false, $isSingleBlock = false) {
        $display = $isTemplate ? "display: none;" : "";
        $class = "config-block" . ($isTemplate ? " template" : "");

        $name = htmlspecialchars($config['name'] ?? '');
        $contentRaw = $config['content'] ?? '';
        $content = htmlspecialchars($contentRaw);
        $copyFrom = (int)($config['copy_from'] ?? 0);
        $type = (int)($config['type'] ?? 1); // 1 = Incidente, 2 = Requisição
        $itilcategoryId = (int)($config['itilcategories_id'] ?? 0);
        $is_default = (int)($config['is_default'] ?? 0);
        $is_pending_per_default = (int)($config['is_pending_per_default'] ?? 0);
        $calendars_id = (int)($config['calendars_id'] ?? 0);
        $followup_frequency = (int)($config['followup_frequency'] ?? 0);
        $itilfollowuptemplates_id = (int)($config['itilfollowuptemplates_id'] ?? 0);
        $followups_before_resolution = (int)($config['followups_before_resolution'] ?? 0);
        $solutiontemplates_id = (int)($config['solutiontemplates_id'] ?? 0);
        $comment = htmlspecialchars($config['comment'] ?? '');

        // Variáveis da aba 5
        $is_active = (int)($config['is_active'] ?? 1);
        $itemtype = htmlspecialchars($config['itemtype'] ?? 'Ticket');
        $event = htmlspecialchars($config['event'] ?? 'new');
        $attach_documents = (int)($config['attach_documents'] ?? -2);
        $allow_response = (int)($config['allow_response'] ?? 1);
        $notificationtemplates_id = (int)($config['notificationtemplates_id'] ?? 0);
        $target_val = htmlspecialchars($config['target'] ?? '');
        $exclusion_val = htmlspecialchars($config['exclusion'] ?? '');

        // Variáveis da aba 6
        $descriptionRaw = $config['description'] ?? '';
        $description = htmlspecialchars($descriptionRaw);
        $forms_categories_id = (int)($config['forms_categories_id'] ?? 0);
        $generated_id = (int)($config['generated_id'] ?? 0);

        $html = "<div class='{$class}' style='border: 1px solid #ccc; padding: 15px; margin-bottom: 15px;  {$display}'>";
        $html .= "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
        $html .= "      <strong>Item de Configuração</strong>";
        if (!$isSingleBlock) {
            $html .= "      <button type='button' class='btn btn-sm btn-danger btn-remove-config' style='color: white !important;'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
        }
        $html .= "  </div>";
        $html .= "  <input type='hidden' name='items_generated_id[]' class='input-generated-id' value='{$generated_id}'>";

        // Copiar de... (apenas visível se for template OU for um bloco único como no Tab 6)
        $showCopyFrom = ($isTemplate || $isSingleBlock) ? "block" : "none";
        $html .= "  <div style='display: {$showCopyFrom}; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;'>";
        $html .= "      <label style='display: block; margin-bottom: 5px;  font-weight:bold;'>Copiar de...</label>";
        $html .= "      <select name='items_copy_from[]' class='form-select select2-copy-from' style='width: 100%;' data-tab='{$tabnum}'>";
        $html .= "        <option value='0'>--- Nenhum (Criar Básico) ---</option>";
        foreach ($existingModels as $id => $mName) {
            $selected = ($id == $copyFrom) ? 'selected' : '';
            $html .= "        <option value='{$id}' {$selected}>" . htmlspecialchars(ltrim($mName, '- ')) . "</option>";
        }
        $html .= "      </select>";
        $html .= "  </div>";

        $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
        $html .= "      <div style='flex: 1;'>";
        $html .= "          <label style='display: block; margin-bottom: 5px;  font-weight:bold;'>Nome / Título</label>";
        $html .= "          <input type='text' name='items_name[]' class='form-control input-name' style='width: 100%;' value='{$name}' placeholder='Ex: Nome Padrão do Item'>";
        $html .= "      </div>";
        $html .= "  </div>";

        if ($tabnum == 1) {
            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px;  font-weight:bold;'>Tipo</label>";
            $html .= "          <select name='items_type[]' class='form-select input-type' style='width: 100%;'>";
            $html .= "            <option value='1' " . ($type == 1 ? 'selected' : '') . ">Incidente</option>";
            $html .= "            <option value='2' " . ($type == 2 ? 'selected' : '') . ">Requisição</option>";
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 2;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px;  font-weight:bold;'>Categoria ITIL</label>";
            $html .= "          <select name='items_category[]' class='form-select select2-cat input-category' style='width: 100%;'>";
            $html .= "            <option value='0'>--- Nenhuma ---</option>";
            foreach (($extraOptions['categories'] ?? []) as $id => $cName) {
                $selected = ($id == $itilcategoryId) ? 'selected' : '';
                $html .= "            <option value='{$id}' {$selected}>" . htmlspecialchars($cName) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";
        } elseif ($tabnum == 4) {
            // Campos de PendingReason
            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Motivo padrão para pendência</label>";
            $html .= "          <select name='items_is_default[]' class='form-select input-is-default' style='width: 100%;'>";
            $html .= "            <option value='0' " . ($is_default == 0 ? 'selected' : '') . ">Não</option>";
            $html .= "            <option value='1' " . ($is_default == 1 ? 'selected' : '') . ">Sim</option>";
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Pendente por padrão</label>";
            $html .= "          <select name='items_is_pending_per_default[]' class='form-select input-is-pending-per-default' style='width: 100%;'>";
            $html .= "            <option value='0' " . ($is_pending_per_default == 0 ? 'selected' : '') . ">Não</option>";
            $html .= "            <option value='1' " . ($is_pending_per_default == 1 ? 'selected' : '') . ">Sim</option>";
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>&nbsp;<br>Calendário</label>";
            $html .= "          <select name='items_calendars_id[]' class='form-select select2-calendar input-calendars-id' style='width: 100%;'>";
            $html .= "            <option value='0'>--- Nenhum ---</option>";
            foreach (($extraOptions['calendars'] ?? []) as $cid => $cname) {
                $sel = ($cid == $calendars_id) ? 'selected' : '';
                $html .= "            <option value='{$cid}' {$sel}>" . htmlspecialchars($cname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Frequência automática de acompanhamento/solução</label>";
            $html .= "          <select name='items_followup_frequency[]' class='form-select select2-freq input-followup-frequency' style='width: 100%;'>";
            $html .= "            <option value='0'>Desabilitado</option>";
            foreach (($extraOptions['frequencies'] ?? []) as $fid => $fname) {
                $sel = ($fid == $followup_frequency) ? 'selected' : '';
                $html .= "            <option value='{$fid}' {$sel}>" . htmlspecialchars($fname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>&nbsp;<br>Modelo de acompanhamento</label>";
            $html .= "          <select name='items_itilfollowuptemplates_id[]' class='form-select select2-foltpl input-itilfollowuptemplates-id' style='width: 100%;'>";
            $html .= "            <option value='0'>--- Nenhum ---</option>";
            foreach (($extraOptions['itilfollowuptemplates'] ?? []) as $fid => $fname) {
                $sel = ($fid == $itilfollowuptemplates_id) ? 'selected' : '';
                $html .= "            <option value='{$fid}' {$sel}>" . htmlspecialchars($fname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Acompanhamentos antes de solução automática</label>";
            $html .= "          <select name='items_followups_before_resolution[]' class='form-select select2-fbr input-followups-before-resolution' style='width: 100%;'>";
            $html .= "            <option value='0'>Desabilitado</option>";
            foreach (($extraOptions['res_limits'] ?? []) as $rid => $rname) {
                $sel = ($rid == $followups_before_resolution) ? 'selected' : '';
                $html .= "            <option value='{$rid}' {$sel}>" . htmlspecialchars($rname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Modelo de solução</label>";
            $html .= "          <select name='items_solutiontemplates_id[]' class='form-select select2-soltpl input-solutiontemplates-id' style='width: 100%;'>";
            $html .= "            <option value='0'>--- Nenhum ---</option>";
            foreach (($extraOptions['solutiontemplates'] ?? []) as $sid => $sname) {
                $sel = ($sid == $solutiontemplates_id) ? 'selected' : '';
                $html .= "            <option value='{$sid}' {$sel}>" . htmlspecialchars($sname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div>";
            $html .= "      <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Comentários</label>";
            $html .= "      <textarea name='items_comment[]' class='form-control input-comment' style='width: 100%; height: 60px;'>{$comment}</textarea>";
            $html .= "  </div>";

        } elseif ($tabnum == 5) {
            // Campos de Notification
            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Ativo</label>";
            $html .= "          <select name='items_is_active[]' class='form-select input-is-active' style='width: 100%;'>";
            $html .= "            <option value='0' " . ($is_active == 0 ? 'selected' : '') . ">Não</option>";
            $html .= "            <option value='1' " . ($is_active == 1 ? 'selected' : '') . ">Sim</option>";
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Tipo</label>";
            $html .= "          <select name='items_itemtype[]' class='form-select select2-itemtype input-itemtype' style='width: 100%;'>";
            foreach (($extraOptions['itemtypes'] ?? []) as $it => $itname) {
                $sel = ($it == $itemtype) ? 'selected' : '';
                $html .= "            <option value='{$it}' {$sel}>" . htmlspecialchars($itname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Evento</label>";
            $html .= "          <select name='items_event[]' class='form-select select2-event input-event' style='width: 100%;'>";
            foreach (($extraOptions['events'] ?? []) as $ev => $evname) {
                $sel = ($ev == $event) ? 'selected' : '';
                $html .= "            <option value='{$ev}' {$sel}>" . htmlspecialchars($evname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Padrão (Modelo de Notificação)</label>";
            $html .= "          <select name='items_notificationtemplates_id[]' class='form-select select2-tpl input-notificationtemplates-id' style='width: 100%;'>";
            $html .= "            <option value='0'>--- Padrão da Origem ---</option>";
            foreach (($extraOptions['templates'] ?? []) as $tid => $tname) {
                $sel = ($tid == $notificationtemplates_id) ? 'selected' : '';
                $html .= "            <option value='{$tid}' {$sel}>" . htmlspecialchars($tname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Adicionar documentos</label>";
            $html .= "          <select name='items_attach_documents[]' class='form-select input-attach-documents' style='width: 100%;'>";
            $html .= "            <option value='-2' " . ($attach_documents == -2 ? 'selected' : '') . ">Usar configuração global</option>";
            $html .= "            <option value='0' " . ($attach_documents == 0 ? 'selected' : '') . ">Não</option>";
            $html .= "            <option value='1' " . ($attach_documents == 1 ? 'selected' : '') . ">Sim</option>";
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Permitir resposta</label>";
            $html .= "          <select name='items_allow_response[]' class='form-select input-allow-response' style='width: 100%;'>";
            $html .= "            <option value='0' " . ($allow_response == 0 ? 'selected' : '') . ">Não</option>";
            $html .= "            <option value='1' " . ($allow_response == 1 ? 'selected' : '') . ">Sim</option>";
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div style='display: flex; gap: 15px; align-items: flex-start; margin-bottom: 10px;'>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Destinatário</label>";
            $html .= "          <select name='items_target[]' class='form-select select2-target input-target' style='width: 100%;'>";
            $html .= "            <option value=''>--- Nenhum ---</option>";
            foreach (($extraOptions['all_targets'] ?? []) as $tkey => $tname) {
                $sel = ($tkey == $target_val) ? 'selected' : '';
                $html .= "            <option value='{$tkey}' {$sel}>" . htmlspecialchars($tname) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "      <div style='flex: 1;'>";
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Exclusão</label>";
            $html .= "          <select name='items_exclusion[]' class='form-select select2-exclusion input-exclusion' style='width: 100%;'>";
            $html .= "            <option value=''>--- Nenhuma ---</option>";
            foreach (($extraOptions['all_exclusions'] ?? []) as $ekey => $ename) {
                $sel = ($ekey == $exclusion_val) ? 'selected' : '';
                $html .= "            <option value='{$ekey}' {$sel}>" . htmlspecialchars($ename) . "</option>";
            }
            $html .= "          </select>";
            $html .= "      </div>";
            $html .= "  </div>";

            $html .= "  <div>";
            $html .= "      <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Comentários</label>";
            $html .= "      <textarea name='items_comment[]' class='form-control input-comment' style='width: 100%; height: 60px;'>{$comment}</textarea>";
            $html .= "  </div>";

        } elseif ($tabnum == 6) {
            $html .= "  <input type='hidden' name='items_is_active[]' class='input-is-active' value='1'>";

            $html .= "  <div style='margin-bottom: 10px;'>";
            if (!$isTemplate) {
                // Renderiza o macro usado pelo formulário padrão do GLPI.
                $twig = \Glpi\Application\View\TemplateRenderer::getInstance()->getEnvironment();
                $categoryTemplate = $twig->createTemplate(<<<'TWIG'
{% import 'components/form/fields_macros.html.twig' as fields %}
{{ fields.dropdownField('Glpi\\Form\\Category', 'items_forms_categories_id[]', category_id, 'Configuração do catálogo de serviços - categoria', {'is_horizontal': false, 'full_width': true, 'add_field_class': 'glpinewentity-form-category'}) }}
TWIG);
                $categoryDropdownHtml = $categoryTemplate->render(['category_id' => $forms_categories_id]);
                $html .= "          <div class='form-category-wrapper' style='margin-bottom: 10px;'>" . $categoryDropdownHtml . "</div>";
            } else {
                $html .= "          <div class='form-category-wrapper' style='margin-bottom: 10px;'>";
                $html .= "              <input type='hidden' name='items_forms_categories_id[]' value='0'>";
                $html .= "          </div>";
            }
            $html .= "          <label style='display: block; margin-bottom: 5px; font-weight:bold;'>Configuração do catálogo de serviços - descrição</label>";

            $encodedDescription = base64_encode($descriptionRaw);
            if (!$isTemplate) {
                $html .= "          <div class='input-description-wrapper richtext-pending' data-field-name='items_description[]' data-field-value='{$encodedDescription}'>";
                $html .= "              <textarea name='items_description[]' class='form-control input-description' style='width: 100%; height: 80px;'>{$description}</textarea>";
                $html .= "          </div>";
            } else {
                $html .= "          <textarea name='items_description[]' class='form-control input-description' style='width: 100%; height: 80px;'>{$description}</textarea>";
            }
            $html .= "      </div>";

            $illustration = htmlspecialchars($config['illustration'] ?? $config['icon'] ?? 'request-service');

            $twig_code = "{% import 'components/form/fields_macros.html.twig' as fields %}{{ fields.illustrationField('items_illustration[]', illustration_value, 'Ilustração', {'is_horizontal': false, 'full_width': true}) }}";
            $twig = \Glpi\Application\View\TemplateRenderer::getInstance()->getEnvironment();
            $template = $twig->createTemplate($twig_code);
            $illustrationHtml = $template->render(['illustration_value' => $illustration]);

            // Mantém a ilustração em uma linha própria, sem limitar a largura da categoria.
            $html .= "      <div class='illustration-wrapper' style='width: 200px; margin-top: 10px;'>";
            $html .=            $illustrationHtml;
            $html .= "      </div>";
        } else {
            $html .= "  <input type='hidden' name='items_type[]' class='input-type' value='1'>";
            $html .= "  <input type='hidden' name='items_category[]' class='input-category' value='0'>";
        }

        // Para tab 6, o $hasContentField deve ser false na lógica base, mas garantimos que não imprima textarea de content
        if ($hasContentField && $tabnum != 6) {
            $html .= "  <div>";
            $html .= "      <label style='display: block; margin-bottom: 5px;  font-weight:bold;'>Conteúdo / Texto Base</label>";
            if (in_array($tabnum, [1, 2, 3]) && !$isTemplate) {
                // Blocos visíveis: renderiza textarea simples com marcador para inicialização AJAX do TinyMCE
                // (Não podemos usar Html::textarea com enable_richtext aqui porque o script inline
                //  do TinyMCE quebra quando o conteúdo da aba é carregado via AJAX/DOMEval do jQuery)
                $encodedContent = base64_encode($contentRaw);
                $html .= "      <div class='input-content-wrapper richtext-pending' data-field-name='items_content[]' data-field-value='{$encodedContent}'>";
                $html .= "          <textarea name='items_content[]' class='form-control input-content' style='width: 100%; height: 80px;'>{$content}</textarea>";
                $html .= "      </div>";
            } elseif (in_array($tabnum, [1, 2, 3]) && $isTemplate) {
                // Template oculto: textarea simples (TinyMCE será inicializado via JS ao clonar)
                $html .= "      <textarea name='items_content[]' class='form-control input-content' style='width: 100%; height: 80px;' placeholder='Texto padrão para este item.'>{$content}</textarea>";
            } else {
                $html .= "      <textarea name='items_content[]' class='form-control input-content' style='width: 100%; height: 60px;' placeholder='Texto padrão para este item.'>{$content}</textarea>";
            }
            $html .= "  </div>";
        } else {
            $html .= "      <input type='hidden' name='items_content[]' class='input-content' value=''>";
        }

        $html .= "</div>";
        return $html;
    }

    private static function getDefaultConfigsForTab($tabnum) {
        switch ($tabnum) {
            case 1: // Modelos de Chamado
                return [
                    ['name' => 'SIGLA - Incidente Padrão', 'content' => '', 'type' => 1, 'copy_from' => 0],
                    ['name' => 'SIGLA - Requisição Padrão', 'content' => '', 'type' => 2, 'copy_from' => 0],
                ];
            case 2: // Respostas Básicas
                return [
                    ['name' => 'SIGLA - Acompanhamento Inicial', 'content' => 'Olá, recebemos sua solicitação e já estamos analisando.', 'copy_from' => 0],
                    ['name' => 'SIGLA - Solicitação de Informação', 'content' => 'Para prosseguirmos com o atendimento, por favor nos informe mais detalhes sobre...', 'copy_from' => 0],
                ];
            case 3: // Soluções Básicas
                return [
                    ['name' => 'SIGLA - Incidente Resolvido', 'content' => 'O problema foi identificado e corrigido.', 'copy_from' => 0],
                    ['name' => 'SIGLA - Requisição Atendida', 'content' => 'A solicitação foi atendida com sucesso conforme pedido.', 'copy_from' => 0],
                ];
            case 4: // Motivos de Pendências
                return [
                    ['name' => 'SIGLA - Aguardando Retorno do Usuário', 'content' => '', 'copy_from' => 0],
                    ['name' => 'SIGLA - Aguardando Terceiros', 'content' => '', 'copy_from' => 0],
                ];
            case 5: // Notificações
                return [
                    ['name' => 'SIGLA - Novo Chamado (Ticket)', 'content' => 'Um novo chamado foi aberto: [TICKET_ID]', 'copy_from' => 0],
                    ['name' => 'SIGLA - Chamado Solucionado', 'content' => 'O chamado [TICKET_ID] foi solucionado.', 'copy_from' => 0],
                ];
            case 6: // Formulário Padrão
                return [
                    ['name' => 'SIGLA - Formulário de Atendimento', 'content' => '', 'copy_from' => 0],
                ];
            default:
                return [];
        }
    }

    public function showForm($ID, array $options = []) {
        global $CFG_GLPI, $DB;
        $sectorId = $ID;
        $isEdit = ($ID > 0);
        $sectorObj = $this;

        if ($isEdit) {
            // A edição continua usando a validação padrão do registro existente.
            $this->initForm($ID, $options);
        } else {
            // A inclusão é um wizard próprio e já é protegida pelo direito do plugin.
            // Evita que o GLPI exija CREATE no registro auxiliar antes de exibir o formulário.
            $this->getEmpty();
        }

        $def_sector_name = '';
        $def_sector_abbr = '';
        $def_parent_entity = 0;
        $def_category_names = '';
        $def_subgroups = [];
        // A inclusão usa a mesma estrutura consumida pela edição ao montar os perfis.
        $def_profiles = [
            'admin' => ['id' => 0, 'emails' => []],
            'support' => ['id' => 0, 'emails' => []],
            'transfer' => ['id' => 0, 'emails' => []],
            'custom' => []
        ];

        if ($isEdit) {
            $meta = json_decode($sectorObj->fields['metadata'], true) ?: [];
            $def_sector_name = $sectorObj->fields['sector_name'];
            $def_sector_abbr = $sectorObj->fields['sector_abbr'];
            $def_parent_entity = $sectorObj->fields['entities_id'];

            // Reconstruir subgrupos e técnicos a partir do banco (live)
            $def_subgroups = [];
            $live_success = false;
            if (!empty($meta['entity_id'])) {
                global $DB;
                $parentGroupIter = $DB->request([
                    'SELECT' => ['id', 'groups_id'],
                    'FROM'   => 'glpi_groups',
                    'WHERE'  => [
                        'entities_id' => $meta['entity_id']
                    ]
                ]);

                $parentGroupId = 0;
                foreach ($parentGroupIter as $row) {
                    if (empty($row['groups_id'])) {
                        $parentGroupId = $row['id'];
                        break;
                    }
                }

                if ($parentGroupId > 0) {
                    $def_subgroups[] = ['name' => '', 'techs' => []]; // Bloco Pai

                    $subgroupsIter = $DB->request([
                        'SELECT' => ['id', 'name', 'groups_id'],
                        'FROM'   => 'glpi_groups',
                        'WHERE'  => [
                            'entities_id' => $meta['entity_id'],
                            'id' => ['<>', $parentGroupId]
                        ],
                        'ORDER'  => 'id ASC'
                    ]);

                    $sgMap = [$parentGroupId => '-1']; // group_id => índice no form (0-based)
                    $formIdx = 0;

                    $rows = [];
                    foreach ($subgroupsIter as $row) {
                        $rows[] = $row;
                    }

                    foreach ($rows as $row) {
                        // Se a view nativa mostra nomes certos mas tem um prefixo ou algo assim, pegamos o nome real
                        $def_subgroups[] = ['name' => $row['name'], 'techs' => [], 'parent' => '-1'];
                        $sgMap[$row['id']] = (string)$formIdx++;
                    }

                    foreach ($rows as $row) {
                        $myFormIdx = (int)$sgMap[$row['id']];
                        $myArrayIdx = $myFormIdx + 1; // o índice 0 de $def_subgroups é o pai
                        $parentGlpiId = $row['groups_id'];
                        if (isset($sgMap[$parentGlpiId])) {
                            if ($parentGlpiId == $parentGroupId) {
                                $def_subgroups[$myArrayIdx]['parent'] = '-1';
                            } else {
                                $def_subgroups[$myArrayIdx]['parent'] = (string)$sgMap[$parentGlpiId];
                            }
                        }
                    }

                    // Buscar emails dos técnicos
                    $allGroupIds = array_keys($sgMap);
                    $techsIter = $DB->request([
                        'SELECT' => ['glpi_groups_users.groups_id', 'glpi_useremails.email'],
                        'FROM'   => 'glpi_groups_users',
                        'INNER JOIN' => [
                            'glpi_useremails' => [
                                'ON' => [
                                    'glpi_groups_users' => 'users_id',
                                    'glpi_useremails'   => 'users_id'
                                ]
                            ]
                        ],
                        'WHERE'  => [
                            'glpi_groups_users.groups_id' => $allGroupIds
                        ]
                    ]);

                    foreach ($techsIter as $row) {
                        $gId = $row['groups_id'];
                        if (isset($sgMap[$gId])) {
                            $formIdxOrRoot = (int)$sgMap[$gId];
                            $arrayIdx = $formIdxOrRoot === -1 ? 0 : $formIdxOrRoot + 1;
                            $def_subgroups[$arrayIdx]['techs'][] = $row['email'];
                        }
                    }
                    $live_success = true;
                }
            }

            // Fallback para metadata antiga se a query live falhar ou não houver entidade criada
            if (!$live_success && !empty($meta['groups'])) {
                foreach ($meta['groups'] as $g) {
                    $gName = $g['name'];
                    $isParent = (strpos($gName, '(' . $sectorObj->fields['sector_abbr'] . ')') !== false);
                    $sgName = $isParent ? '' : $gName;
                    $def_subgroups[] = [
                        'name' => $sgName,
                        'techs' => [],
                        'parent' => '-1'
                    ];
                }

                if (!empty($meta['technicians'])) {
                    foreach ($meta['technicians'] as $tech) {
                        $parts = explode(' -> ', $tech['email']);
                        $email = trim($parts[0]);
                        $groupTarget = trim($parts[1] ?? '');

                        foreach ($def_subgroups as &$sg) {
                            if (($groupTarget === 'Pai' && $sg['name'] === '') || ($groupTarget !== 'Pai' && $sg['name'] === $groupTarget)) {
                                $sg['techs'][] = $email;
                                break;
                            }
                        }
                        unset($sg);
                    }
                }
            }

            foreach ($def_subgroups as &$sg) {
                $sg['techs'] = implode("\n", array_unique($sg['techs']));
            }
            unset($sg); // IMPORTANTE: quebra a referência para evitar corrupção no próximo foreach

            // Se por acaso vier vazio, garante pelo menos um bloco
            if (empty($def_subgroups)) {
                $def_subgroups[0] = ['name' => '', 'techs' => ''];
            }


            // Reconstruir categorias a partir do banco para manter a hierarquia com hífens
            $catList = [];
            if (!empty($meta['entity_id'])) {
                global $DB;
                $cat_iterator = $DB->request([
                    'SELECT' => ['id', 'name', 'itilcategories_id'],
                    'FROM'   => 'glpi_itilcategories',
                    'WHERE'  => ['entities_id' => $meta['entity_id']]
                ]);

                $cats = [];
                $children = [];
                foreach ($cat_iterator as $row) {
                    $cats[$row['id']] = $row;
                    $children[$row['itilcategories_id']][] = $row['id'];
                }

                $buildTree = function ($parentId, $depth) use (&$buildTree, &$catList, &$cats, &$children) {
                    if (isset($children[$parentId])) {
                        foreach ($children[$parentId] as $childId) {
                            $prefix = str_repeat('-', $depth);
                            $catList[] = $prefix . $cats[$childId]['name'];
                            $buildTree($childId, $depth + 1);
                        }
                    }
                };

                $buildTree(0, 0);
            }

            // Fallback caso a entidade não tenha sido criada ou não tenha categorias no DB
            if (empty($catList) && !empty($meta['categories'])) {
                foreach ($meta['categories'] as $c) {
                    $catList[] = $c['name'];
                }
            }
            $def_category_names = implode("\n", $catList);

            // Reconstruir Perfis (Buscando diretamente do banco para a entidade criada)
            if (!empty($meta['entity_id'])) {
                global $DB;
                $pu_iterator = $DB->request([
                    'SELECT' => [
                        'glpi_profiles_users.profiles_id',
                        'glpi_useremails.email',
                        'glpi_profiles.name AS profile_name'
                    ],
                    'FROM'   => 'glpi_profiles_users',
                    'INNER JOIN' => [
                        'glpi_useremails' => [
                            'ON' => [
                                'glpi_profiles_users' => 'users_id',
                                'glpi_useremails' => 'users_id'
                            ]
                        ],
                        'glpi_profiles' => [
                            'ON' => [
                                'glpi_profiles_users' => 'profiles_id',
                                'glpi_profiles' => 'id'
                            ]
                        ]
                    ],
                    'WHERE'  => [
                        'glpi_profiles_users.entities_id' => $meta['entity_id']
                    ]
                ]);

                $profile_map = [];
                foreach ($pu_iterator as $row) {
                    $pid = $row['profiles_id'];
                    $pname = $row['profile_name'];
                    $email = $row['email'];

                    if (!isset($profile_map[$pid])) {
                        if (str_ends_with($pname, ' - Admin')) {
                            $profile_map[$pid] = 'admin';
                        } elseif (str_ends_with($pname, ' - Atendimento')) {
                            $profile_map[$pid] = 'support';
                        } elseif (str_ends_with($pname, ' - Transferência de Chamados')) {
                            $profile_map[$pid] = 'transfer';
                        } else {
                            $def_profiles['custom'][] = [
                                'id' => $pid,
                                'emails' => []
                            ];
                            $profile_map[$pid] = 'custom_' . (count($def_profiles['custom']) - 1);
                        }
                    }

                    $map_key = $profile_map[$pid];
                    if (str_starts_with($map_key, 'custom_')) {
                        $idx = (int)str_replace('custom_', '', $map_key);
                        $def_profiles['custom'][$idx]['emails'][] = $email;
                    } else {
                        $def_profiles[$map_key]['emails'][] = $email;
                        $def_profiles[$map_key]['id'] = $pid;
                    }
                }
            }
        }

        // -----------------------------------------------------------------------
        // RENDERIZAÇÃO DA PÁGINA
        // -----------------------------------------------------------------------


        global $CFG_GLPI;
        $form_url = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php';

        echo "<div class='center' style='margin-top: 20px;'>";
        echo "<style>
        .tab_cadre_fixe td {
            vertical-align: top !important;
        }
        .tab_cadre_fixe td:not([style*=\"padding: 0\"]) {
            padding-top: 15px !important;
            padding-bottom: 15px !important;
        }
        /* Remove o limite legado do GLPI que corta as combos (Select2) do wizard. */
        .tab_cadre_fixe .select2-container .select2-selection.select2-selection--single,
        .tab_cadre_fixe .select2-container {
            max-width: none !important;
        }
        /* Afastar texto da aba da borda direita */
        .glpi-tabs .nav-item .nav-link {
            padding-right: 20px !important;
        }
        </style>";

        // =====================================================================
        // FORMULÁRIO WIZARD
        // =====================================================================

        echo "<form method='post' action='" . $form_url . "' id='form_wizard'>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . \Session::getNewCSRFToken() . "'>";
        echo "<input type='hidden' name='process_wizard' value='1'>";
        if ($isEdit) {
            echo "<input type='hidden' name='id' value='{$sectorId}'>";
        }

        // ── Título Principal ──
        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr><th colspan='2' style='font-size: 1.2em;'>";
        echo "Nova Entidade para Central de Serviços";
        echo "</th></tr>";
        echo "</table>";

        echo "<hr style='width: 750px; border-top: 3px solid; opacity: 0.2; margin: 20px auto 10px auto;'>";

        // ── Bloco 1: Dados da Entidade ──
        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr><th colspan='2'><i class='fas fa-building' style='margin-right: 5px;'></i> Dados da Entidade</th></tr>";

        // Entidade-Pai (dropdown nativo do GLPI)
        echo "<tr class='tab_bg_1'>";
        echo "      <td style='width: 35%;'>Entidade-Pai <span style='color:red;'>*</span></td>";
        echo "      <td>";
        Entity::dropdown([
            'name'  => 'parent_entity',
            'value' => $def_parent_entity,
            'width' => '100%',
        ]);
        echo "          <br><small class='text-muted'>Selecione sob qual entidade o novo setor será criado.</small>";
        echo "      </td>";
        echo "</tr>";

        // Nome do Setor
        echo "<tr class='tab_bg_1'>";
        echo "<td>Nome do Setor <span style='color:red;'>*</span></td>";
        echo "<td>";
        echo "<input type='text' name='sector_name' class='form-control' style='width: 100%;' placeholder='Ex: Departamento de Computação' value='" . htmlspecialchars($def_sector_name) . "' required>";
        echo "</td>";
        echo "</tr>";

        // Sigla
        echo "<tr class='tab_bg_1'>";
        echo "<td>Sigla <span style='color:red;'>*</span></td>";
        echo "<td>";
        echo "<input type='text' name='sector_abbr' class='form-control' style='width: 100%;' placeholder='Ex: DC' value='" . htmlspecialchars($def_sector_abbr) . "' maxlength='20' required>";
        echo "<br><small class='text-muted'>A entidade será criada com o mesmo nome da sigla.</small>";
        echo "</td>";
        echo "</tr>";



        echo "</table>";

        echo "<hr style='width: 750px; border-top: 3px solid; opacity: 0.2; margin: 20px auto 10px auto;'>";

        // ── Bloco 2: Perfis ──
        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr><th colspan='2'><i class='fas fa-id-card' style='margin-right: 5px;'></i> Perfis</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' style='padding: 15px;'>";

        // Carregar lista de perfis do banco
        $profiles = [];
        $profile_obj = new Profile();
        foreach ($profile_obj->find([], ['name']) as $p) {
            $profiles[$p['id']] = $p['name'];
        }

        echo "<div id='perfis-padrao-section'>";

        // Admin
        echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;'>";
        echo "  <div style='display: flex; gap: 10px;'>";
        echo "    <div style='flex: 1;'>";
        echo "      <label style='display: block; margin-bottom: 5px; '>Perfil Padrão</label>";
        $adminVal = $def_sector_abbr ? htmlspecialchars($def_sector_abbr) . ' - Admin' : '';
        echo "      <input type='text' id='profile_admin' name='profiles_default[]' class='form-control' style='width: 100%; border: none; ' readonly value='{$adminVal}'>";
        echo "    </div>";
        echo "    <div style='flex: 1;'>";
        echo "      <label style='display: block; margin-bottom: 5px; '>Copiar de...</label>";
        echo "      <select name='copy_profile_admin' class='form-select profile-select2' style='width: 100%;'>";
        echo "        <option value='0'>-----</option>";
        $adminId = $def_profiles['admin']['id'] ?? 0;
        foreach ($profiles as $pid => $pname) {
            if ($adminId > 0) {
                $selected = ($pid == $adminId) ? 'selected' : '';
            } else {
                $selected = (strpos($pname, '[Padrão] Admin') !== false) ? 'selected' : '';
            }
            echo "        <option value='{$pid}' {$selected}>" . htmlspecialchars($pname) . "</option>";
        }
        echo "      </select>";
        echo "    </div>";
        echo "  </div>";
        echo "  <div>";
        echo "    <label style='display: block; margin-bottom: 5px;  font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
        $adminEmails = htmlspecialchars(implode("\n", $def_profiles['admin']['emails'] ?? []));
        echo "    <textarea name='users_profile_admin' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$adminEmails}</textarea>";
        echo "  </div>";
        echo "</div>";

        // Atendimento
        echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;'>";
        echo "  <div style='display: flex; gap: 10px;'>";
        echo "    <div style='flex: 1;'>";
        echo "      <label style='display: block; margin-bottom: 5px; '>Perfil Padrão</label>";
        $supportVal = $def_sector_abbr ? htmlspecialchars($def_sector_abbr) . ' - Atendimento' : '';
        echo "      <input type='text' id='profile_support' name='profiles_default[]' class='form-control' style='width: 100%; border: none; ' readonly value='{$supportVal}'>";
        echo "    </div>";
        echo "    <div style='flex: 1;'>";
        echo "      <label style='display: block; margin-bottom: 5px; '>Copiar de...</label>";
        echo "      <select name='copy_profile_support' class='form-select profile-select2' style='width: 100%;'>";
        echo "        <option value='0'>-----</option>";
        $supportId = $def_profiles['support']['id'] ?? 0;
        foreach ($profiles as $pid => $pname) {
            if ($supportId > 0) {
                $selected = ($pid == $supportId) ? 'selected' : '';
            } else {
                $selected = (strpos($pname, '[Padrão] Atendimento') !== false) ? 'selected' : '';
            }
            echo "        <option value='{$pid}' {$selected}>" . htmlspecialchars($pname) . "</option>";
        }
        echo "      </select>";
        echo "    </div>";
        echo "  </div>";
        echo "  <div>";
        echo "    <label style='display: block; margin-bottom: 5px;  font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
        $supportEmails = htmlspecialchars(implode("\n", $def_profiles['support']['emails'] ?? []));
        echo "    <textarea name='users_profile_support' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$supportEmails}</textarea>";
        echo "  </div>";
        echo "</div>";

        // Transferência de Chamados
        echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;'>";
        echo "  <div style='display: flex; gap: 10px;'>";
        echo "    <div style='flex: 1;'>";
        echo "      <label style='display: block; margin-bottom: 5px; '>Perfil Padrão</label>";
        $transferVal = $def_sector_abbr ? htmlspecialchars($def_sector_abbr) . ' - Transferência de Chamados' : '';
        echo "      <input type='text' id='profile_transfer' name='profiles_default[]' class='form-control' style='width: 100%; border: none; ' readonly value='{$transferVal}'>";
        echo "    </div>";
        echo "    <div style='flex: 1;'>";
        echo "      <label style='display: block; margin-bottom: 5px; '>Copiar de...</label>";
        echo "      <select name='copy_profile_transfer' class='form-select profile-select2' style='width: 100%;'>";
        echo "        <option value='0'>-----</option>";
        $transferId = $def_profiles['transfer']['id'] ?? 0;
        foreach ($profiles as $pid => $pname) {
            if ($transferId > 0) {
                $selected = ($pid == $transferId) ? 'selected' : '';
            } else {
                $selected = (strpos($pname, '[Padrão] Transferência de Chamados') !== false) ? 'selected' : '';
            }
            echo "        <option value='{$pid}' {$selected}>" . htmlspecialchars($pname) . "</option>";
        }
        echo "      </select>";
        echo "    </div>";
        echo "  </div>";
        echo "  <div>";
        echo "    <label style='display: block; margin-bottom: 5px;  font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
        $transferEmails = htmlspecialchars(implode("\n", $def_profiles['transfer']['emails'] ?? []));
        echo "    <textarea name='users_profile_transfer' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$transferEmails}</textarea>";
        echo "  </div>";
        echo "</div>";

        echo "</div>"; // fecha #perfis-padrao-section

        echo "<br><small class='text-muted'>Os 'Perfis Padrão' são criados automaticamente com base na SIGLA e não podem ser apagados, mas você pode adicionar um novo em 'Adicionar Perfil'.</small>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' style='padding: 0;'>";
        echo "<div id='profiles-container'>";

        // Template oculto para adicionar perfis customizados
        echo "<div class='profile-block template' style='border: 1px solid #ccc; padding: 10px; margin: 10px;  display: none;'>";
        echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
        echo "      <strong>Perfil Adicional</strong>";
        echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-profile' style='color: white !important;'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
        echo "  </div>";
        echo "  <div style='display: flex; gap: 10px; align-items: flex-start;'>";
        echo "      <div style='flex: 1;'>";
        echo "          <label style='display: block; margin-bottom: 5px; '>Nome do Perfil</label>";
        echo "          <input type='text' class='form-control profile-input' style='width: 100%;' placeholder='Ex: SIGLA - Coordenador' name='name_profile_custom[]'>";
        echo "      </div>";
        echo "      <div style='flex: 1;'>";
        echo "          <label style='display: block; margin-bottom: 5px; '>Copiar de...</label>";
        echo "          <select name='copy_profile_custom[]' class='form-select profile-select2' style='width: 100%;'>";
        echo "            <option value='0'>-----</option>";
        foreach ($profiles as $pid => $pname) {
            echo "            <option value='{$pid}'>" . htmlspecialchars($pname) . "</option>";
        }
        echo "          </select>";
        echo "      </div>";
        echo "  </div>";
        echo "  <div style='margin-top: 10px;'>";
        echo "      <label style='display: block; margin-bottom: 5px;  font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
        echo "      <textarea name='users_profile_custom[]' class='form-control profile-users-input' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
        echo "  </div>";
        echo "</div>";

        // Renderiza perfis customizados existentes na edição
        if (!empty($def_profiles['custom'])) {
            foreach ($def_profiles['custom'] as $cProf) {
                $cEmails = htmlspecialchars(implode("\n", $cProf['emails'] ?? []));
                $cId = $cProf['id'];

                echo "<div class='profile-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; '>";
                echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
                echo "      <strong>Perfil Adicional</strong>";
                echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-profile' style='color: white !important;'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
                echo "  </div>";
                echo "  <div style='display: flex; gap: 10px; align-items: flex-start;'>";
                echo "      <div style='flex: 1;'>";
                echo "          <label style='display: block; margin-bottom: 5px; '>Nome do Perfil</label>";
                echo "          <input type='text' class='form-control profile-input' name='name_profile_custom[]' style='width: 100%;' placeholder='Ex: SIGLA - Coordenador' value='" . htmlspecialchars($cProf['name']) . "'>";
                echo "          <small class='text-muted'>O nome original não é carregado na edição, preencha novamente se desejar salvar outro.</small>";
                echo "      </div>";
                echo "      <div style='flex: 1;'>";
                echo "          <label style='display: block; margin-bottom: 5px; '>Copiar de...</label>";
                echo "          <select name='copy_profile_custom[]' class='form-select profile-select2' style='width: 100%;'>";
                echo "            <option value='0'>-----</option>";
                foreach ($profiles as $pid => $pname) {
                    $selected = ($pid == $cId) ? 'selected' : '';
                    echo "            <option value='{$pid}' {$selected}>" . htmlspecialchars($pname) . "</option>";
                }
                echo "          </select>";
                echo "      </div>";
                echo "  </div>";
                echo "  <div style='margin-top: 10px;'>";
                echo "      <label style='display: block; margin-bottom: 5px;  font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
                echo "      <textarea name='users_profile_custom[]' class='form-control profile-users-input' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$cEmails}</textarea>";
                echo "  </div>";
                echo "</div>";
            }
        }

        echo "</div>";
        echo "<div style='padding: 0 10px 10px 10px;'>";
        echo "<button type='button' class='btn btn-success btn-sm' style='color: white !important;' id='btn-add-profile'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar Perfil</button>";
        echo "</div>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";

        echo "<hr style='width: 750px; border-top: 3px solid; opacity: 0.2; margin: 20px auto 10px auto;'>";


        // ── Bloco 3: Grupos e Técnicos Atendentes ──
        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr><th colspan='2'><i class='fas fa-users-cog' style='margin-right: 5px;'></i> Grupos e Técnicos Atendentes</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' style='padding: 0;'>";
        echo "<div id='subgroups-container'>";

        if (empty($def_subgroups) || count($def_subgroups) <= 1) {
            // Se não tem subgrupos, ou só tem o pai (índice 0), renderiza 1 bloco vazio
            $sg0Name = htmlspecialchars($def_subgroups[0]['name'] ?? '');
            $sg0Techs = htmlspecialchars($def_subgroups[0]['techs'] ?? '');

            echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; '>";
            echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
            echo "      <strong>Subgrupo <span class='sg-index'>1</span></strong>";
            echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='color: white !important;' style='display:none;'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
            echo "  </div>";
            echo "  <div style='margin-bottom: 10px;'>";
            echo "      <label>Nome do Subgrupo</label>";
            echo "      <input type='text' name='subgroups[0][name]' class='form-control sg-name-input' style='width: 100%;' value='" . $sg0Name . "' placeholder='Ex: Suporte Nível 1 (Deixe em branco para alocar no Grupo Pai)'>";
            echo "  </div>";
            echo "  <div style='margin-bottom: 10px;'>";
            echo "      <label>Grupo Pai</label>";
            echo "      <div class='parent-wrapper'>";
            echo "          <input type='text' class='form-control' value='(SIGLA)' readonly>";
            echo "          <input type='hidden' name='subgroups[0][parent]' value='-1'>";
            echo "      </div>";
            echo "  </div>";
            echo "  <div>";
            echo "      <label>E-mails dos Técnicos Atendentes</label>";
            echo "      <textarea name='subgroups[0][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este subgrupo. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>" . $sg0Techs . "</textarea>";
            echo "      <small class='text-muted'>Devem estar cadastrados no GLPI. Se informar um subgrupo, os técnicos irão EXCLUSIVAMENTE para ele. Senão, irão para o Grupo Pai <strong>({SIGLA})</strong>.</small>";
            echo "  </div>";
            echo "</div>";
        } else {
            // Tem subgrupos (além do pai). O índice 0 no $def_subgroups é o pai.
            // Se a pessoa preencheu subgrupos, o metadata os salvou a partir do índice 1.
            $i = 0;
            foreach ($def_subgroups as $idx => $sg) {
                if ($idx === 0) {
                    continue;
                } // Pula o grupo pai que só foi salvo no metadata, mas não no form

                $sgName = htmlspecialchars($sg['name']);
                $sgTechs = htmlspecialchars($sg['techs'] ?? '');

                echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; '>";
                echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
                echo "      <strong>Subgrupo <span class='sg-index'>".($i + 1)."</span></strong>";
                echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='color: white !important;' style='".($i == 0 ? 'display:none;' : '')."'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
                echo "  </div>";
                echo "  <div style='margin-bottom: 10px;'>";
                echo "      <label>Nome do Subgrupo</label>";
                echo "      <input type='text' name='subgroups[{$i}][name]' class='form-control sg-name-input' style='width: 100%;' value='{$sgName}' placeholder='Ex: Suporte Nível 1 (Deixe em branco para alocar no Grupo Pai)'>";
                echo "  </div>";
                echo "  <div style='margin-bottom: 10px;'>";
                echo "      <label>Grupo Pai</label>";
                echo "      <div class='parent-wrapper'>";
                echo "          <select name='subgroups[{$i}][parent]' class='form-select sg-parent-select' style='width: 100%;'>";
                echo "              <option value='-1' " . (($sg['parent'] ?? '-1') == '-1' ? 'selected' : '') . ">(SIGLA)</option>";
                for ($prevFormIdx = 0; $prevFormIdx < $i; $prevFormIdx++) {
                    $prevName = htmlspecialchars($def_subgroups[$prevFormIdx + 1]['name'] ?? '');
                    if (!empty($prevName)) {
                        $selected = (($sg['parent'] ?? '') == (string)$prevFormIdx) ? 'selected' : '';
                        echo "              <option value='{$prevFormIdx}' {$selected}>{$prevName}</option>";
                    }
                }
                echo "          </select>";
                echo "      </div>";
                echo "  </div>";
                echo "  <div>";
                echo "      <label>E-mails dos Técnicos Atendentes</label>";
                echo "      <textarea name='subgroups[{$i}][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este subgrupo. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$sgTechs}</textarea>";
                echo "      <small class='text-muted'>Na edição, carregamos os e-mails salvos na base da entidade. Se quiser sincronizar, modifique e salve.</small>";
                echo "  </div>";
                echo "</div>";
                $i++;
            }
        }

        echo "</div>"; // Fim subgroups-container

        echo "<div style='margin: 10px;'>";
        echo "  <button type='button' id='btn-add-subgroup' class='btn btn-sm btn-primary'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar outro Subgrupo</button>";
        echo "</div>";

        echo "</td>";
        echo "</tr>";

        echo "</table>";

        echo "<hr style='width: 750px; border-top: 3px solid; opacity: 0.2; margin: 20px auto 10px auto;'>";

        // ── Bloco 4: Catálogo de Serviços ──
        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr><th colspan='2'><i class='fas fa-clipboard-list' style='margin-right: 5px;'></i> Catálogo de Serviços (Categorias ITIL)</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td style='width: 35%;'>Categorias de Serviço <span style='color:red;'>*</span></td>";
        echo "<td>";
        echo "<textarea name='category_names' class='form-control' style='width: 100%; height: 160px; overflow-y: scroll;' placeholder='Uma categoria por linha. Use hífen (-) para subcategorias.&#10;Ex:&#10;Hardware&#10;- Manutenção de Hardware&#10;-- Troca de Peças&#10;Software&#10;- Instalação de Software' required>" . htmlspecialchars($def_category_names) . "</textarea>";
        echo "<br><small class='text-muted'>Cada categoria será vinculada exclusivamente à nova entidade, habilitada para Incidentes e Requisições.<br><strong>Importante:</strong> O sistema só identificará a hierarquia (Categorias Pai e Filha) se você usar o hífen (-) no início da linha correspondente.</small>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";



        echo "<hr style='width: 750px; border-top: 3px solid; opacity: 0.2; margin: 20px auto 10px auto;'>";

        // ── Botão Submeter ──
        echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
        echo "<tr class='tab_bg_2'>";
        echo "<td class='center' style='padding: 15px;'>";
        $btnTitle = $isEdit ? 'Salvar Modificações' : 'Criar Infraestrutura da Entidade';

        echo "<button type='submit' id='btn-submit-wizard' class='btn btn-primary' style='font-size: 1.05em; padding: 8px 30px;'>";
        echo $btnTitle;
        echo "</button>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";

        Html::closeForm();

        echo "<script>
                $(function() {
                    function validateEmailsStr(str) {
                        let cleanStr = str.trim();
                        if (cleanStr === '') return false;
                        let emails = cleanStr.split(/[\\n,]+/);
                        let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        for (let i = 0; i < emails.length; i++) {
                            let e = emails[i].trim();
                            if (e !== '' && !emailRegex.test(e)) {
                                return false;
                            }
                        }
                        return true;
                    }

                    let index = $('.subgroup-block').length;

                    function getSigla() {
                        let val = $('input[name=\"sector_abbr\"]').val().trim();
                        return val !== '' ? '(' + val + ')' : '';
                    }

                    function updateAllParentCombos(removedIndex = null) {
                        const container = $('#subgroups-container');
                        let siglaText = getSigla();
                        
                        let firstInput = container.find('.subgroup-block').first().find('.parent-wrapper input[type=\"text\"]');
                        if (firstInput.length) {
                            firstInput.val(siglaText);
                        }
                        
                        let availableParents = [];
                        container.find('.subgroup-block').each(function() {
                            let nameInput = $(this).find('input.sg-name-input');
                            if (!nameInput.length) return;
                            
                            let prevName = nameInput.val().trim();
                            let match = nameInput.attr('name').match(/\d+/);
                            if (match && prevName !== '') {
                                availableParents.push({ index: match[0], name: prevName });
                            }
                        });
                        
                        container.find('.subgroup-block').each(function() {
                            let select = $(this).find('select.sg-parent-select');
                            if (select.length === 0) return;
                            
                            let currentValue = select.val();
                            if (removedIndex !== null && currentValue === removedIndex) {
                                currentValue = '-1';
                            }
                            
                            let match = $(this).find('input.sg-name-input').attr('name').match(/\d+/);
                            if (!match) return;
                            let currentBlockIndex = match[0];
                            
                            select.empty();
                            let defaultOption = $('<option>').val('-1').text(siglaText);
                            select.append(defaultOption);
                            
                            let hasCurrentValue = false;
                            if (currentValue === '-1') hasCurrentValue = true;
                            
                            for (let i = 0; i < availableParents.length; i++) {
                                let parentInfo = availableParents[i];
                                if (parentInfo.index === currentBlockIndex) break;
                                
                                let opt = $('<option>').val(parentInfo.index).text(parentInfo.name);
                                select.append(opt);
                                if (currentValue === parentInfo.index) hasCurrentValue = true;
                            }
                            
                            if (hasCurrentValue) {
                                select.val(currentValue);
                            } else {
                                select.val('-1');
                            }
                        });
                    }

                    $('#subgroups-container').on('click', '.btn-remove-subgroup', function() {
                        let block = $(this).closest('.subgroup-block');
                        let match = block.find('input.sg-name-input').attr('name').match(/\d+/);
                        let removedIndex = match ? match[0] : null;
                        block.remove();
                        updateAllParentCombos(removedIndex);
                    });

                    $('#subgroups-container').on('input', 'input.sg-name-input', function() {
                        updateAllParentCombos();
                    });

                    $('#btn-add-subgroup').on('click', function() {
                        const container = $('#subgroups-container');
                        
                        // Validação: verifica se os blocos atuais estão preenchidos
                        let allFilled = true;
                        let isSiglaEmpty = getSigla() === '';
                        
                        container.find('.subgroup-block').each(function() {
                            const nameVal = $(this).find('input.sg-name-input').val().trim();
                            const techsVal = $(this).find('textarea').val().trim();
                            const isFirst = $(this).index() === 0;
                            
                            if (isFirst) {
                                if (isSiglaEmpty || techsVal === '') {
                                    allFilled = false;
                                }
                            } else {
                                const parentVal = $(this).find('select.sg-parent-select, input[name$=\"[parent]\"]').val();
                                if (nameVal === '' || parentVal === null || parentVal === '' || isSiglaEmpty || techsVal === '') {
                                    allFilled = false;
                                }
                            }
                        });
                        
                        if (!allFilled) {
                            alert('Por favor, preencha o Nome do Subgrupo, Grupo Pai e os E-mails dos técnicos em todos os blocos atuais antes de adicionar um novo.');
                            return;
                        }

                        const firstBlock = container.find('.subgroup-block').first();
                        const newBlock = firstBlock.clone();
                        
                        newBlock.find('input.sg-name-input').val('');
                        newBlock.find('textarea').val('');
                        
                        newBlock.find('input.sg-name-input').attr('name', 'subgroups[' + index + '][name]');
                        newBlock.find('textarea').attr('name', 'subgroups[' + index + '][techs]');
                        newBlock.find('.sg-index').text(index + 1);
                        
                        let parentWrapper = newBlock.find('.parent-wrapper');
                        parentWrapper.empty();
                        let selectHtml = '<select name=\"subgroups[' + index + '][parent]\" class=\"form-select sg-parent-select\" style=\"width: 100%;\"></select>';
                        parentWrapper.append(selectHtml);
                        
                        const btnRemove = newBlock.find('.btn-remove-subgroup');
                        btnRemove.show();
                        // Evento de remoção agora é delegado globalmente
                        
                        container.append(newBlock);
                        index++;
                        updateAllParentCombos();
                    });

                    // ── Lógica para Perfis Padrão ──
                    $('input[name=\'sector_abbr\']').on('input', function() {
                        let abbr = $(this).val().trim();
                        if (abbr === '') {
                            $('#profile_admin').val('');
                            $('#profile_support').val('');
                            $('#profile_transfer').val('');
                        } else {
                            $('#profile_admin').val(abbr + ' - Admin');
                            $('#profile_support').val(abbr + ' - Atendimento');
                            $('#profile_transfer').val(abbr + ' - Transferência de Chamados');
                        }
                        updateAllParentCombos();
                    });
                    // Inicializa ao carregar (se houver valor padrão)
                    $('input[name=\'sector_abbr\']').trigger('input');

                    // Inicializa o select2 explicitamente com 100% de largura
                    $('.profile-select2').select2({ width: '100%' });

                    // ── Lógica para Adicionar Perfis Customizados ──
                    $('#btn-add-profile').on('click', function() {
                        const container = $('#profiles-container');
                        const template = container.find('.profile-block.template');
                        
                        // Valida os visíveis atuais
                        let allFilled = true;
                        let emailsValid = true;
                        container.find('.profile-block:visible').each(function() {
                            const nameVal = $(this).find('.profile-input').val().trim();
                            const copyVal = $(this).find('select').val();
                            const usersVal = $(this).find('.profile-users-input').val().trim();
                            if (nameVal === '' || copyVal === '' || copyVal === null || copyVal === '0' || usersVal === '') {
                                allFilled = false;
                            } else if (!validateEmailsStr(usersVal)) {
                                emailsValid = false;
                            }
                        });
                        
                        if (!allFilled || !emailsValid) {
                            alert('Por favor, preencha o nome do perfil, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) antes de adicionar um novo.');
                            return;
                        }
                        
                        const newBlock = template.clone();
                        newBlock.removeClass('template');
                        newBlock.css('display', 'block');
                        newBlock.find('.profile-input').attr('name', 'name_profile_custom[]');
                        
                        // Limpa o textarea de usuários
                        newBlock.find('.profile-users-input').val('');
                        
                        // Remove o lixo do select2 clonado
                        newBlock.find('.select2-container').remove();
                        
                        // Restaura o select original para inicializar o select2 novamente
                        let selectEl = newBlock.find('select');
                        selectEl.removeClass('select2-hidden-accessible')
                                .removeAttr('data-select2-id')
                                .removeAttr('tabindex')
                                .removeAttr('aria-hidden')
                                .show();
                        
                        // Gera um ID novo e limpa o valor selecionado
                        let newId = 'dropdown_copy_profile_' + Date.now();
                        selectEl.attr('id', newId).val('0');
                        selectEl.find('option').removeAttr('data-select2-id');

                        newBlock.find('.btn-remove-profile').on('click', function() {
                            newBlock.remove();
                        });
                        
                        container.append(newBlock);

                        // Inicializa select2 no novo dropdown com largura total
                        $('#' + newId).select2({ width: '100%' });
                    });

                    // Validação no Submit do Formulário
                    $('#form_wizard').on('submit', function(e) {
                        let standardFilled = true;
                        let standardEmailsValid = true;
                        $('#perfis-padrao-section select').each(function() {
                            if ($(this).val() === '0' || $(this).val() === null) {
                                standardFilled = false;
                            }
                        });
                        $('#perfis-padrao-section textarea').each(function() {
                            let usersVal = $(this).val().trim();
                            if (usersVal === '') {
                                standardFilled = false;
                            } else if (!validateEmailsStr(usersVal)) {
                                standardEmailsValid = false;
                            }
                        });
                        
                        if (!standardFilled || !standardEmailsValid) {
                            e.preventDefault();
                            alert('Por favor, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) para todos os Perfis Padrão.');
                            return false;
                        }

                        let customFilled = true;
                        let customEmailsValid = true;
                        $('#profiles-container .profile-block:visible').each(function() {
                            const nameVal = $(this).find('.profile-input').val().trim();
                            const copyVal = $(this).find('select').val();
                            const usersVal = $(this).find('.profile-users-input').val().trim();
                            
                            if (nameVal === '' || copyVal === '' || copyVal === null || copyVal === '0' || usersVal === '') {
                                customFilled = false;
                            } else if (!validateEmailsStr(usersVal)) {
                                customEmailsValid = false;
                            }
                        });
                        
                        if (!customFilled || !customEmailsValid) {
                            e.preventDefault();
                            alert('Por favor, preencha o nome do perfil, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) para todos os Perfis Adicionais.');
                            return false;
                        }
                        
                        // Se chegou até aqui, todas as validações passaram.
                        // Troca o texto do botão para Salvando...
                        $('#btn-submit-wizard').html('<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 5px;\"></i> Salvando...').css('pointer-events', 'none').css('opacity', '0.7');
                    });

                });
            </script>";

        echo "</div>";

        return true;
    }

}
