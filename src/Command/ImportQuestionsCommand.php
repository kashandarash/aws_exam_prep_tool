<?php

namespace App\Command;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:questions:import',
    description: 'Import questions from the YAML files under data/ (as written by app:questions:export), skipping any already in the bank.',
)]
class ImportQuestionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuestionRepository $questionRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceDir = $this->projectDir.'/data';

        $files = glob($sourceDir.'/*.yml') ?: [];
        sort($files, \SORT_NATURAL);

        if ([] === $files) {
            $io->warning(sprintf('No YAML files found in %s.', $sourceDir));

            return Command::SUCCESS;
        }

        // Seed with normalized text of every question already in the bank, so
        // re-running the command (or importing overlapping exports) doesn't
        // create duplicates.
        $seenTexts = array_fill_keys(array_map(Question::normalizeText(...), $this->questionRepository->findAllTexts()), true);

        $imported = 0;
        $duplicates = 0;
        $invalid = 0;

        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            $text = trim((string) ($data['text'] ?? ''));
            $options = Question::buildOptions((array) ($data['options'] ?? []));

            if ('' === $text || null === $options) {
                $io->warning(sprintf('Skipping invalid file: %s', basename($file)));
                ++$invalid;
                continue;
            }

            $normalized = Question::normalizeText($text);

            if (isset($seenTexts[$normalized])) {
                ++$duplicates;
                continue;
            }

            $seenTexts[$normalized] = true;

            $this->entityManager->persist((new Question())->setText($text)->setOptions($options));
            ++$imported;
        }

        if ($imported > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Imported %d question(s) from %s. Skipped %d duplicate(s) and %d invalid file(s).',
            $imported,
            $sourceDir,
            $duplicates,
            $invalid,
        ));

        return Command::SUCCESS;
    }
}
