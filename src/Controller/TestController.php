<?php

namespace App\Controller;

use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    private const QUESTIONS_PER_TEST = 20;

    #[Route('/test/take', name: 'test_take', methods: ['GET', 'POST'])]
    public function take(Request $request, EntityManagerInterface $entityManager, QuestionRepository $questionRepository): Response
    {
        if ($request->isMethod('POST')) {
            $questionIds = array_map('intval', $request->request->all('question_ids'));
            $answers = $request->request->all('answers');
            $questions = $questionRepository->findBy(['id' => $questionIds], ['id' => 'ASC']);

            $correct = 0;
            $results = [];
            foreach ($questions as $question) {
                $submittedIndexes = array_map('intval', $answers[$question->getId()] ?? []);
                sort($submittedIndexes);

                $correctIndexes = $question->getCorrectIndexes();
                sort($correctIndexes);

                $isCorrect = $submittedIndexes === $correctIndexes;

                if ($isCorrect) {
                    ++$correct;
                    $question->incrementCorrectAnswers();
                }

                $results[] = [
                    'question' => $question,
                    'submittedIndexes' => $submittedIndexes,
                    'isCorrect' => $isCorrect,
                ];
            }

            $entityManager->flush();

            return $this->render('test/result.html.twig', [
                'results' => $results,
                'correct' => $correct,
                'total' => count($results),
            ]);
        }

        $available = $questionRepository->findAvailableForTest();
        shuffle($available);
        $questions = array_slice($available, 0, self::QUESTIONS_PER_TEST);

        return $this->render('test/take.html.twig', [
            'questions' => $questions,
            'hasQuestions' => $questionRepository->count([]) > 0,
        ]);
    }
}
