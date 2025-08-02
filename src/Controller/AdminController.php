<?php
// src/Controller/AdminController.php
namespace App\Controller;

use App\Entity\Building;
use App\Entity\Camera;
use App\Entity\SnapshotLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $buildings = $this->entityManager->getRepository(Building::class)
            ->findBy(['active' => true], ['displayOrder' => 'ASC']);
            
        $cameras = $this->entityManager->getRepository(Camera::class)
            ->findBy(['active' => true], ['displayOrder' => 'ASC']);
            
        $recentLogs = $this->entityManager->getRepository(SnapshotLog::class)
            ->findBy([], ['attemptedAt' => 'DESC'], 10);

        return $this->render('admin/dashboard.html.twig', [
            'buildings' => $buildings,
            'cameras' => $cameras,
            'recentLogs' => $recentLogs,
        ]);
    }

    #[Route('/buildings', name: 'admin_buildings')]
    public function buildings(): Response
    {
        $buildings = $this->entityManager->getRepository(Building::class)
            ->findBy([], ['displayOrder' => 'ASC', 'name' => 'ASC']);

        return $this->render('admin/buildings.html.twig', [
            'buildings' => $buildings,
        ]);
    }

    #[Route('/buildings/new', name: 'admin_building_new')]
    public function newBuilding(Request $request): Response
    {
        $building = new Building();
        
        if ($request->isMethod('POST')) {
            $building->setName($request->request->get('name'));
            $building->setSlug($request->request->get('slug'));
            $building->setDescription($request->request->get('description'));
            $building->setDisplayOrder((int)$request->request->get('display_order', 0));
            
            $this->entityManager->persist($building);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Building created successfully!');
            return $this->redirectToRoute('admin_buildings');
        }

        return $this->render('admin/building_form.html.twig', [
            'building' => $building,
            'is_edit' => false,
        ]);
    }

    #[Route('/buildings/{id}/edit', name: 'admin_building_edit')]
    public function editBuilding(Building $building, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $building->setName($request->request->get('name'));
            $building->setSlug($request->request->get('slug'));
            $building->setDescription($request->request->get('description'));
            $building->setDisplayOrder((int)$request->request->get('display_order'));
            $building->setActive((bool)$request->request->get('active'));
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Building updated successfully!');
            return $this->redirectToRoute('admin_buildings');
        }

        return $this->render('admin/building_form.html.twig', [
            'building' => $building,
            'is_edit' => true,
        ]);
    }

    #[Route('/cameras', name: 'admin_cameras')]
    public function cameras(): Response
    {
        $cameras = $this->entityManager->getRepository(Camera::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.building', 'b')
            ->orderBy('b.displayOrder', 'ASC')
            ->addOrderBy('c.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/cameras.html.twig', [
            'cameras' => $cameras,
        ]);
    }

    #[Route('/cameras/new', name: 'admin_camera_new')]
    public function newCamera(Request $request): Response
    {
        $camera = new Camera();
        $buildings = $this->entityManager->getRepository(Building::class)
            ->findBy(['active' => true], ['displayOrder' => 'ASC']);
        
        if ($request->isMethod('POST')) {
            $building = $this->entityManager->getRepository(Building::class)
                ->find($request->request->get('building_id'));
                
            $camera->setBuilding($building);
            $camera->setName($request->request->get('name'));
            $camera->setDescription($request->request->get('description'));
            $camera->setRtspUrl($request->request->get('rtsp_url'));
            $camera->setSnapshotEnabled((bool)$request->request->get('snapshot_enabled'));
            $camera->setSnapshotInterval((int)$request->request->get('snapshot_interval', 300));
            $camera->setStartTime(new \DateTime($request->request->get('start_time', '05:00:00')));
            $camera->setStopTime(new \DateTime($request->request->get('stop_time', '22:00:00')));
            $camera->setGalleryEnabled((bool)$request->request->get('gallery_enabled'));
            $camera->setLiveEnabled((bool)$request->request->get('live_enabled'));
            $camera->setDisplayOrder((int)$request->request->get('display_order', 0));
            
            $this->entityManager->persist($camera);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Camera created successfully!');
            return $this->redirectToRoute('admin_cameras');
        }

        return $this->render('admin/camera_form.html.twig', [
            'camera' => $camera,
            'buildings' => $buildings,
            'is_edit' => false,
        ]);
    }

    #[Route('/cameras/{id}/edit', name: 'admin_camera_edit')]
    public function editCamera(Camera $camera, Request $request): Response
    {
        $buildings = $this->entityManager->getRepository(Building::class)
            ->findBy(['active' => true], ['displayOrder' => 'ASC']);
        
        if ($request->isMethod('POST')) {
            $building = $this->entityManager->getRepository(Building::class)
                ->find($request->request->get('building_id'));
                
            $camera->setBuilding($building);
            $camera->setName($request->request->get('name'));
            $camera->setDescription($request->request->get('description'));
            $camera->setRtspUrl($request->request->get('rtsp_url'));
            $camera->setSnapshotEnabled((bool)$request->request->get('snapshot_enabled'));
            $camera->setSnapshotInterval((int)$request->request->get('snapshot_interval'));
            $camera->setStartTime(new \DateTime($request->request->get('start_time')));
            $camera->setStopTime(new \DateTime($request->request->get('stop_time')));
            $camera->setGalleryEnabled((bool)$request->request->get('gallery_enabled'));
            $camera->setLiveEnabled((bool)$request->request->get('live_enabled'));
            $camera->setDisplayOrder((int)$request->request->get('display_order'));
            $camera->setActive((bool)$request->request->get('active'));
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Camera updated successfully!');
            return $this->redirectToRoute('admin_cameras');
        }

        return $this->render('admin/camera_form.html.twig', [
            'camera' => $camera,
            'buildings' => $buildings,
            'is_edit' => true,
        ]);
    }

    #[Route('/logs', name: 'admin_logs')]
    public function logs(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->entityManager->getRepository(SnapshotLog::class)
            ->createQueryBuilder('sl')
            ->leftJoin('sl.camera', 'c')
            ->leftJoin('c.building', 'b')
            ->orderBy('sl.attemptedAt', 'DESC');

        $totalLogs = (clone $queryBuilder)->select('COUNT(sl.id)')->getQuery()->getSingleScalarResult();
        $logs = $queryBuilder->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
            'currentPage' => $page,
            'totalPages' => ceil($totalLogs / $limit),
            'totalLogs' => $totalLogs,
        ]);
    }

    #[Route('/snapshot/manual/{id}', name: 'admin_manual_snapshot', methods: ['POST'])]
    public function manualSnapshot(Camera $camera): Response
    {
        // TODO: Implement manual snapshot trigger
        $this->addFlash('info', 'Manual snapshot feature will be implemented with the snapshot service.');
        return $this->redirectToRoute('admin_cameras');
    }
}
