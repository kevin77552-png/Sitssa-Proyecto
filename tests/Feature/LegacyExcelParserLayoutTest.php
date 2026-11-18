<?php

namespace Tests\Feature;

use App\Services\Import\LegacyExcelParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyExcelParserLayoutTest extends TestCase
{
    use RefreshDatabase;

    private string $workbookPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workbookPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parser-layout-'.bin2hex(random_bytes(5)).'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->workbookPath)) {
            unlink($this->workbookPath);
        }

        parent::tearDown();
    }

    public function test_inventory_and_total_columns_are_ignored_and_each_sigicov_is_one_unit(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('JUNIO 2025 (2)');
        $headers = [
            'A6' => 'SIGICOV',
            'B6' => 'DESCRIPCIÓN',
            'C6' => 'PASILLO',
            'D6' => 'INVENTARIO REEMPLAZO',
            'E6' => 'REEMPLAZO',
            'F6' => 'INVENTARIO',
            'G6' => 'TOTAL INV. JUNIO',
            'D8' => '99',
            'E8' => '88',
            'F8' => '12',
            'G8' => 'not-a-quantity',
            'A8' => '12345',
            'B8' => 'REPUESTO',
            'C8' => 'A1',
            'D9' => '77',
            'E9' => '66',
            'F9' => '5',
            'G9' => '99',
            'A9' => '12346',
            'B9' => 'REPUESTO DOS',
            'C9' => 'A2',
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        (new Xlsx($spreadsheet))->save($this->workbookPath);
        $spreadsheet->disconnectWorksheets();

        $parser = app(LegacyExcelParser::class);
        $inspection = $parser->inspect($this->workbookPath);
        $rows = iterator_to_array($parser->rows($this->workbookPath));

        $this->assertArrayNotHasKey('inventario', $inspection['columns']);
        $this->assertSame('1', $rows[0]['payload']['inventario']);
        $this->assertSame('1', $rows[1]['payload']['inventario']);
        $this->assertSame([], $rows[0]['payload']['data']);
        $this->assertNull($rows[0]['invalid_reason']);
    }
}
