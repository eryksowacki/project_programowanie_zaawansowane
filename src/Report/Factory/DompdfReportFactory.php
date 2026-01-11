<?php

namespace App\Report\Factory;

use App\Report\Contracts\KpirHtmlBuilderInterface;
use App\Report\Contracts\ReportFactoryInterface;
use App\Report\Contracts\Renderers\PdfRendererInterface;
use App\Report\Contracts\Renderers\XlsxRendererInterface;
use App\Report\Kpir\KpirHtmlBuilder;
use App\Report\Renderers\DompdfPdfRenderer;
use App\Report\Renderers\PhpSpreadsheetXlsxRenderer;
use App\Report\Xlsx\Contracts\ContractorsXlsxBuilderInterface;

final class DompdfReportFactory implements ReportFactoryInterface
{
    public function __construct(
        private readonly KpirHtmlBuilder $kpirBuilder,
        private readonly ContractorsXlsxBuilderInterface $contractorsXlsxBuilder,
        private readonly DompdfPdfRenderer $pdfRenderer,
        private readonly PhpSpreadsheetXlsxRenderer $xlsxRenderer
    ) {}

    public function createKpirHtmlBuilder(): KpirHtmlBuilderInterface
    {
        return $this->kpirBuilder;
    }

    public function createPdfRenderer(): PdfRendererInterface
    {
        return $this->pdfRenderer;
    }

    public function createContractorsXlsxBuilder(): ContractorsXlsxBuilderInterface
    {
        return $this->contractorsXlsxBuilder;
    }

    public function createXlsxRenderer(): XlsxRendererInterface
    {
        return $this->xlsxRenderer;
    }
}