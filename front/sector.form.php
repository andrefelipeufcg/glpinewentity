<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/sector.form.php
 * Formulário para criação e edição de infraestrutura de novo setor.
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
Session::checkRight("plugin_glpinewentity", READ);

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
        // Prevenção de IDOR: Checar acesso à entidade pai
        if (!Session::haveAccessToEntity($sectorObj->fields['entities_id'])) {
            Session::addMessageAfterRedirect(__('Acesso negado à entidade.', 'glpinewentity'), false, ERROR);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.php');
        }

        // Prevenção de Information Disclosure: Checar acesso à entidade gerenciada (filha)
        // Se o usuário não tem acesso à entidade gerada, ele não pode ver o formulário
        // porque o showForm() fará queries na entidade filha para popular a tela.
        $meta = json_decode($sectorObj->fields['metadata'] ?? '{}', true);
        $managedEntity = (int)($meta['entity_id'] ?? 0);
        if ($managedEntity > 0 && !Session::haveAccessToEntity($managedEntity)) {
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
    if ($isEdit) {
        Session::checkRight("plugin_glpinewentity", UPDATE);

        $newParent = (int)($_POST['parent_entity'] ?? 0);
        if (!Session::haveAccessToEntity($newParent)) {
            Session::addMessageAfterRedirect(__('Acesso negado à nova entidade pai escolhida.', 'glpinewentity'), false, ERROR);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.form.php?id=' . $sectorId);
        }

        $result = Wizard::processUpdate($_POST, $sectorObj->fields);

        // Mantém o formulário e o metadata alinhados aos dados já processados.
        $sectorObj->update([
            'id' => $sectorId,
            'entities_id' => (int) $_POST['parent_entity'],
            'sector_name' => $_POST['sector_name'],
            'sector_abbr' => $_POST['sector_abbr'],
            'metadata' => json_encode($result)
        ]);

        if (empty($result['errors'])) {
            Session::addMessageAfterRedirect(__('Infraestrutura atualizada com sucesso!', 'glpinewentity'), true, INFO);
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
            $sectorObj->add([
                'entities_id' => (int)$_POST['parent_entity'],
                'sector_name' => $_POST['sector_name'],
                'sector_abbr' => $_POST['sector_abbr'],
                'metadata' => json_encode($result)
            ]);
            Session::addMessageAfterRedirect(__('Infraestrutura criada com sucesso!', 'glpinewentity'), true, INFO);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . \Plugin::getPhpDir('glpinewentity', false) . '/front/sector.php');
        } else {
            // Se houve erro durante a criação, mas a entidade foi criada, salvamos o que deu e redirecionamos para EDIÇÃO
            if (!empty($result['entity_id'])) {
                $newSectorId = $sectorObj->add([
                    'entities_id' => (int)$_POST['parent_entity'],
                    'sector_name' => $_POST['sector_name'],
                    'sector_abbr' => $_POST['sector_abbr'],
                    'metadata' => json_encode($result)
                ]);
                foreach ($result['errors'] as $err) {
                    Session::addMessageAfterRedirect($err, false, ERROR);
                }
                Session::addMessageAfterRedirect(__('Infraestrutura criada parcialmente. Verifique os erros.', 'glpinewentity'), false, WARNING);
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

$options = ['id' => $sectorId];
if ($sectorId > 0 && isset($sectorObj->fields['metadata'])) {
    $meta = json_decode($sectorObj->fields['metadata'], true);
    if (!empty($meta['entity_id'])) {
        $options['entities_id'] = $meta['entity_id'];
    }
}

$sectorObj->display($options);

Html::footer();
