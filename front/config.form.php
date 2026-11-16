<?php

use GlpiPlugin\Glpinewentity\TransferBridge;

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }
include $inc;
require_once __DIR__ . '/../src/TransferBridge.php';

Session::checkRight('config', UPDATE);

if (isset($_POST['save_config'])) {
    $enabled = ($_POST['transfer_enabled'] ?? '0') === '1';
    $name = is_string($_POST['transfer_plugin'] ?? null) ? $_POST['transfer_plugin'] : '';
    $error = '';
    if ($enabled && !TransferBridge::validPluginName($name)) {
        $error = __('Informe o nome exato da pasta do plugin, com até 50 letras ou números.', 'glpinewentity');
    } elseif ($enabled && !TransferBridge::pluginExists($name)) {
        $error = __('A pasta do plugin informada não existe em plugins ou marketplace.', 'glpinewentity');
    }
    if ($error !== '') {
        Session::addMessageAfterRedirect($error, false, ERROR);
    } else {
        Config::setConfigurationValues('glpinewentity', [
            'transfer_enabled' => $enabled ? '1' : '0',
            'transfer_plugin' => $name,
        ]);
        Session::addMessageAfterRedirect(__('Configurações salvas com sucesso.', 'glpinewentity'), true, INFO);
        if ($enabled && !TransferBridge::active()) {
            Session::addMessageAfterRedirect(__('O plugin informado não está ativado ou não oferece a interface de integração compatível. A integração permanecerá inativa.', 'glpinewentity'), false, WARNING);
        }
    }
    Html::redirect($_SERVER['REQUEST_URI']);
    exit;
}

$enabled = (string) Config::getConfigurationValue('glpinewentity', 'transfer_enabled') === '1';
$name = htmlspecialchars(TransferBridge::pluginName(), ENT_QUOTES, 'UTF-8');
Html::header(__('Configurações do GLPI New Entity', 'glpinewentity'), $_SERVER['REQUEST_URI'], 'config', 'plugins');
echo '<div class="card col-12 col-lg-8 mx-auto my-4"><div class="card-body">';
echo '<div class="alert alert-info"><i class="ti ti-info-circle" aria-hidden="true"></i> ' . __('Para acessar as funcionalidades de criação de entidade pelo plugin, acesse Configurar > GLPI New Entity.', 'glpinewentity') . '</div>';
echo '<form method="post" action="' . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="_glpi_csrf_token" value="' . htmlspecialchars(Session::getNewCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
echo '<label class="form-check form-switch mb-3"><input type="hidden" name="transfer_enabled" value="0"><input class="form-check-input" type="checkbox" id="transfer_enabled" name="transfer_enabled" value="1"' . ($enabled ? ' checked' : '') . '> <span class="form-check-label">' . __('Ativar integração com plugin de transferência de chamados entre setores (entidades)', 'glpinewentity') . '</span></label>';
echo '<div id="transfer_plugin_field" class="mb-3"' . ($enabled ? '' : ' style="display:none"') . '><label class="form-label" for="transfer_plugin">' . __('Digite o nome exato da pasta do plugin de transferência de chamados entre setores/entidades (instalado dentro da pasta plugins ou marketplace)', 'glpinewentity') . '</label><input class="form-control" type="text" id="transfer_plugin" name="transfer_plugin" maxlength="50" pattern="[a-zA-Z0-9]+" value="' . $name . '"' . ($enabled ? ' required' : '') . '></div>';
echo '<div class="text-center"><button class="btn btn-primary" type="submit" name="save_config" value="1">' . __('Salvar', 'glpinewentity') . '</button></div></form></div></div>';
echo Html::scriptBlock("$(function() { function toggleTransfer() { var active = $('#transfer_enabled').is(':checked'); $('#transfer_plugin_field').toggle(active); $('#transfer_plugin').prop('required', active); } $('#transfer_enabled').on('change', toggleTransfer); toggleTransfer(); });");
Html::footer();
