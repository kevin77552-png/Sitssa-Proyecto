<?php

namespace Tests\Feature;

use App\Services\Import\LegacyExcelParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyExcelParserDateCellTest extends TestCase
{
    use RefreshDatabase;

    private string $workbookPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workbookPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parser-date-'.bin2hex(random_bytes(5)).'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->workbookPath)) {
            unlink($this->workbookPath);
        }

        parent::tearDown();
    }

    public function test_parser_reads_excel_date_cells_without_string_cast_errors(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('JUNIO 2025 (2)');
        foreach ([
            'A6' => 'SIGICOV',
            'B6' => 'DESCRIPCIÓN',
            'C6' => 'PASILLO',
            'D6' => 'FECHA',
            'E6' => 'INVENTARIO',
            'A8' => '12301',
            'B8' => 'REPUESTO CON FECHA',
            'C8' => 'A1',
            'D8' => Date::PHPToExcel(new \DateTimeImmutable('2025-06-30')),
            'E8' => '3',
        ] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->getStyle('D8')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        (new Xlsx($spreadsheet))->save($this->workbookPath);
        $spreadsheet->disconnectWorksheets();

        $rows = iterator_to_array(app(LegacyExcelParser::class)->rows($this->workbookPath));

        $this->assertCount(1, $rows);
        $this->assertSame('2025-06-30', $rows[0]['original']['3']);
        $this->assertSame('2025-06-30', $rows[0]['payload']['fecha']);
    }
}
