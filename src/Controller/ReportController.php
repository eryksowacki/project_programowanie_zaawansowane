<?php

namespace App\Controller;

use App\Entity\User;
use App\Report\Kpir\GenerateKpirPdfCommand;
use App\Report\Xlsx\GenerateContractorsXlsxCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/reports')]
class ReportController extends AbstractController
{
    #[Route('/kpir', name: 'api_reports_kpir_pdf', methods: ['POST'])]
    public function kpirPdf(
        #[CurrentUser] ?User $user,
        Request $request,
        GenerateKpirPdfCommand $command
    ): Response {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        try {
            /** @var array<string,mixed> $payload */
            $payload = $request->toArray(); // waliduje JSON i rzuca wyjątek przy błędzie
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        try {
            $file = $command->run($user, $payload);

            // Jeśli obiekt pliku ma helper ->toResponse() (zalecane) to użyj go
            if (is_object($file) && method_exists($file, 'toResponse')) {
                /** @var Response $response */
                $response = $file->toResponse();
                return $response;
            }

            // Fallback: obsługa jak w Twojej pierwotnej wersji (content/contentType/filename)
            return new Response(
                $file->content ?? '',
                200,
                [
                    'Content-Type' => $file->contentType ?? 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . ($file->filename ?? 'kpir.pdf') . '"',
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['message' => $e->getMessage()], 500);
        }
    }

    #[Route('/contractors-xlsx', name: 'api_reports_contractors_xlsx', methods: ['POST'])]
    public function contractorsXlsx(
        #[CurrentUser] ?User $user,
        Request $request,
        GenerateContractorsXlsxCommand $command
    ): Response {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }
        $this->denyUnlessEmployeeOrManager();

        try {
            /** @var array<string,mixed> $payload */
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        try {
            $file = $command->run($user, $payload);

            if (is_object($file) && method_exists($file, 'toResponse')) {
                /** @var Response $response */
                $response = $file->toResponse();
                return $response;
            }

            // Fallback gdyby XLSX też zwracał content/contentType/filename
            return new Response(
                $file->content ?? '',
                200,
                [
                    'Content-Type' => $file->contentType ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . ($file->filename ?? 'contractors.xlsx') . '"',
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['message' => $e->getMessage()], 500);
        }
    }

    private function denyUnlessEmployeeOrManager(): void
    {
        if ($this->isGranted('ROLE_SYSTEM_ADMIN')) {
            throw $this->createAccessDeniedException('Admins are not allowed');
        }
        if (!$this->isGranted('ROLE_EMPLOYEE') && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Forbidden');
        }
    }
}