<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/sector.php
 * Tela de listagem dos setores criados.
 * -----------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

// Verifica direito de acesso
Session::checkRight('config', READ);

$sector = new PluginGlpinewentitySector();

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
Html::header(PluginGlpinewentitySector::getTypeName(Session::getPluralNumber()), '', 'config', 'pluginglpinewentitymenu', 'sector');

Search::show('PluginGlpinewentitySector');

Html::footer();
