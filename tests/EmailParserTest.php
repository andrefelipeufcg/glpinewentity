<?php

namespace GlpiPlugin\Glpinewentity\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Teste unitário para a lógica de separação de e-mails 
 * contida no wizard do plugin.
 */
class EmailParserTest extends TestCase
{
    /**
     * Replica a lógica de separação de e-mails contida no wizard.class.php
     * array_filter(array_map('trim', preg_split('/[\n,]+/', $emails)))
     */
    private function parseEmails(string $emails): array {
        return array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $emails))));
    }

    public function testParseSingleEmail(): void
    {
        $result = $this->parseEmails('teste@dominio.com');
        $this->assertCount(1, $result);
        $this->assertEquals('teste@dominio.com', $result[0]);
    }

    public function testParseCommaSeparatedEmails(): void
    {
        $result = $this->parseEmails('teste1@dominio.com, teste2@dominio.com,teste3@dominio.com');
        $this->assertCount(3, $result);
        $this->assertEquals('teste1@dominio.com', $result[0]);
        $this->assertEquals('teste2@dominio.com', $result[1]);
        $this->assertEquals('teste3@dominio.com', $result[2]);
    }

    public function testParseNewlineSeparatedEmails(): void
    {
        $result = $this->parseEmails("teste1@dominio.com\nteste2@dominio.com\r\nteste3@dominio.com");
        $this->assertCount(3, $result);
        $this->assertEquals('teste1@dominio.com', $result[0]);
        $this->assertEquals('teste2@dominio.com', $result[1]);
        $this->assertEquals('teste3@dominio.com', $result[2]);
    }

    public function testParseMixedSeparatorsAndEmptyLines(): void
    {
        $result = $this->parseEmails("teste1@dominio.com, \nteste2@dominio.com,\n\r\nteste3@dominio.com,,");
        $this->assertCount(3, $result);
        $this->assertEquals('teste1@dominio.com', $result[0]);
        $this->assertEquals('teste2@dominio.com', $result[1]);
        $this->assertEquals('teste3@dominio.com', $result[2]);
    }
}
