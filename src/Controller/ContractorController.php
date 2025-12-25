<?php

namespace App\Controller;

use App\Entity\Contractor;
use App\Entity\User;
use App\Repository\ContractorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ContractorController extends AbstractController
{
    #[Route('/api/contractors', name: 'api_contractors_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] ?User $user,
        ContractorRepository $repo
    ): JsonResponse {
        $this->denyUnlessEmployeeOrManager();

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        /** @var Contractor[] $contractors */
        $contractors = $repo->findBy(['company' => $company], ['name' => 'ASC']);

        $data = array_map(static function (Contractor $c) {
            return [
                'id'        => $c->getId(),
                'name'      => $c->getName(),
                'taxId'     => $c->getTaxId(),
                'address'   => $c->getAddress(),
                'companyId' => $c->getCompany()?->getId(),
            ];
        }, $contractors);

        return $this->json($data);
    }

    #[Route('/api/contractors', name: 'api_contractors_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] ?User $user,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => 'Name is required'], 400);
        }

        $taxId = array_key_exists('taxId', $payload) && $payload['taxId'] !== null
            ? trim((string)$payload['taxId'])
            : null;

        $address = array_key_exists('address', $payload) && $payload['address'] !== null
            ? (string)$payload['address']
            : null;

        $c = new Contractor();
        $c->setName($name);
        $c->setTaxId($taxId ?: null);
        $c->setAddress($address);
        $c->setCompany($company);

        $em->persist($c);
        $em->flush();

        return $this->json(['id' => $c->getId()], 201);
    }

    #[Route('/api/contractors/{id}', name: 'api_contractors_update', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] ?User $user,
        int $id,
        Request $request,
        ContractorRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        $con = $repo->find($id);
        if (!$con) {
            return $this->json(['message' => 'Not found'], 404);
        }

        if ($con->getCompany()?->getId() !== $company->getId()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Invalid name'], 400);
            }
            $con->setName($name);
        }

        if (array_key_exists('taxId', $payload)) {
            $taxId = $payload['taxId'] !== null ? trim((string)$payload['taxId']) : null;
            $con->setTaxId($taxId ?: null);
        }

        if (array_key_exists('address', $payload)) {
            $con->setAddress($payload['address'] !== null ? (string)$payload['address'] : null);
        }

        $em->flush();

        return $this->json(['id' => $con->getId()]);
    }

    #[Route('/api/contractors/{id}', name: 'api_contractors_delete', methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] ?User $user,
        int $id,
        ContractorRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        $con = $repo->find($id);
        if (!$con) {
            return $this->json(['message' => 'Not found'], 404);
        }

        if ($con->getCompany()?->getId() !== $company->getId()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $em->remove($con);
        $em->flush();

        return $this->json(null, 204);
    }

    private function denyUnlessEmployeeOrManager(): void
    {
        if (!$this->isGranted('ROLE_EMPLOYEE') && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Forbidden');
        }
    }
}
