<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SpaController extends AbstractController
{
    #[Route('/', name: 'spa_home', methods: ['GET'])]
    public function index(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/index.html';

        return new Response(
            file_get_contents($path),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    #[Route('/{reactRouting}', name: 'spa_fallback', requirements: ['reactRouting' => '^(?!api).+'], methods: ['GET'])]
    public function fallback(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/index.html';

        return new Response(
            file_get_contents($path),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}