<?php

namespace GlpiPlugin\Glpinewentity\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Teste unitário para a lógica de mapeamento do grupo pai
 * contida no wizard do plugin.
 */
class SubgroupParentMappingTest extends TestCase
{
    /**
     * Replica a lógica de definição do ID do grupo pai.
     */
    private function resolveParentGroupId(string $sgParentIndex, int $rootParentId, array $createdGroupsByIndex): int {
        $currentParentGroupId = $rootParentId;
        if (isset($createdGroupsByIndex[$sgParentIndex])) {
            $currentParentGroupId = $createdGroupsByIndex[$sgParentIndex];
        }
        return $currentParentGroupId;
    }

    public function testFallbackToRootParentIfIndexInvalid(): void
    {
        $createdGroupsByIndex = [
            '-1' => 10,
            '0'  => 15
        ];
        
        $result = $this->resolveParentGroupId('99', 10, $createdGroupsByIndex);
        $this->assertEquals(10, $result, 'Deve retornar o grupo pai raiz caso o índice não exista');
    }

    public function testResolvesCorrectParentIndex(): void
    {
        $createdGroupsByIndex = [
            '-1' => 10,
            '0'  => 15,
            '1'  => 22
        ];
        
        $result = $this->resolveParentGroupId('1', 10, $createdGroupsByIndex);
        $this->assertEquals(22, $result, 'Deve resolver o ID do grupo mapeado no índice');
    }
    
    public function testResolvesRootSiglaProperly(): void
    {
        $createdGroupsByIndex = [
            '-1' => 10,
            '0'  => 15
        ];
        
        $result = $this->resolveParentGroupId('-1', 10, $createdGroupsByIndex);
        $this->assertEquals(10, $result, 'Deve resolver o ID 10 para o índice raiz (-1)');
    }
}
