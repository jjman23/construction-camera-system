<?php
// src/Controller/MikrotikController.php
namespace App\Controller;

use App\Entity\Building;
use App\Entity\MikrotikDevice;
use App\Service\MikrotikService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/mikrotik')]
#[IsGranted('ROLE_ADMIN')]
class MikrotikController extends AbstractController
{
    private const RANGES = ['24h' => '-24 hours', '7d' => '-7 days', '30d' => '-30 days', '90d' => '-90 days'];

    public function __construct(
        private EntityManagerInterface $em,
        private MikrotikService $mikrotik,
    ) {}

    #[Route('', name: 'admin_mikrotik')]
    public function index(): Response
    {
        $devices = $this->em->getRepository(MikrotikDevice::class)->findBy([], ['name' => 'ASC']);
        $latest = [];
        foreach ($devices as $d) {
            $latest[$d->getId()] = $this->mikrotik->latest($d);
        }

        return $this->render('admin/mikrotik/index.html.twig', [
            'devices' => $devices,
            'latest' => $latest,
        ]);
    }

    #[Route('/new', name: 'admin_mikrotik_new')]
    public function new(Request $request): Response
    {
        $device = new MikrotikDevice();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mikrotik_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('admin_mikrotik_new');
            }
            $this->applyForm($device, $request, true);
            $this->em->persist($device);
            $this->em->flush();

            $this->addFlash('success', "MikroTik \"{$device->getName()}\" added. Use “Poll now” to test the connection.");
            return $this->redirectToRoute('admin_mikrotik_show', ['id' => $device->getId()]);
        }

        return $this->render('admin/mikrotik/form.html.twig', [
            'device' => $device,
            'is_edit' => false,
            'buildings' => $this->buildings(),
        ]);
    }

    #[Route('/{id}', name: 'admin_mikrotik_show', requirements: ['id' => '\d+'])]
    public function show(MikrotikDevice $device, Request $request): Response
    {
        $range = $request->query->get('range', '24h');
        if (!isset(self::RANGES[$range])) {
            $range = '24h';
        }
        $history = $this->mikrotik->history($device, new \DateTime(self::RANGES[$range]));

        $pacific = new \DateTimeZone('America/Los_Angeles');
        $points = [];
        foreach ($history as $s) {
            $t = (clone $s->getPolledAt())->setTimezone($pacific);
            $points[] = [
                't' => $t->format('Y-m-d\TH:i:s'),
                'status' => $s->getStatus(),
                'signal' => $s->getSignalStrength(),
                'txSignal' => $s->getTxSignalStrength(),
                'snr' => $s->getSignalToNoise(),
                'txCcq' => $s->getTxCcq(),
                'rxCcq' => $s->getRxCcq(),
                'txRate' => $s->getTxRateMbps(),
                'rxRate' => $s->getRxRateMbps(),
            ];
        }

        $total = count($history);
        $failed = count(array_filter($history, fn($s) => $s->getStatus() !== 'ok'));

        return $this->render('admin/mikrotik/show.html.twig', [
            'device' => $device,
            'latest' => $this->mikrotik->latest($device),
            'points' => $points,
            'range' => $range,
            'ranges' => array_keys(self::RANGES),
            'availability' => $total ? round(100 * ($total - $failed) / $total, 1) : null,
            'pollCount' => $total,
            'recent' => array_slice(array_reverse($history), 0, 20),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_mikrotik_edit', requirements: ['id' => '\d+'])]
    public function edit(MikrotikDevice $device, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mikrotik_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('admin_mikrotik_edit', ['id' => $device->getId()]);
            }
            $this->applyForm($device, $request, false);
            $this->em->flush();

            $this->addFlash('success', 'MikroTik updated.');
            return $this->redirectToRoute('admin_mikrotik_show', ['id' => $device->getId()]);
        }

        return $this->render('admin/mikrotik/form.html.twig', [
            'device' => $device,
            'is_edit' => true,
            'buildings' => $this->buildings(),
        ]);
    }

    #[Route('/{id}/poll', name: 'admin_mikrotik_poll', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function poll(MikrotikDevice $device, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('poll_mikrotik_' . $device->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
        } else {
            $this->mikrotik->poll($device);
            $this->em->flush();

            match ($device->getLastStatus()) {
                'ok' => $this->addFlash('success', 'Poll succeeded.'),
                'no_link' => $this->addFlash('warning', 'Connected, but the station is not associated with an AP.'),
                default => $this->addFlash('error', 'Poll failed: ' . $device->getLastError()),
            };
        }

        $back = $request->request->get('back') === 'index' ? 'admin_mikrotik' : 'admin_mikrotik_show';
        return $this->redirectToRoute($back, $back === 'admin_mikrotik' ? [] : ['id' => $device->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_mikrotik_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(MikrotikDevice $device, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_mikrotik_' . $device->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_mikrotik');
        }

        $name = $device->getName();
        $this->em->remove($device);
        $this->em->flush();

        $this->addFlash('success', "MikroTik \"{$name}\" and its history have been deleted.");
        return $this->redirectToRoute('admin_mikrotik');
    }

    private function applyForm(MikrotikDevice $device, Request $request, bool $isNew): void
    {
        $r = $request->request;
        $buildingId = $r->get('building_id');

        $device->setName(trim((string) $r->get('name')))
            ->setHost(trim((string) $r->get('host')))
            ->setUseSsl((bool) $r->get('use_ssl'))
            ->setPort((int) ($r->get('port') ?: ($r->get('use_ssl') ? 8729 : 8728)))
            ->setUsername(trim((string) $r->get('username')))
            ->setWirelessInterface(trim((string) $r->get('wireless_interface')))
            ->setNotes(trim((string) $r->get('notes')))
            ->setBuilding($buildingId ? $this->em->getRepository(Building::class)->find($buildingId) : null)
            ->setActive($isNew || (bool) $r->get('active'));

        // Blank password on edit keeps the existing one.
        $password = (string) $r->get('password');
        if ($isNew || $password !== '') {
            $device->setPassword($password);
        }
    }

    /** @return Building[] */
    private function buildings(): array
    {
        return $this->em->getRepository(Building::class)->findBy([], ['displayOrder' => 'ASC', 'name' => 'ASC']);
    }
}
