<?php
// src/Controller/MainController.php
namespace App\Controller;

use App\Entity\Building;
use App\Entity\Camera;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'homepage')]
    public function homepage(): Response
    {
        // Get the first active building with cameras
        $building = $this->entityManager->getRepository(Building::class)
            ->createQueryBuilder('b')
            ->leftJoin('b.cameras', 'c')
            ->where('b.active = true')
            ->andWhere('c.active = true')
            ->andWhere('c.liveEnabled = true')
            ->orderBy('b.displayOrder', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$building) {
            return $this->render('no_cameras.html.twig');
        }

        // Get the first camera for this building
        $camera = $building->getActiveCameras()
            ->filter(fn($c) => $c->isLiveEnabled())
            ->first();

        if (!$camera) {
            return $this->render('no_cameras.html.twig');
        }

        return $this->redirectToRoute('camera_live', [
            'building_slug' => $building->getSlug(),
            'camera_id' => $camera->getId()
        ]);
    }

    #[Route('/buildings', name: 'buildings_list')]
    public function buildingsList(): Response
    {
        $buildings = $this->entityManager->getRepository(Building::class)
            ->createQueryBuilder('b')
            ->leftJoin('b.cameras', 'c')
            ->where('b.active = true')
            ->andWhere('c.active = true')
            ->orderBy('b.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('buildings_list.html.twig', [
            'buildings' => $buildings
        ]);
    }

    #[Route('/{building_slug}', name: 'building_home')]
    public function buildingHome(string $building_slug): Response
    {
        $building = $this->getBuilding($building_slug);
        
        // Get first active camera with live enabled
        $camera = $building->getActiveCameras()
            ->filter(fn($c) => $c->isLiveEnabled())
            ->first();

        if (!$camera) {
            return $this->render('no_cameras.html.twig', [
                'building' => $building
            ]);
        }

        return $this->redirectToRoute('camera_live', [
            'building_slug' => $building_slug,
            'camera_id' => $camera->getId()
        ]);
    }

    #[Route('/{building_slug}/{camera_id}', name: 'camera_live', requirements: ['camera_id' => '\d+'])]
    public function cameraLive(string $building_slug, int $camera_id): Response
    {
        $building = $this->getBuilding($building_slug);
        $camera = $this->getCamera($camera_id, $building);

        if (!$camera->isLiveEnabled()) {
            throw $this->createNotFoundException('Live view not available for this camera');
        }

        return $this->render('camera_live.html.twig', [
            'building' => $building,
            'camera' => $camera,
            'cameras' => $this->getAvailableCameras($building)
        ]);
    }

    #[Route('/{building_slug}/{camera_id}/gallery', name: 'camera_gallery', requirements: ['camera_id' => '\d+'])]
    public function cameraGallery(string $building_slug, int $camera_id): Response
    {
        $building = $this->getBuilding($building_slug);
        $camera = $this->getCamera($camera_id, $building);

        if (!$camera->isGalleryEnabled()) {
            throw $this->createNotFoundException('Gallery not available for this camera');
        }

        // Get today's date for gallery
        $date = date('Ymd');
        $galleryData = $this->getGalleryImages($camera, $date);

        return $this->render('camera_gallery.html.twig', [
            'building' => $building,
            'camera' => $camera,
            'cameras' => $this->getAvailableCameras($building),
            'images' => $galleryData['images'],
            'thumbnails' => $galleryData['thumbnails'],
            'date' => $date
        ]);
    }

    #[Route('/{building_slug}/{camera_id}/gallery/{date}', name: 'camera_gallery_date', requirements: ['camera_id' => '\d+', 'date' => '\d{8}'])]
    public function cameraGalleryDate(string $building_slug, int $camera_id, string $date): Response
    {
        $building = $this->getBuilding($building_slug);
        $camera = $this->getCamera($camera_id, $building);

        if (!$camera->isGalleryEnabled()) {
            throw $this->createNotFoundException('Gallery not available for this camera');
        }

        $galleryData = $this->getGalleryImages($camera, $date);

        return $this->render('camera_gallery.html.twig', [
            'building' => $building,
            'camera' => $camera,
            'cameras' => $this->getAvailableCameras($building),
            'images' => $galleryData['images'],
            'thumbnails' => $galleryData['thumbnails'],
            'date' => $date
        ]);
    }

    #[Route('/api/image/{camera_id}', name: 'api_camera_image', requirements: ['camera_id' => '\d+'])]
    public function apiCameraImage(int $camera_id): Response
    {
        $camera = $this->entityManager->getRepository(Camera::class)->find($camera_id);
        
        if (!$camera || !$camera->isActive()) {
            return new Response('Camera not found', 404);
        }

        $latestImage = $this->getLatestImage($camera);

        return $this->render('api_image.html.twig', [
            'image' => $latestImage,
        ]);
    }

    // Helper methods

    private function getBuilding(string $slug): Building
    {
        $building = $this->entityManager->getRepository(Building::class)
            ->findOneBy(['slug' => $slug, 'active' => true]);

        if (!$building) {
            throw $this->createNotFoundException('Building not found');
        }

        return $building;
    }

    private function getCamera(int $id, Building $building): Camera
    {
        $camera = $this->entityManager->getRepository(Camera::class)
            ->findOneBy(['id' => $id, 'building' => $building, 'active' => true]);

        if (!$camera) {
            throw $this->createNotFoundException('Camera not found');
        }

        return $camera;
    }

    private function getAvailableCameras(Building $building): array
    {
        return $building->getActiveCameras()
            ->filter(fn($c) => $c->isLiveEnabled())
            ->toArray();
    }

    private function getLatestImage(Camera $camera): ?string
    {
        $cameraDir = "/var/www/construction-cameras/public/images/cam{$camera->getId()}";
        
        if (!is_dir($cameraDir)) {
            return null;
        }

        // Get newest directory (date)
        $directories = glob($cameraDir . '/????????');
        if (empty($directories)) {
            return null;
        }

        sort($directories);
        $newestDir = end($directories);
        
        // Get newest file in directory
        $files = glob($newestDir . '/*.jpg');
        if (empty($files)) {
            return null;
        }

        sort($files);
        $newestFile = end($files);
        
        // Return relative path for web
        return 'images/cam' . $camera->getId() . '/' . basename($newestDir) . '/' . basename($newestFile);
    }

    private function getGalleryImages(Camera $camera, string $date): array
    {
        $dayDir = "/var/www/construction-cameras/public/images/cam{$camera->getId()}/{$date}";
        $thumbnailDir = $dayDir . "/thumbnail";
        
        $images = [];
        $thumbnails = [];

        // Get full images
        if (is_dir($dayDir)) {
            $files = glob($dayDir . '/*.jpg');
            sort($files);
            
            foreach ($files as $file) {
                $filename = basename($file);
                $images[$filename] = "images/cam{$camera->getId()}/{$date}/{$filename}";
            }
        }

        // Get thumbnails
        if (is_dir($thumbnailDir)) {
            $files = glob($thumbnailDir . '/*.jpg');
            sort($files);
            
            foreach ($files as $file) {
                $filename = basename($file);
                $originalName = str_replace('thumbnail-', '', $filename);
                $thumbnails[$originalName] = "images/cam{$camera->getId()}/{$date}/thumbnail/{$filename}";
            }
        }

        return [
            'images' => $images,
            'thumbnails' => $thumbnails
        ];
    }
}
