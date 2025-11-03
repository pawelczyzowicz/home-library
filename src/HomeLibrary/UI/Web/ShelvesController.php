<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/shelves', name: 'shelves_index', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ShelvesController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('shelves/index.html.twig');
    }
}
