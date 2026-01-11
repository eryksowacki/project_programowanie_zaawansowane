<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\Role;
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
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    /**
     * POST /api/admin/users
     */
    #[Route('', name: 'admin_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SYSTEM_ADMIN');

        $payload = json_decode($request->getContent(), true) ?? [];

        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $roleInput = (string)($payload['role'] ?? 'ROLE_EMPLOYEE');

        if ($email === '' || $password === '') {
            return $this->json(['message' => 'Email and password are required'], 400);
        }

        $pwdError = $this->validatePassword($password);
        if ($pwdError) {
            return $this->json(['message' => $pwdError], 422);
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

        $role = $this->resolveRoleEntity($roleInput);
        if (!$role) {
            return $this->json(['message' => 'Invalid role'], 400);
        }

        $user = new User();
        $user->setEmail($email);

        // Pola opcjonalne: ustawiamy tylko jeśli encja ma settery (tak jak miałeś)
        if (method_exists($user, 'setFirstName')) {
            $user->setFirstName($payload['firstName'] ?? null);
        }
        if (method_exists($user, 'setLastName')) {
            $user->setLastName($payload['lastName'] ?? null);
        }

        $user->setRole($role);

        if (method_exists($user, 'setCompany')) {
            $user->setCompany($company);
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        // roles: w laravel miałeś authRoles(); w symfony zwykle jest getRoles()
        $roles = method_exists($user, 'authRoles') ? $user->authRoles() : $user->getRoles();

        return $this->json([
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'role'      => $user->getRole()?->getCode(),
            'roles'     => $roles,
            'companyId' => method_exists($user, 'getCompany') ? $user->getCompany()?->getId() : null,
        ], 201);
    }

    /**
     * PUT /api/admin/users/{id}/full
     */
    #[Route('/{id}/full', name: 'admin_users_update_full', methods: ['PUT'])]
    public function updateFull(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SYSTEM_ADMIN');

        /** @var User|null $user */
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

        if (array_key_exists('role', $payload)) {
            $role = $this->resolveRoleEntity((string)$payload['role']);
            if (!$role) {
                return $this->json(['message' => 'Invalid role'], 400);
            }
            $user->setRole($role);
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

        // Laravel: waliduje i dopiero wtedy hashuje
        if (array_key_exists('password', $payload) && $payload['password']) {
            $newPwd = (string)$payload['password'];

            $pwdError = $this->validatePassword($newPwd);
            if ($pwdError) {
                return $this->json(['message' => $pwdError], 422);
            }

            $user->setPassword($this->hasher->hashPassword($user, $newPwd));
        }

        $this->em->flush();

        $roles = method_exists($user, 'authRoles') ? $user->authRoles() : $user->getRoles();

        return $this->json([
            'id'    => $user->getId(),
            'role'  => $user->getRole()?->getCode(),
            'roles' => $roles,
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}
     */
    #[Route('/{id}', name: 'admin_users_patch', methods: ['PATCH'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SYSTEM_ADMIN');

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('role', $payload)) {
            $role = $this->resolveRoleEntity((string)$payload['role']);
            if (!$role) {
                return $this->json(['message' => 'Invalid role'], 400);
            }
            $user->setRole($role);
        }

        if (array_key_exists('firstName', $payload) && method_exists($user, 'setFirstName')) {
            $user->setFirstName($payload['firstName']);
        }
        if (array_key_exists('lastName', $payload) && method_exists($user, 'setLastName')) {
            $user->setLastName($payload['lastName']);
        }

        $this->em->flush();

        $roles = method_exists($user, 'authRoles') ? $user->authRoles() : $user->getRoles();

        return $this->json([
            'id'    => $user->getId(),
            'role'  => $user->getRole()?->getCode(),
            'roles' => $roles,
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     */
    #[Route('/{id}', name: 'admin_users_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SYSTEM_ADMIN');

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function resolveRoleEntity(string $input): ?Role
    {
        $code = strtoupper(trim($input));

        $map = [
            'SYSTEM_ADMIN'      => 'ROLE_SYSTEM_ADMIN',
            'ROLE_SYSTEM_ADMIN' => 'ROLE_SYSTEM_ADMIN',
            'ADMIN'             => 'ROLE_SYSTEM_ADMIN',

            'MANAGER'      => 'ROLE_MANAGER',
            'ROLE_MANAGER' => 'ROLE_MANAGER',

            'EMPLOYEE'      => 'ROLE_EMPLOYEE',
            'ROLE_EMPLOYEE' => 'ROLE_EMPLOYEE',
        ];

        $code = $map[$code] ?? $code;

        return $this->em->getRepository(Role::class)->findOneBy(['code' => $code]);
    }

    /**
     * Wymagania:
     * - min 8 znaków
     * - min 1 mała
     * - min 1 wielka
     * - min 1 cyfra
     * - min 1 znak specjalny
     */
    private function validatePassword(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/\d/', $password)) {
            return 'Password must contain at least one digit.';
        }
        // "special" = wszystko co nie jest literą ani cyfrą (działa też na polskie znaki)
        if (!preg_match('/[^\p{L}\p{N}]/u', $password)) {
            return 'Password must contain at least one special character.';
        }

        return null;
    }
}