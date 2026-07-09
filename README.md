# GLPI New Entity Plugin - Criação de toda a estrutura de uma nova entidade em uma única tela

O **GLPI New Entity Plugin** é um plugin de "onboarding" rápido e otimizado desenvolvido especificamente para o GLPI 11. Ele tem o propósito de automatizar a configuração estrutural completa de um novo Setor / Departamento em um único formulário unificado ("Wizard"). 

Ao invés de navegar por diversas telas diferentes do GLPI para criar entidades, configurar perfis, vincular usuários, criar grupos, associar técnicos e montar catálogos de serviços (categorias), este plugin resolve todo o processo em uma única tela, garantindo extrema agilidade, organização padronizada e minimizando erros humanos.

## ✨ Funcionalidades Principais

*   **Criação Rápida de Entidade:** Cria imediatamente uma nova entidade sob uma entidade-pai escolhida.
*   **Clonagem de Perfis Nativos:** Permite selecionar perfis preexistentes (como Super-Admin, Admin, Atendimento) e cloná-los, nomeando-os automaticamente com o prefixo da sigla do novo setor (Ex: `[DC] - Admin`).
*   **Vinculação Direta por E-mail:** Basta colar uma lista de e-mails, separados por vírgula ou quebra de linha. O plugin busca o usuário no banco de dados e automaticamente vincula o perfil à nova entidade em escopo recursivo.
*   **Gerenciamento Inteligente de Grupos e Subgrupos:**
    *   Cria automaticamente um "Grupo Pai" nomeado com a sigla do setor `(SIGLA)`.
    *   Permite a criação de múltiplos Subgrupos aninhados de forma dinâmica.
    *   Associa os técnicos atendentes aos respectivos subgrupos (ou diretamente ao Grupo Pai) a partir de uma lista de e-mails.
*   **Construção Automática de Árvore de Categorias ITIL:**
    *   Aceita listas hierárquicas em formato de texto usando hífens (ex: `- Hardware`, `-- Manutenção`, `--- Reparos`).
    *   Cria categorias de incidentes e requisições perfeitamente aninhadas com apenas um clique.
*   **Controle de Acesso Super-Admin:** A interface do plugin é 100% restrita ao perfil Super-Admin nativo do GLPI (ID 4), ocultando-se completamente para qualquer outro usuário, não gerando poluição visual nos menus de quem não possui privilégios para criar infraestruturas.
*   **Edição e Atualização Sincronizada:** Em caso de ajustes futuros, o sistema armazena os metadados e permite reeditar os grupos, vínculos de e-mails ou nomes do setor a partir de um registro central.

## 🛠️ Requisitos

*   **GLPI:** Versão 11.0.0 ou superior.
*   **PHP:** Versões suportadas pelo GLPI 11 (8.1, 8.2, 8.3, 8.4).

## 🚀 Instalação

1. Baixe os arquivos do repositório ou clone este projeto no diretório de plugins do seu servidor GLPI:
   ```bash
   cd /var/www/html/glpi/plugins/
   git clone https://github.com/andrefelipeufcg/glpinewentity.git
   ```
2. Certifique-se de que a pasta se chama estritamente `glpinewentity`. Corrija a nomenclatura se o git baixar com um sufixo diferente.
3. Acesse a interface web do GLPI com seu usuário **Super-Admin**.
4. Navegue até **Configurar > Plug-ins**.
5. Na lista, localize o `GLPI New Entity`, clique no botão de **Instalar** (ícone de pasta) e depois no botão **Ativar** (ícone de ligar).

## 🖥️ Como Usar

1. Entre no GLPI utilizando um usuário com perfil **Super-Admin**.
2. No menu principal superior, navegue até **Configurar > GLPI New Entity**.
3. Você visualizará uma lista (vazia caso seja a primeira vez) das infraestruturas de setores gerenciadas pelo plugin.
4. Clique em **Adicionar** (ou botão "+" dependendo do seu tema).
5. Siga as instruções do Wizard:
   *   **Entidade-Pai e Sigla:** Indique a localização na árvore corporativa e a sigla (que dará nome à entidade e aos grupos).
   *   **Perfis:** Determine de onde clonar o perfil e quais os e-mails dos encarregados daquele setor para herdarem esse acesso de forma recursiva.
   *   **Subgrupos e Técnicos:** Adicione quantos subgrupos precisar, e cole todos os e-mails dos técnicos responsáveis.
   *   **Catálogo (Categorias):** Cole ou escreva sua árvore de categorias usando "-" para subníveis.
6. Clique em **Salvar**.

O sistema irá iterar e provisionar o ambiente inteiro, exibindo alertas descritivos (inclusive ignorando graciosamente e-mails inexistentes) e lhe devolvendo uma base pronta para operar em questão de segundos!

## ⚙️ Estrutura Técnica e Classes

*   `setup.php`: Inicialização, ganchos (hooks) e registro no núcleo do GLPI.
*   `front/sector.php`: Interface principal (Listagem).
*   `front/sector.form.php`: View principal (Formulário do Wizard e lógica de captura HTML/JavaScript).
*   `inc/menu.class.php`: Lógica exclusiva para inserção e restrição (Super-Admin) no menu superior nativo de "Configurar" do GLPI.
*   `inc/sector.class.php`: Classe CRUD básica para persistência de dados do andamento no banco, extendendo `CommonDBTM`.
*   `inc/wizard.class.php`: Coração do plugin. Contém toda a lógica estrutural, manipulação do banco via `PDO`, criação de cópias de perfis (`cloneProfile`) e validação de e-mails (`findUserByEmail`).

## 📜 Licença

GPLv3+ - Distribuído sob as mesmas diretrizes da licença open-source do ecossistema GLPI.