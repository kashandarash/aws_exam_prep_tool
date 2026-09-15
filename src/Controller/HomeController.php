<?php

namespace App\Controller;

use App\Entity\Visit;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(EntityManagerInterface $entityManager, QuestionRepository $questionRepository): Response
    {
        $entityManager->persist(new Visit());
        $entityManager->flush();

        $visitCount = $entityManager->getRepository(Visit::class)->count([]);

        return $this->render('home/index.html.twig', [
            'visitCount' => $visitCount,
            'questionCount' => $questionRepository->count([]),
        ]);
    }
}
