<?php

namespace App\Report\Xlsx;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Report\Contracts\ReportCommandInterface;
use App\Report\Contracts\ReportFactoryInterface;
use App\Report\Contracts\ReportFile;

final class GenerateContractorsXlsxCommand implements ReportCommandInterface
{
    public function __construct(
        private readonly DocumentRepository $docRepo,
        private readonly ReportFactoryInterface $factory
    ) {}

    public function run(User $user, array $payload): ReportFile
    {
        $company = $user->getCompany();
        if (!$company) {
            throw new \InvalidArgumentException('User has no company assigned');
        }

        $dateFrom = (string)($payload['dateFrom'] ?? '');
        $dateTo = (string)($payload['dateTo'] ?? '');
        $includeIncome = (bool)($payload['includeIncome'] ?? true);
        $includeCost = (bool)($payload['includeCost'] ?? true);

        $contractorId = array_key_exists('contractorId', $payload) ? $payload['contractorId'] : null;
        if ($contractorId !== null && $contractorId !== '') {
            $contractorId = (int)$contractorId;
        } else {
            $contractorId = null;
        }

        if (!$includeIncome && !$includeCost) {
            throw new \InvalidArgumentException('At least one of includeIncome/includeCost must be true');
        }

        try {
            $from = new \DateTimeImmutable($dateFrom);
            $to = new \DateTimeImmutable($dateTo);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid dateFrom/dateTo. Use YYYY-MM-DD');
        }

        $toExclusive = $to->modify('+1 day');

        $types = [];
        if ($includeIncome) $types[] = 'INCOME';
        if ($includeCost) $types[] = 'COST';

        $qb = $this->docRepo->createQueryBuilder('d')
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

            $typeLabel =
                $d->getType() === 'INCOME' ? 'Przychód' :
                ($d->getType() === 'COST' ? 'Koszt' : (string)$d->getType());

            $key = $conName . '|' . $conTaxId . '|' . $typeLabel;

            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'contractor' => $conName,
                    'taxId' => $conTaxId,
                    'type' => $typeLabel,
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

        $builder = $this->factory->createContractorsXlsxBuilder();
        $spreadsheet = $builder->build(
            companyName: (string)$company->getName(),
            periodLabel: $from->format('Y-m-d') . ' do ' . $to->format('Y-m-d'),
            aggRows: array_values($agg)
        );

        $filename = sprintf('raport-kontrahenci-%s_%s.xlsx', $from->format('Y-m-d'), $to->format('Y-m-d'));

        return $this->factory->createXlsxRenderer()->renderSpreadsheet($spreadsheet, $filename);
    }
}