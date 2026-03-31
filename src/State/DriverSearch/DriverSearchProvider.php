<?php

declare(strict_types=1);

namespace App\State\DriverSearch;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Service\OpenSearch\DriverSearchHit;
use App\Service\OpenSearch\DriverSearchResult;
use App\Service\OpenSearch\DriverSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * API Platform provider for driver search.
 *
 * Falls back to a basic Doctrine LIKE query if OpenSearch is unavailable.
 *
 * @implements ProviderInterface<object>
 */
final readonly class DriverSearchProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 10;

    private const int MAX_LIMIT = 20;

    public function __construct(
        private DriverSearchService $driverSearchService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.driver_search')]
        private RateLimiterFactory $driverSearchLimiter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (! $request instanceof Request) {
            return $this->emptyResponse(1, self::DEFAULT_LIMIT);
        }

        /** @var User $user */
        $user = $this->security->getUser();

        // Rate limiting: 30 req / 10 sec per user
        $limiter = $this->driverSearchLimiter->create('driver_search_' . $user->getId());
        $limit = $limiter->consume();

        if (! $limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            throw new TooManyRequestsHttpException(
                (string) ($retryAfter->getTimestamp() - time()),
            );
        }

        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $itemsPerPage = min(self::MAX_LIMIT, max(1, $request->query->getInt('itemsPerPage', self::DEFAULT_LIMIT)));

        // Short queries return empty results (autocomplete UX — no error while typing)
        if (mb_strlen($query) < 2) {
            return $this->emptyResponse($page, $itemsPerPage);
        }

        $schoolId = $this->resolveSchoolId($request, $user);

        // Try OpenSearch first, fall back to Doctrine on failure
        $result = $this->searchOpenSearch($query, $schoolId, $page, $itemsPerPage);

        if (! $result instanceof DriverSearchResult) {
            $result = $this->searchDoctrine($query, $schoolId, $page, $itemsPerPage);
        }

        // Set Cache-Control header for autocomplete
        $request->attributes->set('_api_cache_control', 'private, max-age=5');

        return $this->formatResponse($result);
    }

    private function searchOpenSearch(string $query, int $schoolId, int $page, int $limit): ?DriverSearchResult
    {
        try {
            // If OpenSearch returned results (even empty), it's working
            return $this->driverSearchService->search($query, $schoolId, $page, $limit);
        } catch (Exception $exception) {
            $this->logger->warning('OpenSearch unavailable, falling back to database search', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve the school ID from the request.
     *
     * School admins use their own school. Parents must pass a `school` query parameter
     * and must have at least one child enrolled in that school.
     */
    private function resolveSchoolId(Request $request, User $user): int
    {
        // School admins always search within their own school
        $adminSchool = $user->getSchool();

        if ($adminSchool instanceof School) {
            return (int) $adminSchool->getId();
        }

        // Parents must specify a school
        $schoolParam = $request->query->get('school');

        if (! is_numeric($schoolParam)) {
            throw new BadRequestHttpException('The "school" query parameter is required.');
        }

        $schoolId = (int) $schoolParam;

        // Validate that the parent has at least one child in this school
        $hasChild = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Student::class, 's')
            ->join('s.parents', 'p')
            ->where('p = :user')
            ->andWhere('s.school = :schoolId')
            ->setParameter('user', $user)
            ->setParameter('schoolId', $schoolId)
            ->getQuery()
            ->getSingleScalarResult();

        if ($hasChild === 0) {
            throw new AccessDeniedHttpException('You do not have access to this school.');
        }

        return $schoolId;
    }

    /**
     * Fallback: basic Doctrine LIKE query.
     * Finds drivers assigned to routes in the given school.
     * Uses prefix LIKE only (value%) for B-tree index utilization.
     */
    private function searchDoctrine(string $query, int $schoolId, int $page, int $limit): DriverSearchResult
    {
        $queryLower = mb_strtolower($query) . '%';
        $offset = ($page - 1) * $limit;

        // Disable SchoolFilter — driver users don't have school_id set;
        // we filter by school via Route join instead.
        $filters = $this->entityManager->getFilters();

        if ($filters->isEnabled('school_filter')) {
            $filters->disable('school_filter');
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('d', 'u')
            ->from(Driver::class, 'd')
            ->join('d.user', 'u')
            ->join(Route::class, 'r', 'WITH', 'r.driver = d AND r.school = :schoolId')
            ->andWhere(
                'LOWER(d.nickname) LIKE :query OR LOWER(u.firstName) LIKE :query OR LOWER(u.lastName) LIKE :query OR u.identificationNumber LIKE :queryRaw',
            )
            ->setParameter('schoolId', $schoolId)
            ->setParameter('query', $queryLower)
            ->setParameter('queryRaw', $query . '%')
            ->groupBy('d.id')
            ->addGroupBy('u.id')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $drivers = $qb->getQuery()->getResult();
        $hits = [];

        /** @var Driver $driver */
        foreach ($drivers as $driver) {
            $user = $driver->getUser();
            $hits[] = new DriverSearchHit(
                driverId: (int) $driver->getId(),
                nickname: $driver->getNickname() ?? '',
                firstName: $user?->getFirstName() ?? '',
                lastName: $user?->getLastName() ?? '',
                identificationNumber: $user?->getIdentificationNumber() ?? '',
                score: 0.0,
            );
        }

        return new DriverSearchResult($hits, \count($hits), $page, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResponse(int $page, int $limit): array
    {
        return [
            'results' => [],
            'total' => 0,
            'page' => $page,
            'itemsPerPage' => $limit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResponse(DriverSearchResult $result): array
    {
        $results = array_map(
            static fn (DriverSearchHit $hit): array => [
                'driverId' => $hit->driverId,
                'nickname' => $hit->nickname,
                'firstName' => $hit->firstName,
                'lastName' => $hit->lastName,
                'identificationNumber' => $hit->identificationNumber,
                'score' => $hit->score,
            ],
            $result->results,
        );

        return [
            'results' => $results,
            'total' => $result->total,
            'page' => $result->page,
            'itemsPerPage' => $result->limit,
        ];
    }
}
