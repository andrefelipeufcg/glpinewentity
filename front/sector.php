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

use GlpiPlugin\Glpinewentity\Sector;

// Verifica direito de acesso
Session::checkRight('entity', READ);

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
Html::header(Sector::getTypeName(Session::getPluralNumber()), '', 'config', 'GlpiPlugin\Glpinewentity\Menu', 'sector');

Search::show(Sector::class);

Html::footer();
