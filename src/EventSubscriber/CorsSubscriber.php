<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    public function __construct(array $allowedOrigins = ['http://localhost:3000'])
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // tylko API
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Preflight
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            $this->applyCorsHeaders($request->headers->get('Origin'), $response);
            $response->setStatusCode(204);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $response = $event->getResponse();
        $this->applyCorsHeaders($request->headers->get('Origin'), $response);
    }

    private function applyCorsHeaders(?string $origin, Response $response): void
    {
        if (!$origin || !in_array($origin, $this->allowedOrigins, true)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '86400');
    }
}
