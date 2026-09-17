<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/render_form_category.php
 * Renderiza o campo de categoria do Formulário usando o macro nativo.
 * Usado pelo JS ao clicar em "Adicionar Item" na aba 6.
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

$category_id = (int)($_POST['category_id'] ?? 0);

$twig = \Glpi\Application\View\TemplateRenderer::getInstance()->getEnvironment();
$categoryTemplate = $twig->createTemplate(<<<'TWIG'
{% import 'components/form/fields_macros.html.twig' as fields %}
{{ fields.dropdownField('Glpi\\Form\\Category', 'items_forms_categories_id[]', category_id, 'Configuração do catálogo de serviços - categoria', {'is_horizontal': false, 'full_width': true, 'add_field_class': 'glpinewentity-form-category'}) }}
TWIG);

echo $categoryTemplate->render(['category_id' => $category_id]);
