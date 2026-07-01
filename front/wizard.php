<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/wizard.php
 * Formulário wizard para criação rápida de infraestrutura de novo setor.
 * -----------------------------------------------------------------------
 */

include("../../../inc/includes.php");

// Permissão: somente Super-Admin (ou quem possa gerenciar entidades)
Session::checkRight("entity", CREATE);

// -----------------------------------------------------------------------
// POST: Processar criação
// -----------------------------------------------------------------------
$showResult = false;
$result     = [];

if (isset($_POST['process_wizard'])) {
    // Validação CSRF do GLPI
    Session::checkCSRF($_POST);

    $result = PluginGlpinewentityWizard::processCreation($_POST);
    $showResult = true;

    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) {
            Session::addMessageAfterRedirect($err, false, ERROR);
        }
    }

    if ($result['entity_id'] > 0) {
        Session::addMessageAfterRedirect(
            'Infraestrutura do setor criada com sucesso!',
            true,
            INFO
        );
    }
}

// -----------------------------------------------------------------------
// RENDERIZAÇÃO DA PÁGINA
// -----------------------------------------------------------------------
Html::header('GLPI New Entity — Wizard', $_SERVER['PHP_SELF'], 'config', 'plugins');

global $CFG_GLPI;
$form_url = $CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/wizard.php';

echo "<div class='center' style='margin-top: 20px;'>";

// =====================================================================
// FORMULÁRIO WIZARD
// =====================================================================
if (!$showResult || ($showResult && $result['entity_id'] === 0)) {

    echo "<form method='post' action='" . $form_url . "' id='form_wizard'>";
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo "<input type='hidden' name='process_wizard' value='1'>";

    // ── Título Principal ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2' style='font-size: 1.2em;'>";
    echo "<i class='fas fa-magic' style='margin-right: 8px;'></i>";
    echo "Wizard — Nova Entidade para Central de Serviços";
    echo "</th></tr>";
    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 1: Dados da Entidade ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-building'></i> Dados da Entidade</th></tr>";

    // Entidade-Pai (dropdown nativo do GLPI)
    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>Entidade-Pai <span style='color:red;'>*</span></td>";
    echo "<td>";
    Entity::dropdown([
        'name'  => 'parent_entity',
        'value' => 0,
    ]);
    echo "<br><small class='text-muted'>Selecione sob qual entidade o novo setor será criado.</small>";
    echo "</td>";
    echo "</tr>";

    // Nome do Setor
    echo "<tr class='tab_bg_1'>";
    echo "<td>Nome do Setor <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<input type='text' name='sector_name' class='form-control' style='width: 100%;' placeholder='Ex: Departamento de Computação' required>";
    echo "</td>";
    echo "</tr>";

    // Sigla
    echo "<tr class='tab_bg_1'>";
    echo "<td>Sigla <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<input type='text' name='sector_abbr' class='form-control' style='width: 200px;' placeholder='Ex: DC' maxlength='20' required>";
    echo "<br><small class='text-muted'>A entidade será criada com o mesmo nome da sigla.</small>";
    echo "</td>";
    echo "</tr>";

    // Sub-entidades
    echo "<tr class='tab_bg_1'>";
    echo "<td>Sub-entidades</td>";
    echo "<td>";
    echo "<input type='text' name='sub_entities' class='form-control' style='width: 100%;' placeholder='Ex: Laboratório IA, Laboratório Redes, Núcleo de Pesquisa'>";
    echo "<br><small class='text-muted'>Opcional. Nomes separados por vírgula. Serão criadas como filhas da entidade principal.</small>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 2: Administrador ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-user-shield'></i> Administrador Local</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>E-mail do Administrador <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<input type='email' name='admin_email' class='form-control' style='width: 100%;' placeholder='Ex: joao.silva@instituicao.edu.br' required>";
    echo "<br><small class='text-muted'>O e-mail deve pertencer a um usuário <strong>já cadastrado</strong> no GLPI. Ele receberá o perfil <strong>Admin</strong> na nova entidade, com permissão recursiva.</small>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 3: Grupos e Técnicos Atendentes ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-users-cog'></i> Grupos e Técnicos Atendentes</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 0;'>";
    echo "<div id='subgroups-container'>";
    
    // Bloco inicial (index 0)
    echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa;'>";
    echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
    echo "      <strong>Subgrupo <span class='sg-index'>1</span></strong>";
    echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='display:none;'><i class='fas fa-trash'></i> Remover</button>";
    echo "  </div>";
    
    echo "  <div style='margin-bottom: 10px;'>";
    echo "      <label>Nome do Subgrupo</label>";
    echo "      <input type='text' name='subgroups[0][name]' class='form-control' style='width: 100%;' placeholder='Ex: Suporte Infra (Deixe em branco para alocar no Grupo Pai)'>";
    echo "  </div>";
    
    echo "  <div>";
    echo "      <label>E-mails dos Técnicos Atendentes</label>";
    echo "      <textarea name='subgroups[0][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='maria@instituicao.edu.br&#10;pedro@instituicao.edu.br'></textarea>";
    echo "      <small class='text-muted'>Devem estar cadastrados no GLPI. Se informar um subgrupo, os técnicos irão EXCLUSIVAMENTE para ele. Senão, irão para o Grupo Pai <strong>({SIGLA})</strong>.</small>";
    echo "  </div>";
    echo "</div>";
    
    echo "</div>"; // Fim subgroups-container
    
    echo "<div style='margin: 10px;'>";
    echo "  <button type='button' id='btn-add-subgroup' class='btn btn-sm btn-primary'><i class='fas fa-plus'></i> Adicionar outro Subgrupo</button>";
    echo "</div>";
    
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 4: Catálogo de Serviços ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-clipboard-list'></i> Catálogo de Serviços (Categorias ITIL)</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>Categorias de Serviço <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<textarea name='category_names' class='form-control' style='width: 100%; height: 120px;' placeholder='Uma categoria por linha.&#10;Ex:&#10;Manutenção de Hardware&#10;Instalação de Software&#10;Acesso à Rede' required></textarea>";
    echo "<br><small class='text-muted'>Cada categoria será vinculada exclusivamente à nova entidade, habilitada para Incidentes e Requisições.</small>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 5: Roteamento e E-mail (V2) ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-envelope'></i> Roteamento de E-mail</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>E-mail de Suporte</td>";
    echo "<td>";
    echo "<input type='email' name='support_email' class='form-control' style='width: 100%;' placeholder='Ex: suporte.dc@instituicao.edu.br' disabled>";
    echo "<br><small class='text-muted'><i class='fas fa-clock'></i> <strong>Em breve (V2):</strong> Cadastro automático de Coletor de E-mail e Regras de Roteamento (RuleTicket).</small>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Botão Submeter ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr class='tab_bg_2'>";
    echo "<td class='center' style='padding: 15px;'>";
    echo "<button type='submit' class='btn btn-primary' style='font-size: 1.05em; padding: 8px 30px;'>";
    echo "<i class='fas fa-magic' style='margin-right: 6px;'></i> Criar Infraestrutura do Setor";
    echo "</button>";
    echo "</td>";
    echo "</tr>";
    echo "</table>";

    Html::closeForm();

    echo "<script>
    $(function() {
        let index = 1;

        $('#btn-add-subgroup').on('click', function() {
            const container = $('#subgroups-container');
            
            // Validação: verifica se os blocos atuais estão preenchidos
            let allFilled = true;
            container.find('.subgroup-block').each(function() {
                const nameVal = $(this).find('input').val().trim();
                const techsVal = $(this).find('textarea').val().trim();
                
                if (nameVal === '' || techsVal === '') {
                    allFilled = false;
                }
            });
            
            if (!allFilled) {
                alert('Por favor, preencha o Nome do Subgrupo e os E-mails dos técnicos em todos os blocos atuais antes de adicionar um novo.');
                return;
            }

            const firstBlock = container.find('.subgroup-block').first();
            const newBlock = firstBlock.clone();
            
            newBlock.find('input').val('');
            newBlock.find('textarea').val('');
            
            newBlock.find('input').attr('name', 'subgroups[' + index + '][name]');
            newBlock.find('textarea').attr('name', 'subgroups[' + index + '][techs]');
            newBlock.find('.sg-index').text(index + 1);
            
            const btnRemove = newBlock.find('.btn-remove-subgroup');
            btnRemove.show();
            btnRemove.on('click', function() {
                newBlock.remove();
            });
            
            container.append(newBlock);
            index++;
        });
    });
    </script>";

// =====================================================================
// RESULTADO — Exibe o resumo da criação
// =====================================================================
} else {

    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2' style='font-size: 1.2em; color: #2e7d32;'>";
    echo "<i class='fas fa-check-circle' style='margin-right: 8px;'></i>";
    echo "Infraestrutura Criada com Sucesso!";
    echo "</th></tr>";
    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid #2e7d32; margin: 20px auto 10px auto;'>";

    // ── Resumo: Entidade ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-building'></i> Entidade</th></tr>";
    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>Entidade Principal</td>";
    echo "<td><strong>ID " . $result['entity_id'] . "</strong> — Criada com sucesso</td>";
    echo "</tr>";

    if (!empty($result['sub_entities'])) {
        echo "<tr class='tab_bg_1'>";
        echo "<td>Sub-entidades</td>";
        echo "<td>";
        foreach ($result['sub_entities'] as $sub) {
            echo "<span class='badge bg-secondary' style='margin-right: 5px;'>{$sub['name']} (ID {$sub['id']})</span> ";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // ── Resumo: Admin ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-user-shield'></i> Administrador</th></tr>";
    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>Usuário Admin</td>";
    echo "<td>";
    if ($result['admin_user_id'] > 0) {
        echo "<strong>" . htmlspecialchars($result['admin_login']) . "</strong> (ID {$result['admin_user_id']}) — Perfil Admin atribuído";
    } else {
        echo "<span style='color: red;'>Falha na criação</span>";
    }
    echo "</td>";
    echo "</tr>";
    echo "</table>";

    // ── Resumo: Grupos ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-users-cog'></i> Grupos</th></tr>";
    if (!empty($result['groups'])) {
        foreach ($result['groups'] as $g) {
            echo "<tr class='tab_bg_1'>";
            echo "<td style='width: 35%;'><strong>{$g['name']}</strong></td>";
            echo "<td>ID {$g['id']} — Criado com sucesso</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr class='tab_bg_1'><td colspan='2'>Nenhum grupo criado.</td></tr>";
    }
    echo "</table>";

    // ── Resumo: Técnicos Atendentes ──
    if (!empty($result['technicians'])) {
        echo "<table class='tab_cadre_fixe' style='width: 750px; margin-top: 20px;'>";
        echo "<tr><th colspan='2'><i class='fas fa-user-cog'></i> Técnicos Atendentes</th></tr>";
        foreach ($result['technicians'] as $t) {
            echo "<tr class='tab_bg_1'>";
            echo "<td style='width: 35%;'>" . htmlspecialchars($t['email']) . "</td>";
            echo "<td>ID {$t['id']} — Adicionado aos grupos</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // ── Resumo: Categorias ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-clipboard-list'></i> Categorias ITIL</th></tr>";
    if (!empty($result['categories'])) {
        foreach ($result['categories'] as $c) {
            echo "<tr class='tab_bg_1'>";
            echo "<td style='width: 35%;'><strong>{$c['name']}</strong></td>";
            echo "<td>ID {$c['id']} — Criada com sucesso</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr class='tab_bg_1'><td colspan='2'>Nenhuma categoria criada.</td></tr>";
    }
    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Botão para criar outra ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr class='tab_bg_2'>";
    echo "<td class='center' style='padding: 15px;'>";
    echo "<a href='" . $form_url . "' class='btn btn-outline-primary' style='font-size: 1.05em; padding: 8px 30px;'>";
    echo "<i class='fas fa-plus' style='margin-right: 6px;'></i> Criar Outro Setor";
    echo "</a>";
    echo "</td>";
    echo "</tr>";
    echo "</table>";
}

echo "</div>";

Html::footer();
