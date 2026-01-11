<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SpaController extends AbstractController
{
    // UWAGA: ta trasa ma być NA KOŃCU routingu
    // i nie może łapać /api/*
    #[Route('/{reactRouting}', name: 'spa', requirements: ['reactRouting' => '^(?!api).+'], methods: ['GET'])]
    public function index(): Response
    {
        // index.html jest w public/
        return $this->file($this->getParameter('kernel.project_dir') . '/public/index.html');
    }
}