<?php

namespace App\Report\Contracts\Renderers;

use App\Report\Contracts\ReportFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

interface XlsxRendererInterface
{
    public function renderSpreadsheet(Spreadsheet $spreadsheet, string $filename): ReportFile;
}