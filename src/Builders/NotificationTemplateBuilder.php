<?php

namespace GlpiPlugin\Glpinewentity\Builders;

class NotificationTemplateBuilder
{
    public static function read(int $id): array
    {
        $template = new \NotificationTemplate();
        if (!$template->getFromDB($id)) {
            return [];
        }
        global $DB;
        $data = array_intersect_key($template->fields, array_flip(['name', 'itemtype']));
        $data['translations'] = [];
        foreach ($DB->request(['FROM' => 'glpi_notificationtemplatetranslations', 'WHERE' => ['notificationtemplates_id' => $id], 'ORDER' => 'id ASC']) as $row) {
            $data['translations'][$row['language']] = array_intersect_key($row, array_flip(['subject', 'content_html', 'content_text']));
        }
        $language = array_key_exists('', $data['translations']) ? '' : (string)(array_key_first($data['translations']) ?? '');
        $data['language'] = $language;
        $data += $data['translations'][$language] ?? ['subject' => '', 'content_html' => '', 'content_text' => ''];
        return $data;
    }

    public function build(int $entities_id, array $configs = []): array
    {
        $count = 0;
        foreach ($configs as &$config) {
            $name = trim($config['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $template = new \NotificationTemplate();
            $id = (int)($config['generated_id'] ?? 0);
            $source = new \NotificationTemplate();
            $sourceData = [];
            if ($source->getFromDB((int)($config['copy_from'] ?? 0))) {
                $sourceData = array_intersect_key($source->fields, array_flip(['css', 'comment']));
            }
            $input = $sourceData + ['name' => $name, 'itemtype' => $config['itemtype'] ?? 'Ticket'];
            if ($id > 0) {
                if (!$template->getFromDB($id) || !$template->update(['id' => $id] + $input)) {
                    throw new \RuntimeException('Unable to update notification template.');
                }
            } else {
                $id = (int)$template->add($input);
                if (!$id) {
                    throw new \RuntimeException('Unable to create notification template.');
                }
                $config['generated_id'] = $id;
            }
            $translations = $config['translations'] ?? [];
            $language = (string)($config['language'] ?? '');
            $translations[$language] = array_intersect_key($config, array_flip(['subject', 'content_html', 'content_text']));
            foreach ($translations as $lang => $values) {
                $translation = new \NotificationTemplateTranslation();
                $data = [
                    'notificationtemplates_id' => $id,
                    'language' => (string)$lang,
                    'subject' => (string)($values['subject'] ?? ''),
                    'content_html' => (string)($values['content_html'] ?? ''),
                    'content_text' => (string)($values['content_text'] ?? ''),
                ];
                if ($translation->getFromDBByCrit(['notificationtemplates_id' => $id, 'language' => (string)$lang])) {
                    $ok = $translation->update(['id' => $translation->getID()] + $data);
                } else {
                    $ok = $translation->add($data);
                }
                if (!$ok) {
                    throw new \RuntimeException('Unable to save notification template translation.');
                }
            }
            $config['translations'] = $translations;
            $count++;
        }
        unset($config);
        return ['count' => $count, 'configs' => $configs];
    }
}
