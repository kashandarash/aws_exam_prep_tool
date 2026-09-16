<?php

namespace App\Command;

use App\Repository\QuestionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:questions:export',
    description: 'Export each question\'s text and options to a YAML file under data/, named after its id.',
)]
class ExportQuestionsCommand extends Command
{
    public function __construct(
        private readonly QuestionRepository $questionRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetDir = $this->projectDir.'/data';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $io->error(sprintf('Could not create directory "%s".', $targetDir));

            return Command::FAILURE;
        }

        $questions = $this->questionRepository->findBy([], ['id' => 'ASC']);

        foreach ($questions as $question) {
            $data = [
                'text' => $question->getText(),
                'options' => $question->getOptions(),
            ];

            file_put_contents(
                sprintf('%s/%d.yml', $targetDir, $question->getId()),
                Yaml::dump($data, 10, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
            );
        }

        $io->success(sprintf('Exported %d question(s) to %s.', count($questions), $targetDir));

        return Command::SUCCESS;
    }
}
