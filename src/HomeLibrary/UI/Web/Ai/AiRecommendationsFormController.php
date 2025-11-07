<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Ai;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route(path: '/ai/recommendations', name: 'ai_recommendations_form', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class AiRecommendationsFormController extends AbstractController
{
    public function __construct(private readonly Environment $twig) {}

    public function __invoke(): Response
    {
        return new Response($this->twig->render('ai/recommendations_form.html.twig'));
    }
}
