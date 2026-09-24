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
    private const FORM_NAME = 'Formulário Padrão de Atendimento';

    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return array Array contendo 'count' e 'configs' atualizado.
     */
    public function build(int $entities_id, array $configs = []): array
    {
        $count = 0;
        $processedIds = [];
        foreach ($configs as &$config) {
            $formId = $this->getOrCreateForm($config, $entities_id);
            if ($formId > 0) {
                $count++;
                $processedIds[] = $formId;
            }
        }
        unset($config);

        // Deleta (purge) os formulários da entidade que foram removidos da configuração do plugin
        global $DB;
        $formObj = new Form();
        $table = $formObj->getTable();
        
        if ($DB->tableExists($table)) {
            $iterator = $DB->request([
                'SELECT' => 'id',
                'FROM'   => $table,
                'WHERE'  => ['entities_id' => $entities_id]
            ]);
            
            foreach ($iterator as $row) {
                $id = (int)$row['id'];
                if (!in_array($id, $processedIds)) {
                    $formObj->delete(['id' => $id], 1);
                }
            }
        }

        return ['count' => $count, 'configs' => $configs];
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

        if ($generatedId > 0 && $form->getFromDB($generatedId)) {
            // Como o objetivo do plugin é sempre refletir o que foi copiado (e sobrescrever eventuais edições no GLPI),
            // se o usuário selecionou uma origem (Copiar de...), nós recriamos o formulário.
            if ($sourceForm !== null) {
                $importedId = $this->importCompleteForm(
                    $sourceForm,
                    $entities_id,
                    $name,
                    $description,
                    $forms_categories_id,
                    $illustration
                );
                if ($importedId > 0) {
                    $form->delete(['id' => $generatedId], true);
                    $config['generated_id'] = $importedId;
                    return $importedId;
                }
            }

            $form->update([
                'id' => $generatedId,
                'name' => $name,
                'entities_id' => $entities_id,
                'description' => $description,
                'forms_categories_id' => $forms_categories_id,
                'illustration' => $illustration,
            ]);
            return $generatedId;
        }

        if ($form->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            $config['generated_id'] = $form->getID();
            return (int) $form->getID();
        }

        $insertData = [
            'name' => $name,
            'entities_id' => $entities_id,
            'is_recursive' => 1,
            'is_active' => 1,
            'description' => $description ?: ($sourceData['description'] ?? __('Formulário padrão gerado automaticamente para a entidade.', 'glpinewentity')),
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

        if ($sourceForm !== null) {
            $form->delete(['id' => $formId], true);
            $importedId = $this->importCompleteForm(
                $sourceForm,
                $entities_id,
                $name,
                $description,
                $forms_categories_id,
                $illustration
            );
            if ($importedId > 0) {
                $config['generated_id'] = $importedId;
                return $importedId;
            }
            return 0;
        } else {
            $questions = $this->addQuestions($form);
            if ($questions !== null) {
                $this->configureDestination($form, $questions);
            }
        }

        return $formId;
    }

    private function importCompleteForm(
        Form $source,
        int $entitiesId,
        string $name,
        string $description,
        int $categoryId,
        string $illustration
    ): int
    {
        $override_input = [
            'name'                => $name,
            'entities_id'         => $entitiesId,
            'description'         => $description,
            'forms_categories_id' => $categoryId,
            'illustration'        => $illustration,
            'is_active'           => 1,
            'is_recursive'        => 1
        ];

        $newId = $source->clone($override_input);
        
        // O método clone() nativo do GLPI para formulários força o formulário a nascer inativo (is_active = 0)
        // por segurança. Mas na nossa automação de setores, queremos que ele já venha ativo.
        if ($newId > 0) {
            $newForm = new Form();
            $newForm->update([
                'id'        => $newId,
                'is_active' => 1
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
