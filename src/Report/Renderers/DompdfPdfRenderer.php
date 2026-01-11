<?php

namespace App\Report\Renderers;

use App\Report\Contracts\Renderers\PdfRendererInterface;
use App\Report\Contracts\ReportFile;
use Dompdf\Dompdf;
use Dompdf\Options;

final class DompdfPdfRenderer implements PdfRendererInterface
{
    public function renderHtmlToPdf(string $html, string $filename): ReportFile
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $pdf = $dompdf->output();

        return new ReportFile(
            filename: $filename,
            contentType: 'application/pdf',
            content: $pdf
        );
    }
}