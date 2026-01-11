<?php

namespace App\Report\Kpir;

use App\Entity\Document;
use App\Report\Contracts\KpirHtmlBuilderInterface;

final class KpirHtmlBuilder implements KpirHtmlBuilderInterface
{
    private string $companyName = '—';
    private string $companyAddress = '—';
    private string $companyTaxId = '—';
    private string $periodTitle = '—';

    /** @var Document[] */
    private array $docs = [];

    public function withCompany(string $name, string $address, string $taxId): self
    {
        $this->companyName = $name ?: '—';
        $this->companyAddress = $address ?: '—';
        $this->companyTaxId = $taxId ?: '—';
        return $this;
    }

    public function withPeriodTitle(string $periodTitle): self
    {
        $this->periodTitle = $periodTitle ?: '—';
        return $this;
    }

    public function withDocs(array $docs): self
    {
        $this->docs = $docs;
        return $this;
    }

    public function build(): string
    {
        return $this->renderHtml();
    }

    private function fmt(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    private function renderHtml(): string
    {
        $rowsHtml = '';
        $lp = 0;

        // sumy tylko dla kolumn kwotowych 7..14 i 16
        $sum = [
            7 => 0.0, 8 => 0.0, 9 => 0.0,
            10 => 0.0, 11 => 0.0,
            12 => 0.0, 13 => 0.0, 14 => 0.0,
            16 => 0.0,
        ];

        foreach ($this->docs as $d) {
            $lp++;

            $eventDate = $d->getEventDate()?->format('Y-m-d') ?? '';
            $proofNo = (string)$d->getInvoiceNumber();
            $contractorName = $d->getContractor()?->getName() ?? '';
            $contractorAddr = $d->getContractor()?->getAddress() ?? '';
            $desc = $d->getDescription() ?? '';

            // UWAGA: jak w Twoim ledger (gross). Jeśli chcesz netto -> getNetAmount()
            $amount = (float)$d->getGrossAmount();

            $c7 = 0.0; $c8 = 0.0; $c9 = 0.0;
            $c10 = 0.0; $c11 = 0.0;
            $c12 = 0.0; $c13 = 0.0; $c14 = 0.0;
            $c15 = '';
            $c16 = 0.0;
            $c17 = '';

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
                . '<td class="center">' . $lp . '</td>'
                . '<td class="center">' . htmlspecialchars($eventDate) . '</td>'
                . '<td class="center">' . htmlspecialchars($proofNo) . '</td>'
                . '<td>' . htmlspecialchars($contractorName) . '</td>'
                . '<td>' . htmlspecialchars($contractorAddr) . '</td>'
                . '<td>' . htmlspecialchars($desc) . '</td>'
                . '<td class="num">' . $this->fmt($c7) . '</td>'
                . '<td class="num">' . $this->fmt($c8) . '</td>'
                . '<td class="num">' . $this->fmt($c9) . '</td>'
                . '<td class="num">' . $this->fmt($c10) . '</td>'
                . '<td class="num">' . $this->fmt($c11) . '</td>'
                . '<td class="num">' . $this->fmt($c12) . '</td>'
                . '<td class="num">' . $this->fmt($c13) . '</td>'
                . '<td class="num">' . $this->fmt($c14) . '</td>'
                . '<td>' . htmlspecialchars($c15) . '</td>'
                . '<td class="num">' . $this->fmt($c16) . '</td>'
                . '<td>' . htmlspecialchars($c17) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td class="center" colspan="17">Brak wpisów w wybranym okresie.</td></tr>';
        }

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

    .vertical { writing-mode: vertical-rl; letter-spacing: 0.08em; }
    .colno { background:#ffffff; font-weight: 600; color:#333; font-size: 7.6px; }

    tfoot td { font-weight:700; background:#f7f9fc; }
  </style>
</head>
<body>

  <table class="top">
    <tr>
      <td style="width:70%;">
        <div class="title">Podatkowa Księga Przychodów i Rozchodów</div>
        <div class="period">'.htmlspecialchars($this->periodTitle).'</div>
      </td>
      <td style="width:30%;" class="meta">
        <div><b>Firma</b>: '.htmlspecialchars($this->companyName).'</div>
        <div><b>Adres</b>: '.htmlspecialchars($this->companyAddress).'</div>
        <div><b>NIP</b>: '.htmlspecialchars($this->companyTaxId).'</div>
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
}