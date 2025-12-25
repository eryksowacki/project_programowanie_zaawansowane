<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/users')]
class UserAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('', name: 'admin_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $role = (string)($payload['role'] ?? 'EMPLOYEE');

        if ($email === '' || $password === '') {
            return $this->json(['message' => 'Email and password are required'], 400);
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['message' => 'User with this email already exists'], 409);
        }

        $companyId = $payload['companyId'] ?? null;
        $company = null;
        if ($companyId !== null) {
            $company = $this->em->getRepository(Company::class)->find((int)$companyId);
            if (!$company) {
                return $this->json(['message' => 'Company not found'], 404);
            }
        }

        $user = new User();
        $user->setEmail($email);

        // jeśli masz firstName/lastName w encji – ustawiamy je, jeśli nie masz: usuń te linie
        if (method_exists($user, 'setFirstName')) {
            $user->setFirstName($payload['firstName'] ?? null);
        }
        if (method_exists($user, 'setLastName')) {
            $user->setLastName($payload['lastName'] ?? null);
        }

        // jeśli masz setRole w encji – zostaw, jeśli nie masz, dopasuj do swojej implementacji
        if (method_exists($user, 'setRole')) {
            $user->setRole($role);
        }

        if (method_exists($user, 'setCompany')) {
            $user->setCompany($company);
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'role'      => method_exists($user, 'getRole') ? $user->getRole() : null,
            'companyId' => $user->getCompany()?->getId(),
        ], 201);
    }

    #[Route('/{id}/full', name: 'admin_users_update_full', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('email', $payload)) {
            $user->setEmail(trim((string)$payload['email']));
        }

        if (array_key_exists('firstName', $payload) && method_exists($user, 'setFirstName')) {
            $user->setFirstName($payload['firstName']);
        }

        if (array_key_exists('lastName', $payload) && method_exists($user, 'setLastName')) {
            $user->setLastName($payload['lastName']);
        }

        if (array_key_exists('role', $payload) && method_exists($user, 'setRole')) {
            $user->setRole((string)$payload['role']);
        }

        if (array_key_exists('companyId', $payload) && method_exists($user, 'setCompany')) {
            $companyId = $payload['companyId'];
            $company = null;

            if ($companyId !== null) {
                $company = $this->em->getRepository(Company::class)->find((int)$companyId);
                if (!$company) {
                    return $this->json(['message' => 'Company not found'], 404);
                }
            }

            $user->setCompany($company);
        }

        if (array_key_exists('password', $payload) && $payload['password']) {
            $user->setPassword($this->hasher->hashPassword($user, (string)$payload['password']));
        }

        $this->em->flush();

        return $this->json(['id' => $user->getId()]);
    }

    #[Route('/{id}', name: 'admin_users_patch', methods: ['PATCH'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('role', $payload) && method_exists($user, 'setRole')) {
            $user->setRole((string)$payload['role']);
        }
        if (array_key_exists('firstName', $payload) && method_exists($user, 'setFirstName')) {
            $user->setFirstName($payload['firstName']);
        }
        if (array_key_exists('lastName', $payload) && method_exists($user, 'setLastName')) {
            $user->setLastName($payload['lastName']);
        }

        $this->em->flush();

        return $this->json(['id' => $user->getId()]);
    }

    #[Route('/{id}', name: 'admin_users_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(null, 204);
    }
}
