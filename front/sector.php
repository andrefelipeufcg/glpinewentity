<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/sector.php
 * Tela de listagem dos setores criados.
 * -----------------------------------------------------------------------
 */

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }
include $inc;

use GlpiPlugin\Glpinewentity\Menu;
use GlpiPlugin\Glpinewentity\Sector;

// Verifica direito de acesso
Session::checkRight('plugin_glpinewentity', READ);
if (!Sector::canView()) {
    Session::addMessageAfterRedirect(__('Acesso negado: Este recurso é restrito exclusivamente para o perfil Super-Admin.', 'glpinewentity'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] ?? '/');
}

$sector = new Sector();

// Se o usuário clicar em "+" (Adicionar) ou tentar exibir um item, o GLPI
// chamará o showForm. Como queremos nosso próprio fluxo de wizard para adicionar
// e editar, capturamos antes de exibir.
if (isset($_POST["add"])) {
    $sector->check(-1, CREATE, $_POST);
    $newID = $sector->add($_POST);
    Html::redirect($sector->getFormURL()."?id=".$newID);
} else if (isset($_POST["update"])) {
    $sector->check($_POST["id"], UPDATE, $_POST);
    $sector->update($_POST);
    Html::back();
} else if (isset($_GET["id"])) {
    // Redireciona para o formulário (wizard customizado)
    // O wizard atual será transformado em front/sector.form.php
    Html::redirect('sector.form.php?id=' . $_GET['id']);
}

// -----------------------------------------------------------------------
// Renderiza o grid de listagem padrão do GLPI
// -----------------------------------------------------------------------

// Reconstrói o menu para disponibilizar as ações nativas após atualizações do plugin.
unset($_SESSION['glpimenu']);
Html::generateMenuSession(true);

// Usa a entrada de menu do plugin para o GLPI construir breadcrumb e ações nativas.
Html::header(
    Sector::getTypeName(Session::getPluralNumber()),
    '',
    'config',
    strtolower(Menu::class)
);

Search::show(Sector::class);

// A busca respeita a entidade ativa: fora da raiz, a lista vazia pode só estar filtrada.
if ((int) ($_SESSION['glpiactive_entity'] ?? 0) !== 0) {
    $hint = json_encode(
        __('Verifique se está na entidade raiz para visualizar todas as entidades.', 'glpinewentity'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    echo Html::scriptBlock("
        $(function () {
            $('.alert.alert-info .alert-title, .alert.alert-info .alert-description').first()
                .after($('<div>', { class: 'text-muted', text: {$hint} }));
        });
    ");
}

Html::footer();
