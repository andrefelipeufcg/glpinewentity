<?php

namespace GlpiPlugin\Glpinewentity\Builders;

use Glpi\Form\Destination\CommonITILField\ContentField;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
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
     * @return int Number of forms created.
     */
    public function build(int $entities_id, array $configs = []): int
    {
        $count = 0;
        foreach ($configs as $config) {
            $formId = $this->getOrCreateForm($config, $entities_id);
            if ($formId > 0) {
                $count++;
            }
        }
        return $count;
    }

    private function getOrCreateForm(array $config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $sourceId = (int)($config['copy_from'] ?? 0);

        if (empty($name)) {
            return 0;
        }

        $form = new Form();
        if ($form->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            return (int) $form->getID();
        }

        $sourceData = [];
        if ($sourceId > 0 && $form->getFromDB($sourceId)) {
            $sourceData = $form->fields;
        }

        $description = trim($config['description'] ?? '');
        $forms_categories_id = (int)($config['forms_categories_id'] ?? 0);

        $insertData = [
            'name' => $name,
            'entities_id' => $entities_id,
            'is_recursive' => 1,
            'is_active' => 1, // forced to active as per user request
            'description' => $description ?: ($sourceData['description'] ?? __('Formulário padrão gerado automaticamente para a entidade.', 'glpinewentity')),
            'forms_categories_id' => $forms_categories_id ?: ($sourceData['forms_categories_id'] ?? 0),
        ];

        // Copiar outros campos se existirem
        $fieldsToCopy = ['icon', 'color', 'content', 'help'];
        foreach ($fieldsToCopy as $field) {
            if (isset($sourceData[$field])) {
                $insertData[$field] = $sourceData[$field];
            }
        }

        $formId = $form->add($insertData);

        if (!$formId) {
            return 0;
        }
        $form->getFromDB($formId);

        // Se NÃO copiou de um modelo existente, cria as perguntas padrão
        if ($sourceId == 0) {
            $questions = $this->addQuestions($form);
            if ($questions !== null) {
                $this->configureDestination($form, $questions);
            }
        } else {
            // Nota: Clonar as seções, perguntas e destinos de um Formulário já existente 
            // exige uma lógica profunda nas tabelas relacionadas (glpi_forms_sections, glpi_forms_questions, etc).
            // Para GLPI 11 Form nativo, isso exigiria percorrer todas as tabelas. 
            // Como é um MVP para a aba de Form, copiamos a "casca" do formulário.
        }

        return $formId;
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
