<?php

namespace App\Repository;

use App\Entity\Signalement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Signalement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Signalement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Signalement[]    findAll()
 * @method Signalement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SignalementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signalement::class);
    }

    public function checkOptions($qb, $options)
    {
        if (isset($options['search'])) {
            if (preg_match('/([0-9]{4})-[0-9]{0,6}/', $options['search'])) {
                $qb->andWhere('s.reference = :search');
                $qb->setParameter('search', $options['search']);
            } else {
                $qb->andWhere('LOWER(s.nomOccupant) LIKE :search 
                OR LOWER(s.prenomOccupant) LIKE :search 
                OR LOWER(s.reference) LIKE :search 
                OR LOWER(s.adresseOccupant) LIKE :search 
                OR LOWER(s.villeOccupant) LIKE :search
                OR LOWER(s.nomProprio) LIKE :search');
                $qb->setParameter('search', "%" . strtolower($options['search']) . "%");
            }
        }
        if (isset($options['partners'])) {
            if (in_array('AUCUN', $options['partners']))
                $qb->andWhere('affectations IS NULL');
            else
                $qb->andWhere('partenaire IN (:partners)')
                    ->setParameter('partners', $options['partners']);
        }
        if (isset($options['statuses'])) {
            $qb->andWhere('s.statut IN (:statuses)')
                ->setParameter('statuses', $options['statuses']);
        }
        if (isset($options['cities'])) {
            $qb->andWhere('s.villeOccupant IN (:cities)')
                ->setParameter('cities', $options['cities']);
        }
        if (isset($options['dates'])) {
            if (isset($options['dates']['on'])) {
                $qb->andWhere('s.createdAt >= :date_in')
                    ->setParameter('date_in', $options['dates']['on']);
            } elseif (isset($options['dates']['off'])) {
                $qb->andWhere('s.createdAt <= :date_off')
                    ->setParameter('date_in', $options['dates']['off']);
            }
        }
        if (isset($options['criteres'])) {
            $qb->andWhere('criteres IN (:criteres)')
                ->setParameter('criteres', $options['criteres']);
        }
        if (isset($options['housetypes'])) {
            $qb->andWhere('s.isLogementSocial IN (:housetypes)')
                ->setParameter('housetypes', $options['housetypes']);
        }
        if (isset($options['allocs'])) {
            $qb->andWhere('s.isAllocataire IN (:allocs)')
                ->setParameter('allocs', $options['allocs']);
        }
        if (isset($options['declarants'])) {
            $qb->andWhere('s.isNotOccupant IN (:declarants)')
                ->setParameter('declarants', $options['declarants']);
        }
        if (isset($options['proprios'])) {
            $qb->andWhere('s.isProprioAverti IN (:proprios)')
                ->setParameter('proprios', $options['proprios']);
        }
        if (isset($options['interventions'])) {
            $qb->andWhere('s.isRefusIntervention IN (:interventions)')
                ->setParameter('interventions', $options['interventions']);
        }
        if (isset($options['visites'])) {
            $qb->andWhere('IF(s.dateVisite IS NOT NULL,1,0) IN (:visites)')
                ->setParameter('visites', $options['visites']);
        }
    }


    // /**
    //  * @return Signalement[] Returns an array of Signalement objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */


    public function findAllWithGeoData($options)
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.geoloc')
            ->addSelect('s.uuid')
            ->addSelect('s.reference');
        $this->checkOptions($qb, $options);
        return $qb->andWhere("JSON_EXTRACT(s.geoloc,'$.lat') != ''")
            ->andWhere("JSON_EXTRACT(s.geoloc,'$.lng') != ''")
            ->andWhere('s.statut != 7')
            ->getQuery()
            ->getResult();
    }

    public function findAllWithAffectations($year)
    {
        return $this->createQueryBuilder('s')
            ->where('s.statut != 7')
            ->andWhere('YEAR(s.createdAt) = ' . $year)
            ->leftJoin('s.affectations', 'affectations')
            ->addSelect('affectations', 's')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus()
    {
        $qb = $this->createQueryBuilder('s');
        $qb->select('COUNT(s.id) as count')
            ->addSelect('s.statut');
        $qb->indexBy('s', 's.statut');
        $qb->groupBy('s.statut');
        return $qb->getQuery()
            ->getResult();
    }

    public function countByCity()
    {
        $qb = $this->createQueryBuilder('s');
        $qb->select('COUNT(s.id) as count')
            ->addSelect('s.villeOccupant');
        $qb->indexBy('s', 's.villeOccupant');
        $qb->groupBy('s.villeOccupant');
        return $qb->getQuery()
            ->getResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findByUuid($uuid)
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.uuid = :uuid')
            ->setParameter('uuid', $uuid);
        $qb
            ->leftJoin('s.situations', 'situations')
            ->leftJoin('s.affectations', 'affectations')
            ->leftJoin('situations.criteres', 'criteres')
            ->leftJoin('criteres.criticites', 'criticites')
            ->leftJoin('affectations.partenaire', 'partenaire')
            ->addSelect('situations', 'affectations', 'criteres', 'criticites', 'partenaire');
        /*$qb->leftJoin('s.situations','situations');
        $qb->leftJoin('situations.criteres','criteres');
        $qb->leftJoin('criteres.criticites','criticites');
        $qb->leftJoin('s.affectations','affectations');
        $qb->leftJoin('affectations.partenaire','partenaire');
        $qb->leftJoin('partenaire.users','user');
        $qb->leftJoin('suivis.createdBy','createdBy');
        $qb->addSelect('affectations','partenaire','user','situations','criteres','criticites','suivis','createdBy');*/
        return $qb->getQuery()->getOneOrNullResult();
    }


    public function findByStatusAndOrCityForUser(User|UserInterface $user = null, array $options, int|null $export): Paginator|array
    {

        $pageSize = $options['pageSize'] ?? 20;
        $firstResult = (($options['page'] ?? 1) - 1) * $pageSize;
        $qb = $this->createQueryBuilder('s');
        if (!$export)
            $qb->select('PARTIAL s.{id,uuid,reference,nomOccupant,prenomOccupant,adresseOccupant,cpOccupant,villeOccupant,scoreCreation,statut,createdAt,geoloc}');
        $qb->where('s.statut != :status')
            ->setParameter('status', Signalement::STATUS_ARCHIVED);
//        $qb->leftJoin('s.affectations', 'affectations1');
        $qb->leftJoin('s.affectations', 'affectations');
        $qb->leftJoin('affectations.partenaire', 'partenaire');
        $qb->leftJoin('partenaire.users', 'user');
        $qb->leftJoin('s.suivis', 'suivis');
        $qb->leftJoin('s.criteres', 'criteres');
        $qb->addSelect('affectations', /*'affectations1',*/ 'partenaire', 'user', 'suivis');
        $this->checkOptions($qb, $options);
        $qb->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($firstResult)
            ->setMaxResults($export ?? $pageSize)
            ->getQuery();
        return new Paginator($qb, true);
    }


    public
    function findCities($user = null): array|int|string
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.villeOccupant ville')
            ->where('s.statut != :status')
            ->setParameter('status', Signalement::STATUS_ARCHIVED);
        if ($user)
            $qb->leftJoin('s.affectations', 'affectations')
                ->leftJoin('affectations.partenaire', 'partenaire')
                ->andWhere('partenaire = :partenaire')
                ->setParameter('partenaire', $user->getPArtenaire());
        return $qb->groupBy('s.villeOccupant')
            ->getQuery()
            ->getResult();
    }


    public
    function findOneByCodeForPublic($code): ?Signalement
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.codeSuivi = :code')
            ->setParameter('code', $code)
            ->leftJoin('s.suivis', 'suivis', Join::WITH, 'suivis.isPublic = 1')
            ->addSelect('suivis')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public
    function findNewsSinceForPartenaire($partenaire)
    {
        $now = new \DateTimeImmutable();
        $diff = $now->modify('-30 days');
        $qb = $this->createQueryBuilder('s')
            ->where('partenaire = :partenaire')
            ->setParameter('partenaire', $partenaire)
            ->setParameter('diff', $diff);
        $qb
            ->leftJoin('s.suivis', 'suivis', 'WITH', 'suivis.createdAt > :diff')
            ->leftJoin('s.affectations', 'affectations')
            ->leftJoin('affectations.partenaire', 'partenaire')
            ->addSelect('affectations', 'partenaire', 'suivis');
        return $qb->getQuery()->getResult();
    }

}
