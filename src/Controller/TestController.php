<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Entity\Camera;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/test/camera/{camera_id}', name: 'test_camera', requirements: ['camera_id' => '\d+'])]
    public function testCamera(int $camera_id): Response
    {
        $camera = $this->entityManager->getRepository(Camera::class)->find($camera_id);
        
        if (!$camera) {
            throw $this->createNotFoundException('Camera not found');
        }

        // Get all cameras for the selector
        $allCameras = $this->entityManager->getRepository(Camera::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.building', 'b')
            ->where('c.active = true')
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('test_mobile.html.twig', [
            'camera' => $camera,
            'allCameras' => $allCameras,
        ]);
    }

    #[Route('/test', name: 'test_index')]
    public function index(): Response
    {
        // Get first active camera
        $camera = $this->entityManager->getRepository(Camera::class)
            ->findOneBy(['active' => true], ['id' => 'ASC']);

        if (!$camera) {
            return new Response('No active cameras found. Please add a camera first.');
        }

        return $this->redirectToRoute('test_camera', ['camera_id' => $camera->getId()]);
    }
}