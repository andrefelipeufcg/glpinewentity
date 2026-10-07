<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/render_solution_type.php
 * Renderiza o dropdown do Tipo de Solução usando a função nativa.
 * Usado pelo JS ao clicar em "Adicionar Item" na aba 3.
 * -----------------------------------------------------------------------
 */

define('GLPI_KEEP_CSRF_TOKEN', true);

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }

if (!file_exists($inc) && isset($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $parts = explode('/', trim($scriptDir, '/'));
    $pluginPos = array_search('plugins', $parts);
    if ($pluginPos !== false && $pluginPos > 0) {
        $sub = implode('/', array_slice($parts, 0, $pluginPos));
        $check = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $sub . '/inc/includes.php';
        if (file_exists($check)) {
            $inc = $check;
        }
    }
}

if (!file_exists($inc) && isset($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $check = rtrim($_SERVER['CONTEXT_DOCUMENT_ROOT'], '/') . '/inc/includes.php';
    if (file_exists($check)) {
        $inc = $check;
    }
}

include $inc;

header('Content-Type: text/html; charset=utf-8');

use GlpiPlugin\Glpinewentity\Sector;

if (!Session::haveRight('plugin_glpinewentity', READ) || !Sector::canView()) {
    http_response_code(403);
    exit('Acesso negado. Apenas o perfil Super-Admin pode realizar esta operação.');
}

$type_id = (int)($_POST['type_id'] ?? 0);

if (class_exists('\SolutionType')) {
    \SolutionType::dropdown([
        'name'    => 'items_solutiontypes_id[]',
        'value'   => $type_id,
        'display' => true,
        'width'   => '100%',
        'rand'    => mt_rand()
    ]);
}
