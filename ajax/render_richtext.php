<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/render_richtext.php
 * Renderiza um campo de Rich Text (TinyMCE) via Html::textarea do GLPI.
 * Usado pelo JS ao clicar em "Adicionar Item" nas abas 1, 2 e 3.
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

if (!Session::haveRight('plugin_glpinewentity', READ)) {
    http_response_code(403);
    exit('Acesso negado');
}

$name  = $_POST['name']  ?? 'items_content[]';
$value = $_POST['value'] ?? '';

$rand = mt_rand();

ob_start();
$out = \Html::textarea([
    'name'            => $name,
    'value'           => $value,
    'enable_richtext' => true,
    'rand'            => $rand,
    'rows'            => 4
]);
$buffered = ob_get_clean();

echo !empty($buffered) ? $buffered : $out;

