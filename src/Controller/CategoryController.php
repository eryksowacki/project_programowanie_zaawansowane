<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class CategoryController extends AbstractController
{
    #[Route('/api/categories', name: 'api_categories_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] ?User $user,
        Request $request,
        CategoryRepository $repo
    ): JsonResponse {
        $this->denyUnlessEmployeeOrManager();

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        $type = $request->query->get('type'); // INCOME / COST (opcjonalnie)

        $qb = $repo->createQueryBuilder('c')
            ->andWhere('c.company = :company')
            ->setParameter('company', $company)
            ->orderBy('c.name', 'ASC');

        if ($type) {
            $qb->andWhere('c.type = :type')->setParameter('type', strtoupper((string)$type));
        }

        /** @var Category[] $categories */
        $categories = $qb->getQuery()->getResult();

        $data = array_map(static function (Category $c) {
            return [
                'id'        => $c->getId(),
                'name'      => $c->getName(),
                'type'      => $c->getType(),
                'companyId' => $c->getCompany()?->getId(),
            ];
        }, $categories);

        return $this->json($data);
    }

    #[Route('/api/categories', name: 'api_categories_create', methods: ['POST'])]
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
        $type = strtoupper(trim((string)($payload['type'] ?? '')));

        if ($name === '' || !in_array($type, ['INCOME', 'COST'], true)) {
            return $this->json(['message' => 'Invalid name/type'], 400);
        }

        $c = new Category();
        $c->setName($name);
        $c->setType($type);
        $c->setCompany($company);

        $em->persist($c);
        $em->flush();

        return $this->json(['id' => $c->getId()], 201);
    }

    #[Route('/api/categories/{id}', name: 'api_categories_update', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] ?User $user,
        int $id,
        Request $request,
        CategoryRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        $cat = $repo->find($id);
        if (!$cat) {
            return $this->json(['message' => 'Not found'], 404);
        }

        if ($cat->getCompany()?->getId() !== $company->getId()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Invalid name'], 400);
            }
            $cat->setName($name);
        }

        if (array_key_exists('type', $payload)) {
            $type = strtoupper(trim((string)$payload['type']));
            if (!in_array($type, ['INCOME', 'COST'], true)) {
                return $this->json(['message' => 'Invalid type'], 400);
            }
            $cat->setType($type);
        }

        $em->flush();

        return $this->json(['id' => $cat->getId()]);
    }

    #[Route('/api/categories/{id}', name: 'api_categories_delete', methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] ?User $user,
        int $id,
        CategoryRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $company = $user?->getCompany();
        if (!$company) {
            return $this->json(['message' => 'User has no company'], 400);
        }

        $cat = $repo->find($id);
        if (!$cat) {
            return $this->json(['message' => 'Not found'], 404);
        }

        if ($cat->getCompany()?->getId() !== $company->getId()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $em->remove($cat);
        $em->flush();

        return $this->json(null, 204);
    }

    private function denyUnlessEmployeeOrManager(): void
    {
        // OR: EMPLOYEE albo MANAGER (bez adminów)
        if (!$this->isGranted('ROLE_EMPLOYEE') && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Forbidden');
        }
    }
}
