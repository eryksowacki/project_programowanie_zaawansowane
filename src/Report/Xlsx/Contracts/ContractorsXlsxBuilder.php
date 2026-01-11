<?php

namespace App\Report\Xlsx\Contracts;

use App\Report\Xlsx\Contracts\ContractorsXlsxBuilderInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class ContractorsXlsxBuilder implements ContractorsXlsxBuilderInterface
{
    public function build(string $companyName, string $periodLabel, array $aggRows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Raport');

        $row = 1;
        $sheet->setCellValue("A{$row}", "Firma");
        $sheet->setCellValue("B{$row}", $companyName);
        $row++;

        $sheet->setCellValue("A{$row}", "Okres");
        $sheet->setCellValue("B{$row}", $periodLabel);
        $row += 2;

        $sheet->setCellValue("A{$row}", "Kontrahent");
        $sheet->setCellValue("B{$row}", "NIP");
        $sheet->setCellValue("C{$row}", "Typ");
        $sheet->setCellValue("D{$row}", "Ilość dokumentów");
        $sheet->setCellValue("E{$row}", "Suma Netto");
        $sheet->setCellValue("F{$row}", "Suma VAT");
        $sheet->setCellValue("G{$row}", "Suma Brutto");
        $row++;

        foreach ($aggRows as $a) {
            $sheet->setCellValue("A{$row}", $a['contractor']);
            $sheet->setCellValue("B{$row}", $a['taxId']);
            $sheet->setCellValue("C{$row}", $a['type']);
            $sheet->setCellValue("D{$row}", (int)$a['count']);
            $sheet->setCellValue("E{$row}", round((float)$a['net'], 2));
            $sheet->setCellValue("F{$row}", round((float)$a['vat'], 2));
            $sheet->setCellValue("G{$row}", round((float)$a['gross'], 2));
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}