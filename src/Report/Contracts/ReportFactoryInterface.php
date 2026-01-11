<?php

namespace App\Report\Contracts;

use App\Report\Contracts\Renderers\PdfRendererInterface;
use App\Report\Contracts\Renderers\XlsxRendererInterface;
use App\Report\Xlsx\Contracts\ContractorsXlsxBuilderInterface;

interface ReportFactoryInterface
{
    public function createKpirHtmlBuilder(): KpirHtmlBuilderInterface;
    public function createPdfRenderer(): PdfRendererInterface;

    public function createContractorsXlsxBuilder(): ContractorsXlsxBuilderInterface;
    public function createXlsxRenderer(): XlsxRendererInterface;
}