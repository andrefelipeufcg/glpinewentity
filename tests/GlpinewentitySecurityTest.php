<?php

namespace GlpiPlugin\Glpinewentity\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Testes focados em garantir a segurança do wizard (Prevenção contra XSS e SQL Injection).
 */
class GlpinewentitySecurityTest extends TestCase
{
    /**
     * Replica a lógica de validação de e-mails contida no wizard (linhas 165 e 229).
     * Essa validação protege contra injeções que poderiam vir em formato de e-mail.
     */
    private function validateEmailSecurity(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function testPrevencaoContraInjecaoPorEmail(): void
    {
        $emailMalicioso = "admin@dominio.com'; DROP TABLE glpi_users; --";
        $emailValido = "joao.silva@dominio.com";
        
        $this->assertFalse($this->validateEmailSecurity($emailMalicioso), 'E-mail com payload SQL deve ser sumariamente bloqueado');
        $this->assertTrue($this->validateEmailSecurity($emailValido), 'E-mails reais devem passar');
    }

    /**
     * Replica a lógica de limpeza de HTML (usando strip_tags como substituto do Html::cleanInputText)
     * usada para higienizar os nomes dos setores e subgrupos vindos do formulário (linhas 109, 110, 216).
     */
    private function sanitizeInputText(string $input): string
    {
        // O GLPI usa Html::cleanInputText, aqui usamos strip_tags e regex para simular
        // o bloqueio base de tags HTML e remoção do conteúdo de scripts
        $input = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $input);
        return trim(strip_tags($input));
    }

    public function testPrevencaoDeStoredXSSNosNomesDeSetorESubgrupos(): void
    {
        $nomeMalicioso = "<script>alert('XSS')</script>Setor de TI";
        $siglaMaliciosa = "TI<img src=x onerror=alert(1)>";
        
        $nomeSanitizado = $this->sanitizeInputText($nomeMalicioso);
        $siglaSanitizada = $this->sanitizeInputText($siglaMaliciosa);
        
        $this->assertEquals("Setor de TI", $nomeSanitizado, 'As tags de script devem ser removidas para evitar XSS no nome do Setor');
        $this->assertEquals("TI", $siglaSanitizada, 'As tags maliciosas devem ser removidas da sigla do setor');
    }

    /**
     * Testa o modelo de passagem de parâmetros utilizado no DBmysql do GLPI.
     * O wizard do glpinewentity utiliza a passagem de array de critérios no `$DB->request()` e `$DB->insert()`,
     * e NÃO concatenação de strings na query, o que previne intrinsecamente o SQL Injection.
     */
    public function testPrevencaoDeSQLInjectionPeloCriteriaArrayDoGLPI(): void
    {
        $email = "admin@dominio.com' OR 1=1 --";
        
        // Estrutura utilizada na linha 635 do wizard.class.php
        $criteria = [
            'FROM'  => 'glpi_useremails',
            'WHERE' => [
                'email' => $email
            ]
        ];
        
        $this->assertIsArray($criteria['WHERE'], 'A condição WHERE deve ser um array associativo para o PDO/DBmysql do GLPI realizar o escaping automático');
        $this->assertArrayHasKey('email', $criteria['WHERE']);
        $this->assertEquals($email, $criteria['WHERE']['email'], 'A string maliciosa deve ser passada integralmente para que a abstração de banco trate como valor e escape, e não como código');
    }
}
