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

use GlpiPlugin\Glpinewentity\Sector;
use GlpiPlugin\Glpinewentity\Wizard;

// Permissão genérica de criação de entidade
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
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
        }
        $isEdit = true;
    } else {
        global $CFG_GLPI;
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
    }
}

if (isset($_POST['process_wizard'])) {
    Session::checkValidSessionId();
    if ($isEdit) {
        $result = Wizard::processUpdate($_POST, $sectorObj->fields);
        // Atualiza metadata independentemente de ter erro, pois os dados no banco já foram alterados
        $sectorObj->update([
            'id' => $sectorId,
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
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php?id=' . $sectorId);
    } else {
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
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
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
                Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php?id=' . $newSectorId);
            } else {
                // Erro fatal logo no inicio. Volta para adicionar
                foreach ($result['errors'] as $err) {
                    Session::addMessageAfterRedirect($err, false, ERROR);
                }
                global $CFG_GLPI;
                Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php');
            }
        }
    }
}

// -----------------------------------------------------------------------
// Carrega dados para edição
// -----------------------------------------------------------------------

Html::header(Sector::getTypeName(Session::getPluralNumber()), '', 'config', strtolower(\GlpiPlugin\Glpinewentity\Menu::class), 'sector');

$options = ['id' => $sectorId];
if ($sectorId > 0 && isset($sectorObj->fields['metadata'])) {
    $meta = json_decode($sectorObj->fields['metadata'], true);
    if (!empty($meta['entity_id'])) {
        $options['entities_id'] = $meta['entity_id'];
    }
}

$sectorObj->display($options);

Html::footer();
