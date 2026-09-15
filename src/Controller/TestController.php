<?php

namespace App\Controller;

use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    private const QUESTIONS_PER_TEST = 10;

    #[Route('/test/run', name: 'test_run', methods: ['GET', 'POST'])]
    public function run(Request $request, QuestionRepository $questionRepository): Response
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
                }

                $results[] = [
                    'question' => $question,
                    'submittedIndexes' => $submittedIndexes,
                    'isCorrect' => $isCorrect,
                ];
            }

            return $this->render('test/result.html.twig', [
                'results' => $results,
                'correct' => $correct,
                'total' => count($results),
            ]);
        }

        $questions = $questionRepository->findAll();
        shuffle($questions);
        $questions = array_slice($questions, 0, self::QUESTIONS_PER_TEST);

        return $this->render('test/run.html.twig', [
            'questions' => $questions,
        ]);
    }
}
