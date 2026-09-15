<?php

namespace App\Controller;

use App\Entity\Question;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionController extends AbstractController
{
    #[Route('/questions/add', name: 'question_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager, BedrockRuntimeClient $bedrock): Response
    {
        if ($request->isMethod('POST')) {
            $text = trim($request->request->getString('text'));

            $options = [];
            foreach ($request->request->all('option') as $row) {
                $optionText = trim((string) ($row['text'] ?? ''));

                if ('' === $optionText) {
                    continue;
                }

                $options[] = [
                    'text' => $optionText,
                    'correct' => !empty($row['correct']),
                ];
            }

            $hasCorrectOption = [] !== array_filter($options, static fn (array $option): bool => $option['correct']);

            if ('' === $text || count($options) < 2 || !$hasCorrectOption) {
                $this->addFlash('error', 'Please fill in the question, at least two options, and mark at least one option as correct.');

                return $this->redirectToRoute('question_add');
            }

            $question = (new Question())
                ->setText($text)
                ->setOptions($options);

            $entityManager->persist($question);
            $entityManager->flush();

            $this->addFlash('success', 'Question added.');

            return $this->redirectToRoute('question_add');
        }

        return $this->render('question/add.html.twig', [
            'bedrockConnected' => $this->isBedrockConnected($bedrock),
        ]);
    }

    private function isBedrockConnected(BedrockRuntimeClient $bedrock): bool
    {
        try {
            $bedrock->listAsyncInvokes(['maxResults' => 1]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
