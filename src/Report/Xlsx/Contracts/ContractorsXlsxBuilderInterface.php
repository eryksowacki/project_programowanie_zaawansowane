<?php

namespace App\Report\Xlsx\Contracts;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

interface ContractorsXlsxBuilderInterface
{
    /**
     * @param array<int, array{
     *   contractor:string, taxId:string, type:string, count:int, net:float, vat:float, gross:float
     * }> $aggRows
     */
    public function build(
        string $companyName,
        string $periodLabel,
        array $aggRows
    ): Spreadsheet;
}