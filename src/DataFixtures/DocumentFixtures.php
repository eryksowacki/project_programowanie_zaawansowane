<?php

namespace App\DataFixtures;

use App\Entity\Document;
use App\Repository\CategoryRepository;
use App\Entity\Company;
use App\Repository\ContractorRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class DocumentFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ContractorRepository $contractorRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        /** @var Company $company */
        $company = $this->getReference(CompanyFixtures::COMPANY_DEMO, Company::class);

        $incomeCategory = $this->categoryRepository->findOneBy([
            'company' => $company,
            'type' => 'INCOME',
        ]);
        $costCategory = $this->categoryRepository->findOneBy([
            'company' => $company,
            'type' => 'COST',
        ]);

        $contractors = $this->contractorRepository->findBy(['company' => $company]);

        $now = new \DateTimeImmutable();

        // Dokument PRZYCHÓD zaksięgowany
        $d1 = new Document();
        $d1
            ->setType('INCOME')
            ->setIssueDate($now->modify('-5 days'))
            ->setEventDate($now->modify('-4 days'))
            ->setDescription('Sprzedaż usług')
            ->setNetAmount('1000.00')
            ->setVatAmount('230.00')
            ->setGrossAmount('1230.00')
            ->setStatus('BOOKED')
            ->setLedgerNumber(1)
            ->setCreatedAt($now->modify('-5 days'))
            ->setUpdatedAt($now->modify('-4 days'))
            ->setCompany($company)
            ->setCategory($incomeCategory)
            ->setContractor($contractors[0] ?? null);
        $manager->persist($d1);

        // Dokument KOSZT w buforze
        $d2 = new Document();
        $d2
            ->setType('COST')
            ->setIssueDate($now->modify('-2 days'))
            ->setEventDate($now->modify('-1 day'))
            ->setDescription('Zakup materiałów')
            ->setNetAmount('500.00')
            ->setVatAmount('115.00')
            ->setGrossAmount('615.00')
            ->setStatus('BUFFER')
            ->setCreatedAt($now->modify('-2 days'))
            ->setUpdatedAt($now->modify('-1 day'))
            ->setCompany($company)
            ->setCategory($costCategory)
            ->setContractor($contractors[1] ?? null);
        $manager->persist($d2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
            CategoryFixtures::class,
            ContractorFixtures::class,
        ];
    }
}
