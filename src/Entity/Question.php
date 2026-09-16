<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    /**
     * A question is retired from tests once it's been answered correctly at
     * least this many times.
     */
    public const MASTERY_THRESHOLD = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $text = '';

    /**
     * @var list<array{text: string, correct: bool}>
     */
    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $correctAnswers = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return list<array{text: string, correct: bool}>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param list<array{text: string, correct: bool}> $options
     */
    public function setOptions(array $options): static
    {
        $this->options = array_values($options);

        return $this;
    }

    /**
     * @return list<int> zero-based indexes (into getOptions()) of the correct options
     */
    public function getCorrectIndexes(): array
    {
        return array_values(array_keys(array_filter(
            $this->options,
            static fn (array $option): bool => $option['correct'],
        )));
    }

    public function isMultiAnswer(): bool
    {
        return count($this->getCorrectIndexes()) > 1;
    }

    public function getCorrectAnswers(): int
    {
        return $this->correctAnswers;
    }

    public function incrementCorrectAnswers(): static
    {
        ++$this->correctAnswers;

        return $this;
    }

    public function isMastered(): bool
    {
        return $this->correctAnswers >= self::MASTERY_THRESHOLD;
    }
}
