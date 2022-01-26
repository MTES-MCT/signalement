<?php

namespace App\Repository;

use App\Entity\Partenaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Partenaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Partenaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Partenaire[]    findAll()
 * @method Partenaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartenaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partenaire::class);
    }

    // /**
    //  * @return Partenaire[] Returns an array of Partenaire objects
    //  */
    public function findAllOrByInseeIfCommune($insee = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isArchive != 1');
        if ($insee)
            $qb->andWhere('p.isCommune = 0 OR p.isCommune = 1 AND p.insee = :insee')
                ->setParameter('insee', $insee);
        $qb->leftJoin('p.users', 'users')
            ->addSelect('users')
            ->orderBy('users.roles');

        return $qb
            ->getQuery()
            ->getResult();
    }
    /*
    public function findOneBySomeField($value): ?Partenaire
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
