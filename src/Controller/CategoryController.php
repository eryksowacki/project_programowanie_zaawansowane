<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Document;
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
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $type = $request->query->get('type'); // INCOME / COST

        $qb = $repo->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');

        if ($type) {
            $t = strtoupper((string) $type);
            if (!in_array($t, ['INCOME', 'COST'], true)) {
                return $this->json(['message' => 'Invalid type'], 400);
            }
            $qb->andWhere('c.type = :type')->setParameter('type', $t);
        }

        /** @var Category[] $categories */
        $categories = $qb->getQuery()->getResult();

        $data = array_map(static function (Category $c) {
            return [
                'id'   => $c->getId(),
                'name' => $c->getName(),
                'type' => $c->getType(),
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
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessAdmin();

        $payload = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($payload['name'] ?? ''));
        $type = strtoupper(trim((string) ($payload['type'] ?? '')));

        if ($name === '') {
            return $this->json(['message' => 'Invalid name'], 400);
        }
        if (!in_array($type, ['INCOME', 'COST'], true)) {
            return $this->json(['message' => 'Invalid type'], 400);
        }

        $c = new Category();
        $c->setName($name);
        $c->setType($type);

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
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessAdmin();

        $cat = $repo->find($id);
        if (!$cat) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Invalid name'], 400);
            }
            $cat->setName($name);
        }

        if (array_key_exists('type', $payload)) {
            $type = strtoupper(trim((string) $payload['type']));
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
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessAdmin();

        $cat = $repo->find($id);
        if (!$cat) {
            return $this->json(['message' => 'Not found'], 404);
        }

        $usedInDocs = (int) $em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->andWhere('d.category = :cat')
            ->setParameter('cat', $cat)
            ->getQuery()
            ->getSingleScalarResult();

        if ($usedInDocs > 0) {
            return $this->json([
                'message' => "The category cannot be deleted because it is used in documents. (count: {$usedInDocs})",
                'code' => 'CATEGORY_IN_USE',
                'usedInDocuments' => $usedInDocs,
            ], 409);
        }

        $em->remove($cat);
        $em->flush();

        return $this->json(null, 204);
    }

    private function denyUnlessAdmin(): void
    {
        if (!$this->isGranted('ROLE_SYSTEM_ADMIN')) {
            throw $this->createAccessDeniedException('Only administrators can modify categories');
        }
    }
}
