<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Entity\Route;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wraps the default API Platform persist processor for POST /api/active_routes.
 *
 * After persisting the new ActiveRoute, materializes one ActiveRouteStop per
 * active RouteStop on the route template. This is what makes the trip
 * actually trackable for parents:
 *
 *  - The Mercure parent-token endpoint checks ActiveRoute.stops to authorize
 *    the parent's subscription (src/Controller/MercureController.php).
 *  - ProximityEvaluationHandler / GeofencingService / AttendanceController
 *    all read ActiveRouteStop rows to drive arriving/picked-up/dropped-off
 *    transitions.
 *
 * Without this materialization step the parent tracking screen shows
 * "no children to deliver" and the proximity / push pipeline never fires.
 *
 * Idempotent: if the caller already attached stops (e.g. tests, admin form),
 * we leave them alone and skip materialization.
 *
 * @implements ProcessorInterface<ActiveRoute, ActiveRoute>
 */
final readonly class ActiveRouteCreateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<ActiveRoute, ActiveRoute> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ActiveRoute
    {
        /** @var ActiveRoute $activeRoute */
        $activeRoute = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Idempotency: if the caller already provided stops, don't second-guess them.
        if (! $activeRoute->getStops()->isEmpty()) {
            return $activeRoute;
        }

        $template = $activeRoute->getRouteTemplate();

        if (! $template instanceof Route) {
            return $activeRoute;
        }

        $created = 0;

        foreach ($template->getStops() as $templateStop) {
            if (! $templateStop->getIsActive()) {
                continue;
            }

            $student = $templateStop->getStudent();
            $address = $templateStop->getAddress();
            // Skip incomplete template rows rather than failing the trip start.
            if ($student === null) {
                continue;
            }

            if ($address === null) {
                continue;
            }

            $stop = new ActiveRouteStop();
            $stop->setActiveRoute($activeRoute);
            $stop->setStudent($student);
            $stop->setAddress($address);
            $stop->setStopOrder($templateStop->getStopOrder() ?? 0);
            $stop->setGeofenceRadius($templateStop->getGeofenceRadius());
            $stop->setNotes($templateStop->getNotes());
            $stop->setEstimatedArrivalTime($templateStop->getEstimatedArrivalTime());

            $activeRoute->addStop($stop);
            $this->entityManager->persist($stop);
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('ActiveRouteCreateProcessor: stops materialized', [
            'activeRouteId' => $activeRoute->getId(),
            'routeTemplateId' => $template->getId(),
            'stopsCreated' => $created,
        ]);

        return $activeRoute;
    }
}
