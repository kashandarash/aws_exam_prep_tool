<?php

namespace App\Controller;

use App\Entity\Visit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelloController extends AbstractController
{
    #[Route('/', name: 'hello_world')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $entityManager->persist(new Visit());
        $entityManager->flush();

        $visitCount = $entityManager->getRepository(Visit::class)->count([]);

        return $this->render('hello/index.html.twig', [
            'visitCount' => $visitCount,
        ]);
    }
}
