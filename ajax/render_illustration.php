<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/render_illustration.php
 * Renderiza o seletor de ilustração nativo com IDs próprios.
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

use GlpiPlugin\Glpinewentity\Sector;

if (!Session::haveRight('plugin_glpinewentity', READ) || !Sector::canView()) {
    http_response_code(403);
    exit('Acesso negado. Apenas o perfil Super-Admin pode realizar esta operação.');
}

$twig = \Glpi\Application\View\TemplateRenderer::getInstance()->getEnvironment();
$illustrationTemplate = $twig->createTemplate(<<<'TWIG'
{% import 'components/form/fields_macros.html.twig' as fields %}
{{ fields.illustrationField('items_illustration[]', illustration_value, 'Ilustração', {'is_horizontal': false, 'full_width': true}) }}
TWIG);

echo $illustrationTemplate->render(['illustration_value' => Sector::sanitizeIllustration($_POST['illustration'] ?? '')]);
