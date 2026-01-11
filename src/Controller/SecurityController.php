<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['message' => 'Missing credentials'], 401);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['message' => 'Unauthenticated'], 401);
        }

        return $this->json($this->serializeUser($user));
    }

    /**
     * Odpowiednik AuthController@updateMe z Laravel (bez validatora)
     */
    #[Route('/api/me', name: 'api_me_update', methods: ['PUT', 'PATCH'])]
    public function updateMe(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        if (null === $user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $email = trim((string)($payload['email'] ?? ''));
        $roleInput = (string)($payload['role'] ?? '');

        if ($email === '' || $roleInput === '') {
            return $this->json(['message' => 'Email and role are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Invalid email'], 422);
        }

        // opcjonalnie: ogranicz długości jak w Laravel
        if (mb_strlen($email) > 255) {
            return $this->json(['message' => 'Email is too long (max 255)'], 422);
        }

        // Unikalność emaila (jak Rule::unique()->ignore($user->id))
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing && $existing->getId() !== $user->getId()) {
            return $this->json(['message' => 'User with this email already exists'], 409);
        }

        // Rola
        $role = $this->resolveRoleEntity($roleInput);
        if (!$role) {
            return $this->json(['message' => 'Invalid role'], 400);
        }

        // firstName / lastName (opcjonalne)
        if (array_key_exists('firstName', $payload)) {
            $firstName = $payload['firstName'];
            if ($firstName !== null && !is_string($firstName)) {
                return $this->json(['message' => 'firstName must be a string or null'], 422);
            }
            if (is_string($firstName) && mb_strlen($firstName) > 255) {
                return $this->json(['message' => 'firstName is too long (max 255)'], 422);
            }
            if (method_exists($user, 'setFirstName')) {
                $user->setFirstName($firstName);
            }
        }

        if (array_key_exists('lastName', $payload)) {
            $lastName = $payload['lastName'];
            if ($lastName !== null && !is_string($lastName)) {
                return $this->json(['message' => 'lastName must be a string or null'], 422);
            }
            if (is_string($lastName) && mb_strlen($lastName) > 255) {
                return $this->json(['message' => 'lastName is too long (max 255)'], 422);
            }
            if (method_exists($user, 'setLastName')) {
                $user->setLastName($lastName);
            }
        }

        $user->setEmail($email);

        if (method_exists($user, 'setRole')) {
            $user->setRole($role);
        }

        // Hasło (opcjonalne) – Laravel miał min:6
        if (array_key_exists('password', $payload) && $payload['password']) {
            $pwd = $payload['password'];

            if (!is_string($pwd)) {
                return $this->json(['message' => 'password must be a string'], 422);
            }
            if (mb_strlen($pwd) < 6) {
                return $this->json(['message' => 'Password must be at least 6 characters long.'], 422);
            }

            $user->setPassword($this->hasher->hashPassword($user, $pwd));
        }

        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),

            'firstName' => method_exists($user, 'getFirstName') ? $user->getFirstName() : null,
            'lastName'  => method_exists($user, 'getLastName') ? $user->getLastName() : null,

            'role'      => $user->getRole()?->getCode(),
            'roles'     => $user->getRoles(),

            'companyId' => $user->getCompany()?->getId(),
        ];
    }

    private function resolveRoleEntity(string $input): ?Role
    {
        $code = strtoupper(trim($input));

        $map = [
            'SYSTEM_ADMIN' => 'ROLE_SYSTEM_ADMIN',
            'ROLE_SYSTEM_ADMIN' => 'ROLE_SYSTEM_ADMIN',
            'ADMIN' => 'ROLE_SYSTEM_ADMIN',

            'MANAGER' => 'ROLE_MANAGER',
            'ROLE_MANAGER' => 'ROLE_MANAGER',

            'EMPLOYEE' => 'ROLE_EMPLOYEE',
            'ROLE_EMPLOYEE' => 'ROLE_EMPLOYEE',
        ];

        $code = $map[$code] ?? $code;

        return $this->em->getRepository(Role::class)->findOneBy(['code' => $code]);
    }
}