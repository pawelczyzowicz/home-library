<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Books;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/books/new', name: 'books_new', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class CreateBookController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('books/new.html.twig');
    }
}
