<?php

namespace App\Report\Kpir;

use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Report\Contracts\ReportCommandInterface;
use App\Report\Contracts\ReportFactoryInterface;
use App\Report\Contracts\ReportFile;

final class GenerateKpirPdfCommand implements ReportCommandInterface
{
    public function __construct(
        private readonly DocumentRepository $docRepo,
        private readonly KpirPeriodResolver $periodResolver,
        private readonly ReportFactoryInterface $factory
    ) {}

    /** @param array<string,mixed> $payload */
    public function run(User $user, array $payload): ReportFile
    {
        $company = $user->getCompany();
        if (!$company) {
            throw new \InvalidArgumentException('User has no company assigned');
        }

        $mode = (string)($payload['mode'] ?? 'month'); // month|quarter|year
        $year = (int)($payload['year'] ?? (int)date('Y'));

        [$from, $toExclusive, $periodTitle] = $this->periodResolver->resolve($mode, $payload, $year);

        $docs = $this->docRepo->createQueryBuilder('d')
            ->leftJoin('d.contractor', 'c')->addSelect('c')
            ->andWhere('d.company = :company')
            ->andWhere('d.status = :status')
            ->andWhere('d.eventDate >= :from AND d.eventDate < :to')
            ->setParameter('company', $company)
            ->setParameter('status', 'BOOKED')
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->orderBy('d.eventDate', 'ASC')
            ->addOrderBy('d.ledgerNumber', 'ASC')
            ->getQuery()
            ->getResult();

        $builder = $this->factory->createKpirHtmlBuilder();

        $html = $builder
            ->withCompany(
                name: (string)$company->getName(),
                address: (string)$company->getAddress(),
                taxId: (string)$company->getTaxId()
            )
            ->withPeriodTitle($periodTitle)
            ->withDocs($docs)
            ->build();

        $filename = match ($mode) {
            'month' => sprintf('kpir-%04d-%02d.pdf', $year, (int)($payload['month'] ?? 1)),
            'quarter' => sprintf('kpir-%04d-Q%d.pdf', $year, (int)($payload['quarter'] ?? 1)),
            'year' => sprintf('kpir-%04d.pdf', $year),
            default => sprintf('kpir-%s.pdf', $from->format('Y-m-d')),
        };

        return $this->factory->createPdfRenderer()->renderHtmlToPdf($html, $filename);
    }
}