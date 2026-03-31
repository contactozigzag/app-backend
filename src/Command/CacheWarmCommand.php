<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\School;
use App\Service\GoogleMapsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * Pre-populates critical caches after deployment.
 *
 * Run via deploy.sh after migrations, before switching traffic to the new slot:
 *   docker exec zigzag_php_<slot> php bin/console app:cache:warm
 *
 * This ensures the first requests after a deploy hit warm caches instead of
 * cold databases, preventing a latency spike when traffic cuts over.
 *
 * Warming strategy:
 *  - Doctrine result cache: run expensive queries so the result cache is populated
 *  - MP fee rate: prime cache.mp_fees so the first payment doesn't compute cold
 *  - Geocoding: prime cache.geo for all school addresses (most-queried locations)
 */
#[AsCommand(
    name: 'app:cache:warm',
    description: 'Pre-populate critical Redis cache pools after deployment',
)]
class CacheWarmCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'cache.mp_fees')]
        private readonly CacheInterface $mpFeesCache,
        private readonly GoogleMapsService $googleMapsService,
        #[Autowire(env: 'float:MERCADOPAGO_MARKETPLACE_FEE_PERCENT')]
        private readonly float $marketplaceFeePercent,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ZigZag Cache Warmup');

        $this->warmMpFeeRate($io);
        $this->warmSchoolAddresses($io);
        $this->warmDoctrineResultCache($io);

        $io->success('Cache warmup complete.');

        return Command::SUCCESS;
    }

    private function warmMpFeeRate(SymfonyStyle $io): void
    {
        $io->section('MP fee rate (cache.mp_fees)');

        try {
            $feePercent = $this->marketplaceFeePercent;
            $this->mpFeesCache->get(
                'mp_fee_rate_immediate_ars',
                static function (ItemInterface $item) use ($feePercent): float {
                    $item->expiresAfter(21600); // 6 hours

                    return $feePercent / 100.0;
                },
            );
            $io->writeln('  ✓ mp_fee_rate_immediate_ars primed');
        } catch (Throwable $throwable) {
            $io->warning('MP fee rate warm failed: ' . $throwable->getMessage());
        }
    }

    private function warmSchoolAddresses(SymfonyStyle $io): void
    {
        $io->section('School addresses (cache.geo)');

        /** @var School[] $schools */
        $schools = $this->em->getRepository(School::class)->findAll();

        $warmed = 0;
        foreach ($schools as $school) {
            $address = $school->getAddress();
            if ($address === null) {
                continue;
            }

            if ($address->getStreetAddress() === null) {
                continue;
            }

            // Build a geocodable string from stored address components.
            // The first call after deploy hits Google Maps API and caches for 365 days;
            // subsequent calls (and future deploys) are served from Redis.
            $addressString = implode(', ', array_filter([
                $address->getStreetAddress(),
                $address->getCity(),
                $address->getState(),
                $address->getCountry(),
            ]));

            try {
                $this->googleMapsService->geocodeAddress($addressString);
                $warmed++;
            } catch (Throwable $e) {
                $io->warning(sprintf('  Geocode failed for school %d: %s', $school->getId() ?? 0, $e->getMessage()));
            }
        }

        $io->writeln(sprintf('  ✓ %d school address(es) geocoded', $warmed));
    }

    private function warmDoctrineResultCache(SymfonyStyle $io): void
    {
        $io->section('Doctrine result cache (active routes & drivers)');

        try {
            // Run queries that use enableResultCache so their results are pre-cached.
            // These are the same queries state providers call on first request.
            $activeRouteCount = $this->em->getRepository(Route::class)
                ->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->enableResultCache(300, 'warm_active_routes_count')
                ->getSingleScalarResult();

            $activeDriverCount = $this->em->getRepository(Driver::class)
                ->createQueryBuilder('d')
                ->select('COUNT(d.id)')
                ->getQuery()
                ->enableResultCache(600, 'warm_active_drivers_count')
                ->getSingleScalarResult();

            $io->writeln(sprintf('  ✓ %d active route(s), %d driver(s) counts cached', $activeRouteCount, $activeDriverCount));
        } catch (Throwable $throwable) {
            $io->warning('Doctrine result cache warm failed: ' . $throwable->getMessage());
        }
    }
}
