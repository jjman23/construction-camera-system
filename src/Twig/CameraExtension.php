<?php
// src/Twig/CameraExtension.php
namespace App\Twig;

use App\Entity\Building;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CameraExtension extends AbstractExtension
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_all_buildings', [$this, 'getAllBuildings']),
        ];
    }

    public function getAllBuildings(): array
    {
        return $this->entityManager->getRepository(Building::class)
            ->createQueryBuilder('b')
            ->leftJoin('b.cameras', 'c')
            ->where('b.active = true')
            ->andWhere('c.active = true')
            ->orderBy('b.displayOrder', 'ASC')
            ->addOrderBy('c.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
