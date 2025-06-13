<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\OptinData;

class OptinDataTest extends TestCase {
    private \ReflectionMethod $escapeMethod;

    protected function setUp(): void {
        $this->escapeMethod = new \ReflectionMethod(OptinData::class, 'escape_csv_field');
        $this->escapeMethod->setAccessible(true);
    }

    public function test_escape_csv_field_prefixes_formula() {
        $this->assertSame("'=1+1", $this->escapeMethod->invoke(null, '=1+1'));
        $this->assertSame("'+SUM(A1)", $this->escapeMethod->invoke(null, '+SUM(A1)'));
    }

    public function test_escape_csv_field_unchanged() {
        $this->assertSame('plain', $this->escapeMethod->invoke(null, 'plain'));
    }
}
