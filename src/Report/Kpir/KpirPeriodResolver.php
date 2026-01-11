<?php

namespace App\Report\Kpir;

final class KpirPeriodResolver
{
    /**
     * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable,2:string}
     */
    public function resolve(string $mode, array $payload, int $year): array
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

        if ($mode === 'year') {
            $from = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
            $toExclusive = $from->modify('+1 year');
            $title = sprintf('Rok %d', $year);
            return [$from, $toExclusive, $title];
        }

        throw new \InvalidArgumentException('Invalid mode. Allowed: month|quarter|year');
    }

    private function monthNamePL(int $month): string
    {
        return [
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
            5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
            9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        ][$month] ?? 'Miesiąc';
    }
}