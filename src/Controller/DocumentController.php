<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ContractorRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DocumentController extends AbstractController
{
    #[Route('/api/documents', name: 'api_documents_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] ?User $user,
        Request $request,
        DocumentRepository $repo
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        $company = $user->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company assigned'], 400);
        }

        $type   = $request->query->get('type');   // INCOME / COST
        $status = $request->query->get('status'); // BUFFER / BOOKED

        $qb = $repo->createQueryBuilder('d')
            ->andWhere('d.company = :company')
            ->setParameter('company', $company)
            ->orderBy('d.eventDate', 'ASC')
            ->addOrderBy('d.id', 'ASC');

        if ($type) {
            $qb->andWhere('d.type = :type')->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('d.status = :status')->setParameter('status', $status);
        }

        $docs = $qb->getQuery()->getResult();

        $data = array_map(function (Document $d) {
            return [
                'id'            => $d->getId(),
                'invoiceNumber' => $d->getInvoiceNumber(),
                'type'          => $d->getType(),
                'issueDate'     => $d->getIssueDate()?->format('Y-m-d'),
                'eventDate'     => $d->getEventDate()?->format('Y-m-d'),
                'description'   => $d->getDescription(),
                'netAmount'     => (float) $d->getNetAmount(),
                'vatAmount'     => (float) $d->getVatAmount(),
                'grossAmount'   => (float) $d->getGrossAmount(),
                'status'        => $d->getStatus(),
                'ledgerNumber'  => $d->getLedgerNumber(),
                'categoryId'    => $d->getCategory()?->getId(),
                'contractorId'  => $d->getContractor()?->getId(),
            ];
        }, $docs);

        return $this->json($data);
    }

    #[Route('/api/documents', name: 'api_documents_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] ?User $user,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo,
        ContractorRepository $contractorRepo
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        $company = $user->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company assigned'], 400);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $invoiceNumber = null;
        if (array_key_exists('invoiceNumber', $payload)) {
            if ($payload['invoiceNumber'] === null) {
                $invoiceNumber = null;
            } elseif (!is_string($payload['invoiceNumber'])) {
                return $this->json(['message' => 'invoiceNumber must be a string'], 400);
            } else {
                $invoiceNumber = trim($payload['invoiceNumber']);
                $invoiceNumber = $invoiceNumber === '' ? null : $invoiceNumber;
            }
        }

        foreach (['type', 'issueDate', 'eventDate', 'netAmount', 'vatAmount', 'grossAmount'] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $this->json(['message' => "Missing field: $field"], 400);
            }
        }

        try {
            $issueDate = new \DateTimeImmutable($payload['issueDate']);
            $eventDate = new \DateTimeImmutable($payload['eventDate']);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
        }

        $doc = new Document();
        $doc
            ->setInvoiceNumber($invoiceNumber)
            ->setType((string) $payload['type'])
            ->setIssueDate($issueDate)
            ->setEventDate($eventDate)
            ->setDescription($payload['description'] ?? null)
            ->setNetAmount(number_format((float) $payload['netAmount'], 2, '.', ''))
            ->setVatAmount(number_format((float) $payload['vatAmount'], 2, '.', ''))
            ->setGrossAmount(number_format((float) $payload['grossAmount'], 2, '.', ''))
            ->setStatus('BUFFER')
            ->setLedgerNumber(null)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setCompany($company)
            ->setCreatedBy($user);

        if (!empty($payload['categoryId'])) {
            $category = $categoryRepo->find((int) $payload['categoryId']);
                $doc->setCategory($category);
        }

        if (!empty($payload['contractorId'])) {
            $contractor = $contractorRepo->find((int) $payload['contractorId']);
            if ($contractor && $contractor->getCompany()?->getId() === $company->getId()) {
                $doc->setContractor($contractor);
            } else {
                return $this->json(['message' => 'Invalid contractorId (not in your company)'], 400);
            }
        }

        $em->persist($doc);
        $em->flush();

        return $this->json(['id' => $doc->getId()], 201);
    }

    #[Route('/api/documents/{id}/book', name: 'api_documents_book', methods: ['POST'])]
    public function book(
        #[CurrentUser] ?User $user,
        int $id,
        DocumentRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $company = $user->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company assigned'], 400);
        }

        $doc = $repo->find($id);
        if (!$doc) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($doc->getCompany()?->getId() !== $company->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($doc->getStatus() === 'BOOKED') {
            return $this->json(['error' => 'Already booked'], 400);
        }

        $max = $repo->createQueryBuilder('d')
            ->select('MAX(d.ledgerNumber) as maxNr')
            ->andWhere('d.company = :company')
            ->setParameter('company', $company)
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = ((int) $max) + 1;

        $doc
            ->setStatus('BOOKED')
            ->setLedgerNumber($nextNumber)
            ->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['ledgerNumber' => $nextNumber]);
    }

    #[Route('/api/ledger', name: 'api_ledger', methods: ['GET'])]
    public function ledger(
        #[CurrentUser] ?User $user,
        DocumentRepository $repo
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        $company = $user->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company assigned'], 400);
        }

        $docs = $repo->createQueryBuilder('d')
            ->andWhere('d.company = :company')
            ->andWhere('d.status = :status')
            ->setParameter('company', $company)
            ->setParameter('status', 'BOOKED')
            ->orderBy('d.eventDate', 'ASC')
            ->addOrderBy('d.ledgerNumber', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(function (Document $d) {
            return [
                'ledgerNumber'  => $d->getLedgerNumber(),
                'eventDate'     => $d->getEventDate()?->format('Y-m-d'),
                'description'   => $d->getDescription(),
                'type'          => $d->getType(),
                'netAmount'     => (float) $d->getNetAmount(),
                'grossAmount'   => (float) $d->getGrossAmount(),
            ];
        }, $docs);

        return $this->json($data);
    }

    private function denyUnlessEmployeeOrManager(): void
    {
        if ($this->isGranted('ROLE_SYSTEM_ADMIN')) {
            throw $this->createAccessDeniedException('Admins are not allowed to access documents');
        }

        if (!$this->isGranted('ROLE_EMPLOYEE') && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Only employees/managers can access documents');
        }
    }
}
