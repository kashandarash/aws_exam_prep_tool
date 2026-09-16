<?php

namespace App\Repository;

use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * @return list<string>
     */
    public function findAllTexts(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.text')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param 'active'|'mastered'|null $status
     *
     * @return array{items: list<Question>, total: int}
     */
    public function search(?string $searchText, ?string $status, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('q');

        if (null !== $searchText && '' !== $searchText) {
            $qb->andWhere('q.text LIKE :search')
                ->setParameter('search', '%'.$searchText.'%');
        }

        if ('mastered' === $status) {
            $qb->andWhere('q.correctAnswers >= :threshold')->setParameter('threshold', Question::MASTERY_THRESHOLD);
        } elseif ('active' === $status) {
            $qb->andWhere('q.correctAnswers < :threshold')->setParameter('threshold', Question::MASTERY_THRESHOLD);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(q.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('q.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countMastered(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.correctAnswers >= :threshold')
            ->setParameter('threshold', Question::MASTERY_THRESHOLD)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Question>
     */
    public function findAvailableForTest(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.correctAnswers < :threshold')
            ->setParameter('threshold', Question::MASTERY_THRESHOLD)
            ->getQuery()
            ->getResult();
    }
}
