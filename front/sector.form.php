<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/sector.form.php
 * Formulário para criação e edição de infraestrutura de novo setor.
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
$isEdit     = false;
$sectorId   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sectorObj  = new PluginGlpinewentitySector();

if ($sectorId > 0) {
    if ($sectorObj->getFromDB($sectorId)) {
        $isEdit = true;
    } else {
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
    }
}

if (isset($_POST['process_wizard'])) {
    // No GLPI 11+, o CheckCsrfListener já valida e consome o token globalmente.
    // Chamar checkCSRF novamente falha porque o token já foi consumido.
    // Em versões antigas (ex: 9.5), precisamos checar manualmente.
    if (!class_exists('Glpi\Kernel\Listener\ControllerListener\CheckCsrfListener')) {
        Session::checkCSRF($_POST);
    }

    if ($isEdit) {
        $result = PluginGlpinewentityWizard::processUpdate($_POST, $sectorObj->fields);
        // Atualiza metadata
        if (empty($result['errors'])) {
            $sectorObj->update([
                'id' => $sectorId,
                'sector_name' => $_POST['sector_name'],
                'sector_abbr' => $_POST['sector_abbr'],
                'metadata' => json_encode($result)
            ]);
            Session::addMessageAfterRedirect('Infraestrutura atualizada com sucesso!', true, INFO);
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php?id=' . $sectorId);
        }
    } else {
        $result = PluginGlpinewentityWizard::processCreation($_POST);
        
        if (empty($result['errors']) && $result['entity_id'] > 0) {
            $sectorObj->add([
                'entities_id' => (int)$_POST['parent_entity'],
                'sector_name' => $_POST['sector_name'],
                'sector_abbr' => $_POST['sector_abbr'],
                'metadata' => json_encode($result)
            ]);
            Session::addMessageAfterRedirect('Infraestrutura criada com sucesso!', true, INFO);
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
        }
    }
    
    $showResult = true;
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) {
            Session::addMessageAfterRedirect($err, false, ERROR);
        }
    }
}

// -----------------------------------------------------------------------
// Carrega dados para edição
// -----------------------------------------------------------------------
$def_sector_name = '';
$def_sector_abbr = '';
$def_parent_entity = 0;
$def_category_names = '';
$def_subgroups = [];

if ($isEdit) {
    $meta = json_decode($sectorObj->fields['metadata'], true) ?: [];
    $def_sector_name = $sectorObj->fields['sector_name'];
    $def_sector_abbr = $sectorObj->fields['sector_abbr'];
    $def_parent_entity = $sectorObj->fields['entities_id'];
    // Monta subgrupos a partir do metadata se necessário (simplificado para form)
    $def_subgroups = $meta['groups'] ?? []; 
}

// -----------------------------------------------------------------------
// RENDERIZAÇÃO DA PÁGINA
// -----------------------------------------------------------------------
Html::header('GLPI New Entity — Form', $_SERVER['PHP_SELF'], 'config', 'plugins');

global $CFG_GLPI;
$form_url = $CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php';

echo "<div class='center' style='margin-top: 20px;'>";
echo "<style>
        .tab_cadre_fixe td {
            vertical-align: top !important;
        }
        .tab_cadre_fixe td:not([style*=\"padding: 0\"]) {
            padding-top: 15px !important;
            padding-bottom: 15px !important;
        }
    </style>";

// =====================================================================
// FORMULÁRIO WIZARD
// =====================================================================
if (!$showResult || ($showResult && $result['entity_id'] === 0)) {

    echo "<form method='post' action='" . $form_url . "' id='form_wizard'>";
    echo "<input type='hidden' name='process_wizard' value='1'>";
    if ($isEdit) {
        echo "<input type='hidden' name='id' value='{$sectorId}'>";
    }

    // ── Título Principal ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2' style='font-size: 1.2em;'>";
    echo "Nova Entidade para Central de Serviços";
    echo "</th></tr>";
    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 1: Dados da Entidade ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-building' style='margin-right: 5px;'></i> Dados da Entidade</th></tr>";

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
    echo "<input type='text' name='sector_name' class='form-control' style='width: 100%;' placeholder='Ex: Departamento de Computação' value='" . Html::cleanInputText($def_sector_name) . "' required>";
    echo "</td>";
    echo "</tr>";

    // Sigla
    echo "<tr class='tab_bg_1'>";
    echo "<td>Sigla <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<input type='text' name='sector_abbr' class='form-control' style='width: 100%;' placeholder='Ex: DC' value='" . Html::cleanInputText($def_sector_abbr) . "' maxlength='20' required>";
    echo "<br><small class='text-muted'>A entidade será criada com o mesmo nome da sigla.</small>";
    echo "</td>";
    echo "</tr>";



    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 2: Perfis ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-id-card' style='margin-right: 5px;'></i> Perfis</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 15px;'>";
    
    // Carregar lista de perfis do banco
    $profiles = [];
    $profile_obj = new Profile();
    foreach ($profile_obj->find([], ['name']) as $p) {
        $profiles[$p['id']] = $p['name'];
    }

    echo "<div id='perfis-padrao-section'>";



    // Admin
    echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;'>";
    echo "  <div style='display: flex; gap: 10px;'>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Perfil Padrão</label>";
    echo "      <input type='text' id='profile_admin' name='profiles_default[]' class='form-control' style='width: 100%; border: none; background: #e9ecef;' readonly>";
    echo "    </div>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "      <select name='copy_profile_admin' class='form-select profile-select2' style='width: 100%;'>";
    echo "        <option value='0'>-----</option>";
    foreach ($profiles as $pid => $pname) {
        $selected = (strpos($pname, '[Padrão] Admin') !== false) ? 'selected' : '';
        echo "        <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "      </select>";
    echo "    </div>";
    echo "  </div>";
    echo "  <div>";
    echo "    <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    echo "    <textarea name='users_profile_admin' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
    echo "  </div>";
    echo "</div>";

    // Atendimento
    echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;'>";
    echo "  <div style='display: flex; gap: 10px;'>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Perfil Padrão</label>";
    echo "      <input type='text' id='profile_support' name='profiles_default[]' class='form-control' style='width: 100%; border: none; background: #e9ecef;' readonly>";
    echo "    </div>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "      <select name='copy_profile_support' class='form-select profile-select2' style='width: 100%;'>";
    echo "        <option value='0'>-----</option>";
    foreach ($profiles as $pid => $pname) {
        $selected = (strpos($pname, '[Padrão] Atendimento') !== false) ? 'selected' : '';
        echo "        <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "      </select>";
    echo "    </div>";
    echo "  </div>";
    echo "  <div>";
    echo "    <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    echo "    <textarea name='users_profile_support' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
    echo "  </div>";
    echo "</div>";

    // Transferência de Chamados
    echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;'>";
    echo "  <div style='display: flex; gap: 10px;'>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Perfil Padrão</label>";
    echo "      <input type='text' id='profile_transfer' name='profiles_default[]' class='form-control' style='width: 100%; border: none; background: #e9ecef;' readonly>";
    echo "    </div>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "      <select name='copy_profile_transfer' class='form-select profile-select2' style='width: 100%;'>";
    echo "        <option value='0'>-----</option>";
    foreach ($profiles as $pid => $pname) {
        $selected = (strpos($pname, '[Padrão] Transferência de Chamados') !== false) ? 'selected' : '';
        echo "        <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "      </select>";
    echo "    </div>";
    echo "  </div>";
    echo "  <div>";
    echo "    <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    echo "    <textarea name='users_profile_transfer' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
    echo "  </div>";
    echo "</div>";

    echo "</div>"; // fecha #perfis-padrao-section
    
    echo "<br><small class='text-muted'>Os 'Perfis Padrão' são criados automaticamente com base na SIGLA e não podem ser apagados, mas você pode adicionar um novo em 'Adicionar Perfil'.</small>";
    echo "</td>";
    echo "</tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 0;'>";
    echo "<div id='profiles-container'>";
    
    // Template oculto para adicionar perfis customizados
    echo "<div class='profile-block template' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa; display: none;'>";
    echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
    echo "      <strong>Perfil Adicional</strong>";
    echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-profile'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
    echo "  </div>";
    echo "  <div style='display: flex; gap: 10px; align-items: flex-start;'>";
    echo "      <div style='flex: 1;'>";
    echo "          <label style='display: block; margin-bottom: 5px; color: #444;'>Nome do Perfil</label>";
    echo "          <input type='text' class='form-control profile-input' style='width: 100%;' placeholder='Ex: SIGLA - Coordenador'>";
    echo "      </div>";
    echo "      <div style='flex: 1;'>";
    echo "          <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "          <select name='copy_profile_custom[]' class='form-select profile-select2' style='width: 100%;'>";
    echo "            <option value='0'>-----</option>";
    foreach ($profiles as $pid => $pname) {
        echo "            <option value='{$pid}'>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "          </select>";
    echo "      </div>";
    echo "  </div>";
    echo "  <div style='margin-top: 10px;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    echo "      <textarea name='users_profile_custom[]' class='form-control profile-users-input' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
    echo "  </div>";
    echo "</div>";

    echo "</div>";
    echo "<div style='padding: 0 10px 10px 10px;'>";
    echo "<button type='button' class='btn btn-success btn-sm' id='btn-add-profile'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar Perfil</button>";
    echo "</div>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";


    // ── Bloco 3: Grupos e Técnicos Atendentes ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-users-cog' style='margin-right: 5px;'></i> Grupos e Técnicos Atendentes</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 0;'>";
    echo "<div id='subgroups-container'>";
    
    if (empty($def_subgroups) || count($def_subgroups) <= 1) {
        // Se não tem subgrupos, ou só tem o pai (índice 0), renderiza 1 bloco vazio
        echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa;'>";
        echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
        echo "      <strong>Subgrupo <span class='sg-index'>1</span></strong>";
        echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='display:none;'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
        echo "  </div>";
        echo "  <div style='margin-bottom: 10px;'>";
        echo "      <label>Nome do Subgrupo</label>";
        echo "      <input type='text' name='subgroups[0][name]' class='form-control' style='width: 100%;' placeholder='Ex: Suporte Nível 1 (Deixe em branco para alocar no Grupo Pai)'>";
        echo "  </div>";
        echo "  <div>";
        echo "      <label>E-mails dos Técnicos Atendentes</label>";
        echo "      <textarea name='subgroups[0][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este subgrupo. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
        echo "      <small class='text-muted'>Devem estar cadastrados no GLPI. Se informar um subgrupo, os técnicos irão EXCLUSIVAMENTE para ele. Senão, irão para o Grupo Pai <strong>({SIGLA})</strong>.</small>";
        echo "  </div>";
        echo "</div>";
    } else {
        // Tem subgrupos (além do pai). O índice 0 no $def_subgroups é o pai.
        // Se a pessoa preencheu subgrupos, o metadata os salvou a partir do índice 1.
        $i = 0;
        foreach ($def_subgroups as $idx => $sg) {
            if ($idx === 0) continue; // Pula o grupo pai que só foi salvo no metadata, mas não no form
            
            // Wait, this requires mapping technicians for each group. The metadata currently doesn't store techs per group easily!
            // I'll just render empty blocks based on the count for now, because extracting techs back to textarea is complex.
            // Actually, let's just render the names.
            $sgName = Html::cleanInputText($sg['name']);
            
            echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa;'>";
            echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
            echo "      <strong>Subgrupo <span class='sg-index'>".($i+1)."</span></strong>";
            echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='".($i==0 ? 'display:none;' : '')."'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
            echo "  </div>";
            echo "  <div style='margin-bottom: 10px;'>";
            echo "      <label>Nome do Subgrupo</label>";
            echo "      <input type='text' name='subgroups[{$i}][name]' class='form-control' style='width: 100%;' value='{$sgName}' placeholder='Ex: Suporte Nível 1 (Deixe em branco para alocar no Grupo Pai)'>";
            echo "  </div>";
            echo "  <div>";
            echo "      <label>E-mails dos Técnicos Atendentes</label>";
            echo "      <textarea name='subgroups[{$i}][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este subgrupo. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
            echo "      <small class='text-muted'>Na edição, não estamos listando os e-mails antigos. Se quiser sincronizar, preencha novamente.</small>";
            echo "  </div>";
            echo "</div>";
            $i++;
        }
    }
    
    echo "</div>"; // Fim subgroups-container
    
    echo "<div style='margin: 10px;'>";
    echo "  <button type='button' id='btn-add-subgroup' class='btn btn-sm btn-primary'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar outro Subgrupo</button>";
    echo "</div>";
    
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 4: Catálogo de Serviços ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-clipboard-list' style='margin-right: 5px;'></i> Catálogo de Serviços (Categorias ITIL)</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>Categorias de Serviço <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<textarea name='category_names' class='form-control' style='width: 100%; height: 160px; overflow-y: scroll;' placeholder='Uma categoria por linha. Use hífen (-) para subcategorias.&#10;Ex:&#10;Hardware&#10;- Manutenção de Hardware&#10;-- Troca de Peças&#10;Software&#10;- Instalação de Software' required></textarea>";
    echo "<br><small class='text-muted'>Cada categoria será vinculada exclusivamente à nova entidade, habilitada para Incidentes e Requisições.<br><strong>Importante:</strong> O sistema só identificará a hierarquia (Categorias Pai e Filha) se você usar o hífen (-) no início da linha correspondente.</small>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";



    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Botão Submeter ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr class='tab_bg_2'>";
    echo "<td class='center' style='padding: 15px;'>";
    $btnTitle = $isEdit ? 'Salvar Modificações' : 'Criar Infraestrutura da Entidade';
    
    echo "<button type='submit' class='btn btn-primary' style='font-size: 1.05em; padding: 8px 30px;'>";
    echo $btnTitle;
    echo "</button>";
    echo "</td>";
    echo "</tr>";
    echo "</table>";

    Html::closeForm();

    echo "<script>
    $(function() {
        function validateEmailsStr(str) {
            let cleanStr = str.trim();
            if (cleanStr === '') return false;
            let emails = cleanStr.split(/[\\n,]+/);
            let emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
            for (let i = 0; i < emails.length; i++) {
                let e = emails[i].trim();
                if (e !== '' && !emailRegex.test(e)) {
                    return false;
                }
            }
            return true;
        }

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

        // ── Lógica para Perfis Padrão ──
        $('input[name=\'sector_abbr\']').on('input', function() {
            let abbr = $(this).val().trim();
            if (abbr === '') {
                $('#profile_admin').val('');
                $('#profile_support').val('');
                $('#profile_transfer').val('');
            } else {
                $('#profile_admin').val(abbr + ' - Admin');
                $('#profile_support').val(abbr + ' - Atendimento');
                $('#profile_transfer').val(abbr + ' - Transferência de Chamados');
            }
        });
        // Inicializa ao carregar (se houver valor padrão)
        $('input[name=\'sector_abbr\']').trigger('input');

        // Inicializa o select2 explicitamente com 100% de largura
        $('.profile-select2').select2({ width: '100%' });

        // ── Lógica para Adicionar Perfis Customizados ──
        $('#btn-add-profile').on('click', function() {
            const container = $('#profiles-container');
            const template = container.find('.profile-block.template');
            
            // Valida os visíveis atuais
            let allFilled = true;
            let emailsValid = true;
            container.find('.profile-block:visible').each(function() {
                const nameVal = $(this).find('.profile-input').val().trim();
                const copyVal = $(this).find('select').val();
                const usersVal = $(this).find('.profile-users-input').val().trim();
                if (nameVal === '' || copyVal === '' || copyVal === null || copyVal === '0' || usersVal === '') {
                    allFilled = false;
                } else if (!validateEmailsStr(usersVal)) {
                    emailsValid = false;
                }
            });
            
            if (!allFilled || !emailsValid) {
                alert('Por favor, preencha o nome do perfil, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) antes de adicionar um novo.');
                return;
            }
            
            const newBlock = template.clone();
            newBlock.removeClass('template');
            newBlock.css('display', 'block');
            newBlock.find('.profile-input').attr('name', 'profiles_custom[]');
            
            // Limpa o textarea de usuários
            newBlock.find('.profile-users-input').val('');
            
            // Remove o lixo do select2 clonado
            newBlock.find('.select2-container').remove();
            
            // Restaura o select original para inicializar o select2 novamente
            let selectEl = newBlock.find('select');
            selectEl.removeClass('select2-hidden-accessible')
                    .removeAttr('data-select2-id')
                    .removeAttr('tabindex')
                    .removeAttr('aria-hidden')
                    .show();
            
            // Gera um ID novo e limpa o valor selecionado
            let newId = 'dropdown_copy_profile_' + Date.now();
            selectEl.attr('id', newId).val('0');
            selectEl.find('option').removeAttr('data-select2-id');

            newBlock.find('.btn-remove-profile').on('click', function() {
                newBlock.remove();
            });
            
            container.append(newBlock);

            // Inicializa select2 no novo dropdown com largura total
            $('#' + newId).select2({ width: '100%' });
        });

        // Validação no Submit do Formulário
        $('#form_wizard').on('submit', function(e) {
            let standardFilled = true;
            let standardEmailsValid = true;
            $('#perfis-padrao-section select').each(function() {
                if ($(this).val() === '0' || $(this).val() === null) {
                    standardFilled = false;
                }
            });
            $('#perfis-padrao-section textarea').each(function() {
                let usersVal = $(this).val().trim();
                if (usersVal === '') {
                    standardFilled = false;
                } else if (!validateEmailsStr(usersVal)) {
                    standardEmailsValid = false;
                }
            });
            
            if (!standardFilled || !standardEmailsValid) {
                e.preventDefault();
                alert('Por favor, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) para todos os Perfis Padrão.');
                return false;
            }

            let customFilled = true;
            let customEmailsValid = true;
            $('#profiles-container .profile-block:visible').each(function() {
                const nameVal = $(this).find('.profile-input').val().trim();
                const copyVal = $(this).find('select').val();
                const usersVal = $(this).find('.profile-users-input').val().trim();
                
                if (nameVal === '' || copyVal === '' || copyVal === null || copyVal === '0' || usersVal === '') {
                    customFilled = false;
                } else if (!validateEmailsStr(usersVal)) {
                    customEmailsValid = false;
                }
            });
            
            if (!customFilled || !customEmailsValid) {
                e.preventDefault();
                alert('Por favor, preencha o nome do perfil, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) para todos os Perfis Adicionais.');
                return false;
            }
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
