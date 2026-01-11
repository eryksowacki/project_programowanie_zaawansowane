<?php

namespace App\Report\Contracts\Renderers;

use App\Report\Contracts\ReportFile;

interface PdfRendererInterface
{
    public function renderHtmlToPdf(string $html, string $filename): ReportFile;
}