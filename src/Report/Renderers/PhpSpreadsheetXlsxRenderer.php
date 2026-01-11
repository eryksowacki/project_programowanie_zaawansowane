<?php

namespace App\Report\Renderers;

use App\Report\Contracts\Renderers\XlsxRendererInterface;
use App\Report\Contracts\ReportFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class PhpSpreadsheetXlsxRenderer implements XlsxRendererInterface
{
    public function renderSpreadsheet(Spreadsheet $spreadsheet, string $filename): ReportFile
    {
        $writer = new Xlsx($spreadsheet);

        $fh = fopen('php://memory', 'r+');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open memory stream');
        }

        $writer->save($fh);
        rewind($fh);

        $content = stream_get_contents($fh);
        fclose($fh);

        if ($content === false) {
            throw new \RuntimeException('Cannot read XLSX content from memory stream');
        }

        return new ReportFile(
            filename: $filename,
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            content: $content
        );
    }
}