<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Builders/FormBuilder.php
 * Construtor responsável por clonar ou criar formulários padrão (GLPI 11).
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity\Builders;

use Glpi\Form\Destination\CommonITILField\ContentField;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Category;
use Glpi\Form\Export\Context\DatabaseMapper;
use Glpi\Form\Export\Serializer\FormSerializer;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;

class FormBuilder
{
    private const FORM_NAME = 'Formulários';

    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @param array $managedIds IDs de formulários previamente gerados pelo plugin
     * @param array $externalIds IDs de formulários externos adotados pela configuração
     * @return array Array contendo 'count' e 'configs' atualizado.
     */
    public function build(int $entities_id, array $configs = [], array $managedIds = [], array $externalIds = []): array
    {
        $count = 0;
        $processedIds = [];
        $processedManagedIds = [];
        $processedExternalIds = [];
        $managedIds = array_values(array_unique(array_filter(array_map('intval', $managedIds))));
        $externalIds = array_values(array_unique(array_filter(array_map('intval', $externalIds))));
        foreach ($configs as &$config) {
            $generatedId = (int)($config['generated_id'] ?? 0);
            $formId = $this->getOrCreateForm($config, $entities_id);
            if ($formId > 0) {
                $count++;
                $processedIds[] = $formId;
                if (in_array($generatedId, $managedIds, true)
                    || ($generatedId === 0 && !in_array($formId, $externalIds, true))) {
                    $processedManagedIds[] = $formId;
                } else {
                    $processedExternalIds[] = $formId;
                }
            }
        }
        unset($config);

        $remainingManagedIds = $processedManagedIds;
        $remainingExternalIds = $processedExternalIds;

        global $DB;
        $formObj = new Form();

        $answersSetClass = '\Glpi\Form\AnswersSet';
        $hasAnswersSetTable = class_exists($answersSetClass);
        $answersTable = $hasAnswersSetTable ? (new $answersSetClass())->getTable() : '';

        foreach ($managedIds as $id) {
            if (in_array($id, $processedIds, true)
                || !$formObj->getFromDB($id)
                || (int)$formObj->fields['entities_id'] !== $entities_id) {
                continue;
            }

            $hasAnswers = false;
            if ($hasAnswersSetTable && $DB->tableExists($answersTable)) {
                $answersCount = $DB->request([
                    'COUNT' => 'cpt',
                    'FROM'  => $answersTable,
                    'WHERE' => ['forms_forms_id' => $id],
                ]);
                if ((int)($answersCount->current()['cpt'] ?? 0) > 0) {
                    $hasAnswers = true;
                }
            }

            if ($hasAnswers) {
                $formObj->update(['id' => $id, 'is_active' => 0]);
                $remainingManagedIds[] = $id;
            } else {
                $formObj->delete(['id' => $id], 1);
            }
        }

        foreach ($externalIds as $id) {
            if (in_array($id, $processedIds, true)
                || !$formObj->getFromDB($id)
                || (int)$formObj->fields['entities_id'] !== $entities_id) {
                continue;
            }

            $formObj->update(['id' => $id, 'is_active' => 0]);
            $remainingExternalIds[] = $id;
        }

        return [
            'count' => $count,
            'configs' => $configs,
            'managed_ids' => array_values(array_unique($remainingManagedIds)),
            'external_ids' => array_values(array_unique($remainingExternalIds)),
        ];
    }

    private function getOrCreateForm(array &$config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $sourceId = (int)($config['copy_from'] ?? 0);
        $generatedId = (int)($config['generated_id'] ?? 0);

        if (empty($name)) {
            return 0;
        }

        $form = new Form();

        $description = trim($config['description'] ?? '');
        $forms_categories_id = (int)($config['forms_categories_id'] ?? 0);
        $illustration = $config['illustration'] ?? $config['icon'] ?? 'request-service';
        $configHash = $this->getConfigHash($config);

        $sourceData = [];
        $sourceForm = null;
        if ($sourceId > 0) {
            $sourceForm = new Form();
            if ($sourceForm->getFromDB($sourceId)) {
                $sourceData = $sourceForm->fields;
            } else {
                $sourceForm = null;
            }
        }

        if ($generatedId === 0 && $form->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            $generatedId = (int) $form->getID();
            $config['generated_id'] = $generatedId;
        }

        if ($generatedId > 0 && ($form->getID() === $generatedId || $form->getFromDB($generatedId))) {
            if (($config['applied_hash'] ?? '') === $configHash
                || $this->matchesConfiguration($form, $entities_id, $name, $description, $forms_categories_id, $illustration, $config['is_active'] ?? 1)) {
                $config['applied_hash'] = $configHash;
                return $generatedId;
            }

            $form->update([
                'id' => $generatedId,
                'name' => $name,
                'entities_id' => $entities_id,
                'description' => $description,
                'forms_categories_id' => $forms_categories_id,
                'illustration' => $illustration,
                'is_active' => $config['is_active'] ?? 1,
            ]);
            $config['applied_hash'] = $configHash;
            return $generatedId;
        }

        if ($sourceForm !== null) {
            $importedId = $this->importCompleteForm(
                $sourceForm,
                $entities_id,
                $name,
                $description,
                $forms_categories_id,
                $illustration,
                $config['is_active'] ?? 1
            );
            if ($importedId > 0) {
                $config['generated_id'] = $importedId;
                $config['applied_hash'] = $configHash;
            }
            return $importedId;
        }

        $insertData = [
            'name' => $name,
            'entities_id' => $entities_id,
            'is_recursive' => 1,
            'is_active' => $config['is_active'] ?? 1,
            'description' => $description ?: ($sourceData['description'] ?? __('Formulários gerados automaticamente para a entidade.', 'glpinewentity')),
            'forms_categories_id' => $forms_categories_id ?: ($sourceData['forms_categories_id'] ?? 0),
            'illustration' => $illustration,
        ];

        $fieldsToCopy = ['color', 'content', 'help'];
        foreach ($fieldsToCopy as $field) {
            if (isset($sourceData[$field])) {
                $insertData[$field] = $sourceData[$field];
            }
        }

        $formId = (int) $form->add($insertData);

        if (!$formId) {
            return 0;
        }

        $config['generated_id'] = $formId;
        $form->getFromDB($formId);

        $questions = $this->addQuestions($form);
        if ($questions !== null) {
            $this->configureDestination($form, $questions);
        }

        $config['applied_hash'] = $configHash;
        return $formId;
    }

    private function getConfigHash(array $config): string
    {
        $values = [
            'name'                => trim((string)($config['name'] ?? '')),
            'copy_from'           => (int)($config['copy_from'] ?? 0),
            'description'         => str_replace("\r\n", "\n", trim((string)($config['description'] ?? ''))),
            'forms_categories_id' => (int)($config['forms_categories_id'] ?? 0),
            'illustration'        => (string)($config['illustration'] ?? $config['icon'] ?? 'request-service'),
            'is_active'           => (int)($config['is_active'] ?? 1),
        ];

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function matchesConfiguration(
        Form $form,
        int $entitiesId,
        string $name,
        string $description,
        int $categoryId,
        string $illustration,
        int $isActive
    ): bool {
        return (int)($form->fields['entities_id'] ?? 0) === $entitiesId
            && (string)($form->fields['name'] ?? '') === $name
            && str_replace("\r\n", "\n", trim((string)($form->fields['description'] ?? ''))) === str_replace("\r\n", "\n", $description)
            && (int)($form->fields['forms_categories_id'] ?? 0) === $categoryId
            && (string)($form->fields['illustration'] ?? 'request-service') === $illustration
            && (int)($form->fields['is_active'] ?? 0) === $isActive;
    }

    private function importCompleteForm(
        Form $source,
        int $entitiesId,
        string $name,
        string $description,
        int $categoryId,
        string $illustration,
        int $isActive = 1
    ): int
    {
        $override_input = [
            'name'                => $name,
            'entities_id'         => $entitiesId,
            'description'         => $description,
            'forms_categories_id' => $categoryId,
            'illustration'        => $illustration,
            'is_active'           => $isActive,
            'is_recursive'        => 1
        ];

        $newId = $source->clone($override_input);
        
        // O método clone() nativo do GLPI para formulários força o formulário a nascer inativo (is_active = 0)
        // por segurança. Atualizamos para respeitar a configuração do plugin.
        if ($newId > 0) {
            $newForm = new Form();
            $newForm->update([
                'id'        => $newId,
                'is_active' => $isActive
            ]);
        }
        
        return (int)$newId;
    }

    private function addQuestions(Form $form): ?array
    {
        $section = new Section();
        if (!$section->getFromDBByCrit(['forms_forms_id' => $form->getID()])) {
            // Seção principal criada pelo core automaticamente ao criar o Form,
            // mas caso não exista, criamos:
            $sectionId = $section->add([
                'forms_forms_id' => $form->getID(),
                'name' => __('Sua Solicitação', 'glpinewentity')
            ]);
        } else {
            $sectionId = $section->getID();
        }

        if (!$sectionId) {
            return null;
        }

        $assunto = new Question();
        $assuntoId = $assunto->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Assunto / Título breve', 'glpinewentity'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);
        $assunto->getFromDB($assuntoId);

        $descricao = new Question();
        $descricaoId = $descricao->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Descreva os detalhes da sua solicitação', 'glpinewentity'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
        ]);
        $descricao->getFromDB($descricaoId);

        if (!$assuntoId || !$descricaoId) {
            return null;
        }

        return ['assunto' => $assunto, 'descricao' => $descricao];
    }

    private function configureDestination(Form $form, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $assuntoTag = $answerProvider->getTagForQuestion($questions['assunto'])->html;

        $config = [
            TitleField::getKey() => (new SimpleValueConfig($assuntoTag))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
