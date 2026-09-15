<?php

namespace App\Controller;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionController extends AbstractController
{
    private const TOOL_NAME = 'record_questions';
    private const QUESTIONS_PER_PAGE = 20;

    #[Route('/questions', name: 'question_list', methods: ['GET'])]
    public function list(Request $request, QuestionRepository $questionRepository): Response
    {
        $search = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));

        $result = $questionRepository->search($search, $page, self::QUESTIONS_PER_PAGE);
        $totalPages = max(1, (int) ceil($result['total'] / self::QUESTIONS_PER_PAGE));

        if ($page > $totalPages) {
            return $this->redirectToRoute('question_list', ['q' => $search, 'page' => $totalPages]);
        }

        return $this->render('question/list.html.twig', [
            'questions' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => self::QUESTIONS_PER_PAGE,
            'totalPages' => $totalPages,
            'search' => $search,
        ]);
    }

    #[Route('/questions/{id}/edit', name: 'question_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, QuestionRepository $questionRepository): Response
    {
        $question = $questionRepository->find($id);

        if (null === $question) {
            throw $this->createNotFoundException('Question not found.');
        }

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

                return $this->redirectToRoute('question_edit', ['id' => $id]);
            }

            $question->setText($text)->setOptions($options);
            $entityManager->flush();

            $this->addFlash('success', 'Question updated.');

            return $this->redirectToRoute('question_list');
        }

        return $this->render('question/edit.html.twig', [
            'question' => $question,
        ]);
    }

    #[Route('/questions/add', name: 'question_add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        QuestionRepository $questionRepository,
        BedrockRuntimeClient $bedrock,
        string $bedrockModel,
    ): Response {
        if ($request->isMethod('POST')) {
            $html = trim($request->request->getString('html'));

            if ('' === $html) {
                $this->addFlash('error', 'Paste the exam page HTML before importing.');

                return $this->redirectToRoute('question_add');
            }

            try {
                // A full exam page can take well over PHP's default 30s execution
                // limit to come back from Bedrock.
                set_time_limit(120);
                $extracted = $this->extractQuestions($bedrock, $bedrockModel, $html);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Bedrock request failed: '.$e->getMessage());

                return $this->redirectToRoute('question_add');
            }

            // Seed with normalized text of every question already in the bank, so
            // freshly-imported questions are also deduplicated against them and
            // against each other in the same batch.
            $seenTexts = array_fill_keys(array_map(self::normalize(...), $questionRepository->findAllTexts()), true);

            $added = 0;
            $duplicates = 0;
            $invalid = 0;

            foreach ($extracted as $item) {
                $text = trim((string) ($item['text'] ?? ''));

                $options = [];
                foreach ((array) ($item['options'] ?? []) as $option) {
                    $optionText = trim((string) ($option['text'] ?? ''));

                    if ('' === $optionText) {
                        continue;
                    }

                    $options[] = [
                        'text' => $optionText,
                        'correct' => (bool) ($option['correct'] ?? false),
                    ];
                }

                $hasCorrectOption = [] !== array_filter($options, static fn (array $o): bool => $o['correct']);

                if ('' === $text || count($options) < 2 || !$hasCorrectOption) {
                    ++$invalid;
                    continue;
                }

                $normalized = self::normalize($text);

                if (isset($seenTexts[$normalized])) {
                    ++$duplicates;
                    continue;
                }

                $seenTexts[$normalized] = true;

                $entityManager->persist((new Question())->setText($text)->setOptions($options));
                ++$added;
            }

            if ($added > 0) {
                $entityManager->flush();
            }

            $this->addFlash($added > 0 ? 'success' : 'error', sprintf(
                'Imported %d question(s). Skipped %d duplicate(s) and %d invalid entr%s.',
                $added,
                $duplicates,
                $invalid,
                1 === $invalid ? 'y' : 'ies',
            ));

            return $this->redirectToRoute('question_add');
        }

        return $this->render('question/add.html.twig', [
            'bedrockConnected' => $this->isBedrockConnected($bedrock),
        ]);
    }

    /**
     * @return list<array{text: string, options: list<array{text: string, correct: bool}>}>
     */
    private function extractQuestions(BedrockRuntimeClient $bedrock, string $modelId, string $html): array
    {
        // Scripts/styles/comments never contain question content on the exam-dump
        // pages this feature targets; stripping them keeps the request smaller
        // without relying on any site-specific markup.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? $html;

        $result = $bedrock->converse([
            'modelId' => $modelId,
            'system' => [['text' => $this->extractionSystemPrompt()]],
            'messages' => [[
                'role' => 'user',
                'content' => [['text' => "Extract the exam questions from this HTML page:\n\n".$html]],
            ]],
            'inferenceConfig' => [
                'maxTokens' => 8192,
                'temperature' => 0,
            ],
            'toolConfig' => [
                'tools' => [[
                    'toolSpec' => [
                        'name' => self::TOOL_NAME,
                        'description' => 'Records the exam questions extracted from the HTML.',
                        'inputSchema' => ['json' => $this->extractionSchema()],
                    ],
                ]],
                'toolChoice' => ['tool' => ['name' => self::TOOL_NAME]],
            ],
        ]);

        foreach ($result['output']['message']['content'] ?? [] as $block) {
            if (self::TOOL_NAME === ($block['toolUse']['name'] ?? null)) {
                return $block['toolUse']['input']['questions'] ?? [];
            }
        }

        return [];
    }

    private function extractionSystemPrompt(): string
    {
        return <<<'PROMPT'
            You extract multiple-choice exam questions from raw HTML pasted from an
            online exam-question-bank / exam-dump web page (for example a certification
            practice-exam site). The HTML contains the real questions mixed with a lot of
            unrelated material: site navigation, ads, marketing/upsell/checkout sections,
            JavaScript, related-exam listings, and reader discussion/comment threads with
            community votes.

            Call the record_questions tool exactly once with every question you can
            extract. Follow these rules for every question:

            - "text" must be the question's own wording, copied EXACTLY as it appears in
              the HTML (as plain text, with tags stripped). Do not correct spelling,
              grammar, or punctuation; do not paraphrase, translate, shorten, or reformat
              it; do not add anything that isn't in the source. Where the HTML has a
              meaningful line break inside the question (e.g. a <br> separating context
              from the actual question), keep it as a "\n" in "text".
            - "options" lists every answer choice for that question, in the order they
              appear. Each option's "text" is that choice's own wording, copied exactly as
              above, but WITHOUT its leading choice label (e.g. drop "A.", "B)", "C:") —
              the label is just UI numbering, not part of the answer.
            - Exclude any UI chrome that isn't actually part of the question/option
              wording: badges like "Most Voted", vote-count numbers, "Reveal
              Solution"/"Hide Solution" buttons, discussion/comment links, voting icons,
              and similar never belong in "text".
            - Mark an option "correct": true only when the HTML's OWN answer key says so —
              e.g. an explicit "Correct Answer" label/box naming the choice letter(s), or a
              CSS class/attribute on that option marking it as correct. Some questions have
              more than one correct option (e.g. "Choose two" / "Choose three" questions);
              mark all of them correct: true.
            - NEVER use community signals to decide correctness. Vote counts, "most voted"
              badges, "voted_answers" data, and discussion/comment opinions are other
              readers' guesses, not the answer key — always ignore them for this purpose.
            - If a question has no explicit answer key in the HTML (correctness can't be
              determined from the page's own markup), leave that question out entirely
              rather than guessing.
            - If a question or any of its options can only be understood via an
              image/diagram (the text alone isn't self-contained), leave that question out
              — it can't be represented as text-only.
            - If the same question (same wording and options) appears more than once in
              the HTML, include it only once.
            - Skip everything that isn't an actual exam question: navigation, ads,
              marketing/checkout content, related-exam lists, and discussion/comment
              threads.

            If the HTML contains no extractable exam questions, call the tool with an
            empty "questions" list.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractionSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'description' => 'The question wording, copied verbatim from the HTML.',
                            ],
                            'options' => [
                                'type' => 'array',
                                'minItems' => 2,
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => [
                                            'type' => 'string',
                                            'description' => 'The answer choice wording, copied verbatim, without its leading letter label.',
                                        ],
                                        'correct' => [
                                            'type' => 'boolean',
                                            'description' => "True only if the HTML's own answer key marks this choice as correct.",
                                        ],
                                    ],
                                    'required' => ['text', 'correct'],
                                ],
                            ],
                        ],
                        'required' => ['text', 'options'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
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
