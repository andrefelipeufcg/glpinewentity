<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/sector.form.php
 * Formulário para criação e edição de estrutura de novo setor.
 * -----------------------------------------------------------------------
 */

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }
include $inc;

use GlpiPlugin\Glpinewentity\Menu;
use GlpiPlugin\Glpinewentity\Sector;
use GlpiPlugin\Glpinewentity\Wizard;

// READ permite acessar o wizard; CREATE é validado pelo GLPI na inclusão.
// O plugin requer permissão híbrida: direito de READ + perfil obrigatoriamente de Super-Admin.
Session::checkRight("plugin_glpinewentity", READ);
if (!Sector::canView()) {
    Session::addMessageAfterRedirect(__('Acesso negado: Este recurso é restrito exclusivamente para o perfil Super-Admin.', 'glpinewentity'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] ?? '/');
}

// -----------------------------------------------------------------------
// POST: Processar criação
// -----------------------------------------------------------------------
$showResult = false;
$result     = [];
$isEdit     = false;
$sectorId   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sectorObj  = new Sector();

if ($sectorId > 0) {
    if ($sectorObj->getFromDB($sectorId)) {
        if (!Sector::canView() && !Session::haveAccessToEntity($sectorObj->fields['entities_id'])) {
            Session::addMessageAfterRedirect(__('Acesso negado à entidade.', 'glpinewentity'), false, ERROR);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.php');
        }

        $meta = json_decode($sectorObj->fields['metadata'] ?? '{}', true);
        $managedEntity = (int)($meta['entity_id'] ?? 0);
        if ($managedEntity <= 0 || (!Sector::canView() && !Session::haveAccessToEntity($managedEntity))) {
            Session::addMessageAfterRedirect(__('Acesso negado à entidade gerenciada por este setor.', 'glpinewentity'), false, ERROR);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.php');
        }

        $isEdit = true;
    } else {
        global $CFG_GLPI;
        Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.php');
    }
}

if (isset($_POST['process_wizard'])) {
    Session::checkValidSessionId();
    Session::setActiveTab(Sector::class, Sector::class . '$main');
    // Keep only wizard fields, never the submitted CSRF token or action flags.
    $draftKey = 'plugin_glpinewentity_wizard_drafts';
    $_SESSION[$draftKey][$sectorId] = array_intersect_key($_POST, array_flip([
        'parent_entity', 'sector_name', 'sector_abbr', 'profiles_default',
        'copy_profile_admin', 'copy_profile_support', 'users_profile_admin',
        'users_profile_support', 'copy_profile_custom', 'name_profile_custom',
        'users_profile_custom', 'default_profile', 'removed_profile_ids',
        'subgroups', 'category_names',
    ]));
    $sectorName = trim($_POST['sector_name'] ?? '');
    $sectorAbbr = trim($_POST['sector_abbr'] ?? '');
    if (empty($sectorName) || empty($sectorAbbr)) {
        Session::addMessageAfterRedirect(__('O nome do setor e a sigla são obrigatórios.', 'glpinewentity'), false, ERROR);
        $formUrl = $CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php';
        Html::redirect($formUrl . ($isEdit ? '?id=' . $sectorId : ''));
    }
    if ($isEdit) {
        Session::checkRight("plugin_glpinewentity", UPDATE);

        $newParent = (int)($_POST['parent_entity'] ?? 0);
        if (!Sector::canUpdate() && !Session::haveAccessToEntity($newParent)) {
            Session::addMessageAfterRedirect(__('Acesso negado à nova entidade pai escolhida.', 'glpinewentity'), false, ERROR);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php?id=' . $sectorId);
        }

        $result = Wizard::processUpdate($_POST, $sectorObj->fields);

        if (!empty($result['_invalid_default_profile']) || !empty($result['_invalid_removed_profiles'])) {
            foreach ($result['errors'] as $err) {
                Session::addMessageAfterRedirect($err, false, ERROR);
            }
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php?id=' . $sectorId);
        }

        // Mantém o formulário e o metadata alinhados aos dados já processados.
        $sectorSaved = $sectorObj->update([
            'id' => $sectorId,
            'entities_id' => (int) $_POST['parent_entity'],
            'sector_name' => $sectorName,
            'sector_abbr' => $sectorAbbr,
            'metadata' => json_encode($result)
        ]);

        foreach ($result['warnings'] ?? [] as $warn) {
            Session::addMessageAfterRedirect($warn, false, WARNING);
        }

        if (!$sectorSaved) {
            $result['errors'][] = __('A estrutura foi processada, mas não foi possível salvar o registro do setor.', 'glpinewentity');
        }

        if (empty($result['errors'])) {
            unset($_SESSION[$draftKey][$sectorId]);
            Session::addMessageAfterRedirect(__('Estrutura atualizada com sucesso!', 'glpinewentity'), true, INFO);
        } else {
            foreach ($result['errors'] as $err) {
                Session::addMessageAfterRedirect($err, false, ERROR);
            }
        }
        global $CFG_GLPI;
        Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php?id=' . $sectorId);
    } else {
        Session::checkRight("plugin_glpinewentity", CREATE);
        $result = Wizard::processCreation($_POST);
        
        if (empty($result['errors']) && !empty($result['entity_id'])) {
            $newSectorId = $sectorObj->add([
                'entities_id' => (int)$_POST['parent_entity'],
                'sector_name' => $sectorName,
                'sector_abbr' => $sectorAbbr,
                'metadata' => json_encode($result)
            ]);
            if (!$newSectorId) {
                Session::addMessageAfterRedirect(__('A entidade foi criada, mas não foi possível salvar o registro do setor. Verifique a estrutura no GLPI antes de tentar novamente.', 'glpinewentity'), false, ERROR);
                Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php');
            }
            unset($_SESSION[$draftKey][0]);
            Session::addMessageAfterRedirect(__('Estrutura criada com sucesso!', 'glpinewentity'), true, INFO);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php?id=' . $newSectorId);
        } else {
            // Se houve erro durante a criação, mas a entidade foi criada, salvamos o que deu e redirecionamos para EDIÇÃO
            if (!empty($result['entity_id'])) {
                $newSectorId = $sectorObj->add([
                    'entities_id' => (int)$_POST['parent_entity'],
                    'sector_name' => $sectorName,
                    'sector_abbr' => $sectorAbbr,
                    'metadata' => json_encode($result)
                ]);
                foreach ($result['errors'] as $err) {
                    Session::addMessageAfterRedirect($err, false, ERROR);
                }
                if (!$newSectorId) {
                    Session::addMessageAfterRedirect(__('A entidade foi criada, mas não foi possível salvar o registro do setor. Verifique a estrutura no GLPI antes de tentar novamente.', 'glpinewentity'), false, ERROR);
                    Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php');
                }
                $_SESSION[$draftKey][(int)$newSectorId] = $_SESSION[$draftKey][0];
                unset($_SESSION[$draftKey][0]);
                Session::addMessageAfterRedirect(__('Estrutura criada parcialmente. Verifique os erros.', 'glpinewentity'), false, WARNING);
                global $CFG_GLPI;
                Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php?id=' . $newSectorId);
            } else {
                // Erro fatal logo no inicio. Volta para adicionar
                foreach ($result['errors'] as $err) {
                    Session::addMessageAfterRedirect($err, false, ERROR);
                }
                global $CFG_GLPI;
                Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php');
            }
        }
    }
}

// -----------------------------------------------------------------------
// Carrega dados para edição
// -----------------------------------------------------------------------

// Reconstrói o menu para disponibilizar as ações nativas após atualizações do plugin.
unset($_SESSION['glpimenu']);
Html::generateMenuSession(true);

// Mantém o breadcrumb e as ações nativas do menu também no wizard.
Html::header(
    Sector::getTypeName(Session::getPluralNumber()),
    '',
    'config',
    strtolower(Menu::class)
);

$assetPath = version_compare(GLPI_VERSION, '11.0', '<') ? '/public/js/unsaved-changes.js' : '/js/unsaved-changes.js';
echo Html::script(\Plugin::getPhpDir('glpinewentity', false) . $assetPath, [], false);

$options = ['id' => $sectorId];
if ($sectorId > 0 && isset($sectorObj->fields['metadata'])) {
    $meta = json_decode($sectorObj->fields['metadata'], true);
    if (!empty($meta['entity_id'])) {
        $options['entities_id'] = $meta['entity_id'];
    }
}

$sectorObj->display($options);

Html::footer();
