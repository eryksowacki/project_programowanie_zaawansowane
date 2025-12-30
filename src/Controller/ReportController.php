<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/reports')]
class ReportController extends AbstractController
{
    /**
     * POST /api/reports/kpir
     * Body:
     *  { "mode":"month", "year":2025, "month":12 }
     *  { "mode":"quarter", "year":2025, "quarter":4 }
     *  { "mode":"year", "year":2025 }
     */
    #[Route('/kpir', name: 'api_reports_kpir_pdf', methods: ['POST'])]
    public function kpirPdf(
        #[CurrentUser] ?User $user,
        Request $request,
        DocumentRepository $docRepo
    ): Response {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        $company = $user->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company assigned'], 400);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $mode = (string)($payload['mode'] ?? '');
        if (!in_array($mode, ['month', 'quarter', 'year'], true)) {
            return $this->json(['message' => 'Invalid mode. Allowed: month|quarter|year'], 400);
        }

        $year = (int)($payload['year'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            return $this->json(['message' => 'Invalid year'], 400);
        }

        try {
            [$from, $toExclusive, $periodTitle] = $this->resolvePeriod($mode, $payload, $year);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        // KPiR: BOOKED, po kolei (jak na księdze)
        $docs = $docRepo->createQueryBuilder('d')
            ->leftJoin('d.contractor', 'c')->addSelect('c')
            ->andWhere('d.company = :company')
            ->andWhere('d.status = :status')
            ->andWhere('d.eventDate >= :from AND d.eventDate < :to')
            ->setParameter('company', $company)
            ->setParameter('status', 'BOOKED')
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->orderBy('d.ledgerNumber', 'ASC')
            ->addOrderBy('d.eventDate', 'ASC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();

        $html = $this->renderKpirHtml(
            companyName: (string)$company->getName(),
            companyAddress: (string)($company->getAddress() ?? ''),
            companyTaxId: (string)($company->getTaxId() ?? ''),
            periodTitle: $periodTitle,
            docs: $docs
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // PL znaki
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape'); // jak na wzorze
        $dompdf->render();

        $pdf = $dompdf->output();
        $filename = sprintf('kpir-%s.pdf', $this->safeFilename($periodTitle));

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * POST /api/reports/contractors-xlsx
     * Body:
     *  {
     *    "dateFrom":"2025-12-01",
     *    "dateTo":"2025-12-30",
     *    "includeIncome": true,
     *    "includeCost": true
     *  }
     */
    #[Route('/contractors-xlsx', name: 'api_reports_contractors_xlsx', methods: ['POST'])]
    public function contractorsXlsx(
        #[CurrentUser] ?User $user,
        Request $request,
        DocumentRepository $docRepo
    ): Response {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        $company = $user->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company assigned'], 400);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $dateFrom = (string)($payload['dateFrom'] ?? '');
        $dateTo = (string)($payload['dateTo'] ?? '');
        $includeIncome = (bool)($payload['includeIncome'] ?? true);
        $includeCost = (bool)($payload['includeCost'] ?? true);

        $contractorId = array_key_exists('contractorId', $payload) ? $payload['contractorId'] : null;
        if ($contractorId !== null && $contractorId !== '') {
            $contractorId = (int) $contractorId;
        } else {
            $contractorId = null;
        }

        if (!$includeIncome && !$includeCost) {
            return $this->json(['message' => 'At least one of includeIncome/includeCost must be true'], 400);
        }

        try {
            $from = new \DateTimeImmutable($dateFrom);
            $to = new \DateTimeImmutable($dateTo);
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid dateFrom/dateTo. Use YYYY-MM-DD'], 400);
        }

        $toExclusive = $to->modify('+1 day');

        $types = [];
        if ($includeIncome) $types[] = 'INCOME';
        if ($includeCost) $types[] = 'COST';

        $qb = $docRepo->createQueryBuilder('d')
            ->leftJoin('d.contractor', 'c')->addSelect('c')
            ->andWhere('d.company = :company')
            ->andWhere('d.status = :status')
            ->andWhere('d.eventDate >= :from AND d.eventDate < :to')
            ->andWhere('d.type IN (:types)')
            ->setParameter('company', $company)
            ->setParameter('status', 'BOOKED')
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->setParameter('types', $types);

        if ($contractorId !== null) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $contractorId);
        }

        $docs = $qb->orderBy('d.eventDate', 'ASC')
            ->addOrderBy('d.ledgerNumber', 'ASC')
            ->getQuery()
            ->getResult();

        // agregacja: contractor + type
        $agg = [];
        /** @var Document $d */
        foreach ($docs as $d) {
            $con = $d->getContractor();
            $conName = $con?->getName() ?? '— (brak kontrahenta)';
            $conTaxId = $con?->getTaxId() ?? '';

            $key = $conName . '|' . $conTaxId . '|' . (string)$d->getType();

            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'contractor' => $conName,
                    'taxId' => $conTaxId,
                    'type' => (string)$d->getType(),
                    'count' => 0,
                    'net' => 0.0,
                    'vat' => 0.0,
                    'gross' => 0.0,
                ];
            }

            $agg[$key]['count'] += 1;
            $agg[$key]['net'] += (float)$d->getNetAmount();
            $agg[$key]['vat'] += (float)$d->getVatAmount();
            $agg[$key]['gross'] += (float)$d->getGrossAmount();
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Raport');

        $row = 1;
        $sheet->setCellValue("A{$row}", "Firma");
        $sheet->setCellValue("B{$row}", $company->getName());
        $row++;

        $sheet->setCellValue("A{$row}", "Okres");
        $sheet->setCellValue("B{$row}", $from->format('Y-m-d') . " do " . $to->format('Y-m-d'));
        $row += 2;

        $sheet->setCellValue("A{$row}", "Kontrahent");
        $sheet->setCellValue("B{$row}", "NIP");
        $sheet->setCellValue("C{$row}", "Typ");
        $sheet->setCellValue("D{$row}", "Ilość dokumentów");
        $sheet->setCellValue("E{$row}", "Suma Netto");
        $sheet->setCellValue("F{$row}", "Suma VAT");
        $sheet->setCellValue("G{$row}", "Suma Brutto");
        $row++;

        foreach ($agg as $a) {
            $sheet->setCellValue("A{$row}", $a['contractor']);
            $sheet->setCellValue("B{$row}", $a['taxId']);
            $sheet->setCellValue("C{$row}", $a['type'] === 'INCOME' ? 'Przychód' : ($a['type'] === 'COST' ? 'Koszt' : $a['type']));
            $sheet->setCellValue("D{$row}", (int)$a['count']);
            $sheet->setCellValue("E{$row}", round($a['net'], 2));
            $sheet->setCellValue("F{$row}", round($a['vat'], 2));
            $sheet->setCellValue("G{$row}", round($a['gross'], 2));
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = sprintf('raport-kontrahenci-%s_%s.xlsx', $from->format('Y-m-d'), $to->format('Y-m-d'));

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function denyUnlessEmployeeOrManager(): void
    {
        if ($this->isGranted('ROLE_SYSTEM_ADMIN')) {
            throw $this->createAccessDeniedException('Admins are not allowed');
        }
        if (!$this->isGranted('ROLE_EMPLOYEE') && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Forbidden');
        }
    }

    /**
     * Zwraca: [from, toExclusive, periodTitle]
     */
    private function resolvePeriod(string $mode, array $payload, int $year): array
    {
        if ($mode === 'month') {
            $month = (int)($payload['month'] ?? 0);
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('Invalid month (1-12)');
            }
            $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
            $toExclusive = $from->modify('+1 month');
            $title = sprintf('%s %d', $this->monthNamePL($month), $year);
            return [$from, $toExclusive, $title];
        }

        if ($mode === 'quarter') {
            $quarter = (int)($payload['quarter'] ?? 0);
            if ($quarter < 1 || $quarter > 4) {
                throw new \InvalidArgumentException('Invalid quarter (1-4)');
            }
            $startMonth = (($quarter - 1) * 3) + 1;
            $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
            $toExclusive = $from->modify('+3 month');
            $title = sprintf('Kwartał %d %d', $quarter, $year);
            return [$from, $toExclusive, $title];
        }

        $from = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $toExclusive = $from->modify('+1 year');
        $title = sprintf('Rok %d', $year);
        return [$from, $toExclusive, $title];
    }

    private function monthNamePL(int $month): string
    {
        return [
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        ][$month] ?? 'Miesiąc';
    }

    private function safeFilename(string $s): string
    {
        $s = preg_replace('/[^a-zA-Z0-9\-_]+/u', '_', $s);
        return trim($s ?: 'report', '_');
    }

    /**
     * KPiR: tabela jak na wzorze (kolumny 1–17 + Lp).
     * Mapowanie uproszczone:
     * - INCOME -> kol. 7 i 9 (sprzedaż + razem przychód)
     * - COST   -> kol. 13 i 14 (pozostałe wydatki + razem wydatki)
     * - kol. 15 (Opis kosztu B+R) zostawiamy puste
     * - kol. 16 (Wartość B+R) = 0.00
     */
    private function renderKpirHtml(
        string $companyName,
        string $companyAddress,
        string $companyTaxId,
        string $periodTitle,
        array $docs
    ): string {
        $rowsHtml = '';
        $lp = 0;

        // sumy tylko dla kolumn kwotowych 7..14 i 16
        $sum = [
            7 => 0.0, 8 => 0.0, 9 => 0.0,
            10 => 0.0, 11 => 0.0,
            12 => 0.0, 13 => 0.0, 14 => 0.0,
            16 => 0.0,
        ];

        /** @var Document $d */
        foreach ($docs as $d) {
            $lp++;

            $eventDate = $d->getEventDate()?->format('Y-m-d') ?? '';
            $proofNo = (string)$d->getInvoiceNumber();
            $contractorName = $d->getContractor()?->getName() ?? '';
            $contractorAddr = $d->getContractor()?->getAddress() ?? '';
            $desc = $d->getDescription() ?? '';

            // Kwoty jak w Twoim widoku ledger (gross). Jak chcesz stricte netto -> zmień na getNetAmount().
            $amount = (float)$d->getGrossAmount();

            $c7 = 0.0; $c8 = 0.0; $c9 = 0.0;
            $c10 = 0.0; $c11 = 0.0;
            $c12 = 0.0; $c13 = 0.0; $c14 = 0.0;
            $c15 = '';  // opis B+R
            $c16 = 0.0; // wartość B+R
            $c17 = '';  // uwagi

            if ($d->getType() === 'INCOME') {
                $c7 = $amount;
                $c9 = $amount; // 7+8
            } elseif ($d->getType() === 'COST') {
                $c13 = $amount;
                $c14 = $amount; // 12+13
            }

            $sum[7] += $c7;  $sum[8] += $c8;  $sum[9] += $c9;
            $sum[10] += $c10; $sum[11] += $c11;
            $sum[12] += $c12; $sum[13] += $c13; $sum[14] += $c14;
            $sum[16] += $c16;

            $rowsHtml .= '<tr>'
                . '<td class="center">' . $lp . '</td>' // Lp.
                . '<td class="center">' . htmlspecialchars($eventDate) . '</td>' // (1)
                . '<td class="center">' . htmlspecialchars($proofNo) . '</td>' // (2)
                . '<td>' . htmlspecialchars($contractorName) . '</td>' // (4)
                . '<td>' . htmlspecialchars($contractorAddr) . '</td>' // (5)
                . '<td>' . htmlspecialchars($desc) . '</td>' // (6)
                . '<td class="num">' . $this->fmt($c7) . '</td>' // (7)
                . '<td class="num">' . $this->fmt($c8) . '</td>' // (8)
                . '<td class="num">' . $this->fmt($c9) . '</td>' // (9)
                . '<td class="num">' . $this->fmt($c10) . '</td>' // (10)
                . '<td class="num">' . $this->fmt($c11) . '</td>' // (11)
                . '<td class="num">' . $this->fmt($c12) . '</td>' // (12)
                . '<td class="num">' . $this->fmt($c13) . '</td>' // (13)
                . '<td class="num">' . $this->fmt($c14) . '</td>' // (14)
                . '<td>' . htmlspecialchars($c15) . '</td>' // (15) opis B+R
                . '<td class="num">' . $this->fmt($c16) . '</td>' // (16) wartość B+R
                . '<td>' . htmlspecialchars($c17) . '</td>' // (17) uwagi
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td class="center" colspan="17">Brak wpisów w wybranym okresie.</td></tr>';
        }

        // Dompdf lubi proste layouty (bez flex). Dlatego top = tabelka 2-kolumnowa.
        return '<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 12mm 8mm; }

    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.2px; color:#111; }

    .top { width:100%; border-collapse: collapse; margin-bottom: 6px; }
    .top td { vertical-align: top; }
    .title { font-size: 12px; font-weight: 700; margin:0; }
    .period { font-size: 12px; font-weight: 700; margin-top:2px; }

    .meta { font-size: 8.2px; line-height: 1.25; }

    table.kpir { width:100%; border-collapse:collapse; table-layout: fixed; }
    table.kpir th, table.kpir td {
      border:1px solid #bfc7d1;
      padding: 2px 3px;
      vertical-align: middle;
    }
    table.kpir thead th {
      background:#f3f6fb;
      text-align:center;
      font-weight:700;
    }

    .center { text-align:center; }
    .num { text-align:right; white-space:nowrap; }

    /* szerokości dobrane pod wzór (A4 poziomo) */
    .w-lp{width:18px}
    .w-date{width:60px}
    .w-proof{width:82px}
    .w-name{width:150px}
    .w-addr{width:130px}
    .w-desc{width:190px}
    .w-money{width:68px}
    .w-brdesc{width:92px}
    .w-brval{width:60px}
    .w-uw{width:26px}

    /* pionowy napis Uwagi */
    .vertical {
      writing-mode: vertical-rl;
      transform: rotate(180deg);
      letter-spacing: 0.08em;
    }

    /* wiersz z numeracją kolumn */
    .colno { background:#ffffff; font-weight: 600; color:#333; font-size: 7.6px; }

    tfoot td { font-weight:700; background:#f7f9fc; }
  </style>
</head>
<body>

  <table class="top">
    <tr>
      <td style="width:70%;">
        <div class="title">Podatkowa Księga Przychodów i Rozchodów</div>
        <div class="period">'.htmlspecialchars($periodTitle).'</div>
      </td>
      <td style="width:30%;" class="meta">
        <div><b>Firma</b>: '.htmlspecialchars($companyName ?: '—').'</div>
        <div><b>Adres</b>: '.htmlspecialchars($companyAddress ?: '—').'</div>
        <div><b>NIP</b>: '.htmlspecialchars($companyTaxId ?: '—').'</div>
      </td>
    </tr>
  </table>

  <table class="kpir">
    <thead>
      <tr>
        <th class="w-lp" rowspan="3">Lp.</th>
        <th class="w-date" rowspan="2">Data<br>zdarzenia<br>gospodar-<br>czego</th>
        <th class="w-proof" rowspan="2">Nr dowodu<br>księgowego</th>

        <th class="w-name" colspan="2">Kontrahent</th>

        <th class="w-desc" rowspan="2">Opis zdarzenia gospodar-<br>czego</th>

        <th class="w-money" colspan="3">Przychód</th>

        <th class="w-money" rowspan="2">Zakup towarów<br>handlowych i<br>materiałów wg cen<br>zakupu</th>

        <th class="w-money" rowspan="2">Koszty<br>uboczne<br>zakupu</th>

        <th class="w-money" colspan="3">Wydatki (koszty)</th>

        <th class="w-brdesc" colspan="2">Koszty działalności badawczo-<br>-rozwojowej, o których mowa<br>w art. 26e ustawy o podatku dochodowym</th>

        <th class="w-uw" rowspan="3"><div class="vertical">Uwagi</div></th>
      </tr>

      <tr>
        <th class="w-name">Imię i nazwisko<br>(Firma)</th>
        <th class="w-addr">Adres</th>

        <th class="w-money">Wartość sprzedanych<br>towarów i usług</th>
        <th class="w-money">Pozostałe<br>przychody</th>
        <th class="w-money">Razem<br>przychód<br>(7+8)</th>

        <th class="w-money">Wynagrodzenia w<br>gotówce i naturze</th>
        <th class="w-money">Pozostałe<br>wydatki</th>
        <th class="w-money">Razem<br>wydatki<br>(12+13)</th>

        <th class="w-brdesc">Opis kosztu</th>
        <th class="w-brval">Wartość</th>
      </tr>

      <tr class="colno">
        <th class="w-date">1</th>
        <th class="w-proof">2</th>
        <th class="w-name">4</th>
        <th class="w-addr">5</th>
        <th class="w-desc">6</th>
        <th class="w-money">7</th>
        <th class="w-money">8</th>
        <th class="w-money">9</th>
        <th class="w-money">10</th>
        <th class="w-money">11</th>
        <th class="w-money">12</th>
        <th class="w-money">13</th>
        <th class="w-money">14</th>
        <th class="w-brdesc">15</th>
        <th class="w-brval">16</th>
      </tr>
    </thead>

    <tbody>
      '.$rowsHtml.'
    </tbody>

    <tfoot>
      <tr>
        <td colspan="6" class="num">Razem</td>
        <td class="num">'.$this->fmt($sum[7]).'</td>
        <td class="num">'.$this->fmt($sum[8]).'</td>
        <td class="num">'.$this->fmt($sum[9]).'</td>
        <td class="num">'.$this->fmt($sum[10]).'</td>
        <td class="num">'.$this->fmt($sum[11]).'</td>
        <td class="num">'.$this->fmt($sum[12]).'</td>
        <td class="num">'.$this->fmt($sum[13]).'</td>
        <td class="num">'.$this->fmt($sum[14]).'</td>
        <td></td>
        <td class="num">'.$this->fmt($sum[16]).'</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

</body>
</html>';
    }

    private function fmt(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
