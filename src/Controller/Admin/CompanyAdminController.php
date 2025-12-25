<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/companies')]
class CompanyAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_companies_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        $activeParam = $request->query->get('active', null); // "true"/"false"/null
        $sort = (string) $request->query->get('sort', 'name'); // name|id|taxId|active
        $dir = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $repo = $this->em->getRepository(Company::class);
        $qb = $repo->createQueryBuilder('c');

        if ($q !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.taxId) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        if ($activeParam !== null) {
            $active = filter_var($activeParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $qb->andWhere('c.active = :active')->setParameter('active', $active);
            }
        }

        $sortMap = [
            'id' => 'c.id',
            'name' => 'c.name',
            'taxId' => 'c.taxId',
            'active' => 'c.active',
        ];
        $qb->orderBy($sortMap[$sort] ?? 'c.name', $dir);

        $companies = $qb->getQuery()->getResult();

        $data = array_map([$this, 'serializeCompany'], $companies);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'admin_companies_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $company = $this->em->getRepository(Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        return $this->json($this->serializeCompany($company));
    }

    #[Route('', name: 'admin_companies_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => 'Field "name" is required'], 400);
        }

        $company = new Company();
        $company->setName($name);
        $company->setTaxId(isset($payload['taxId']) ? (string)$payload['taxId'] : null);
        $company->setAddress(isset($payload['address']) ? (string)$payload['address'] : null);
        $company->setActive(array_key_exists('active', $payload) ? (bool)$payload['active'] : true);

        $this->em->persist($company);
        $this->em->flush();

        return $this->json($this->serializeCompany($company), 201);
    }

    #[Route('/{id}', name: 'admin_companies_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $company = $this->em->getRepository(Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Field "name" cannot be empty'], 400);
            }
            $company->setName($name);
        }
        if (array_key_exists('taxId', $payload)) {
            $company->setTaxId($payload['taxId'] !== null ? (string)$payload['taxId'] : null);
        }
        if (array_key_exists('address', $payload)) {
            $company->setAddress($payload['address'] !== null ? (string)$payload['address'] : null);
        }
        if (array_key_exists('active', $payload)) {
            $company->setActive((bool) $payload['active']);
        }

        $this->em->flush();

        return $this->json($this->serializeCompany($company));
    }

    #[Route('/{id}', name: 'admin_companies_delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $company = $this->em->getRepository(Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        $this->em->remove($company);
        $this->em->flush();

        return new Response(null, 204);
    }

    #[Route('/{id}/users', name: 'admin_companies_users', methods: ['GET'])]
    public function users(int $id): JsonResponse
    {
        $company = $this->em->getRepository(Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        $data = array_map(
            static fn (User $u) => [
                'id'        => $u->getId(),
                'email'     => $u->getEmail(),
                'firstName' => $u->getFirstName(),
                'lastName'  => $u->getLastName(),
                'role'      => $u->getRole(),
            ],
            $company->getUsers()->toArray()
        );

        return $this->json($data);
    }

    private function serializeCompany(Company $c): array
    {
        return [
            'id'      => $c->getId(),
            'name'    => $c->getName(),
            'taxId'   => $c->getTaxId(),
            'address' => $c->getAddress(),
            'active'  => $c->isActive(),
        ];
    }
}
