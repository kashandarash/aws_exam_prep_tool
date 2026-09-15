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
     * @return array{items: list<Question>, total: int}
     */
    public function search(?string $searchText, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('q');

        if (null !== $searchText && '' !== $searchText) {
            $qb->andWhere('q.text LIKE :search')
                ->setParameter('search', '%'.$searchText.'%');
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
}
