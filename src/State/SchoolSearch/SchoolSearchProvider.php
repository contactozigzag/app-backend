<?php

declare(strict_types=1);

namespace App\State\SchoolSearch;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\School;
use App\Service\OpenSearch\SchoolSearchHit;
use App\Service\OpenSearch\SchoolSearchResult;
use App\Service\OpenSearch\SchoolSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

/**
 * API Platform provider for school search.
 *
 * Schools are not tenant-scoped — any authenticated user may search all schools
 * (parents need to find schools when linking children; admins manage globally).
 *
 * Falls back to a basic Doctrine LIKE query if OpenSearch is unavailable.
 *
 * @implements ProviderInterface<object>
 */
final readonly class SchoolSearchProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 10;

    private const int MAX_LIMIT = 20;

    public function __construct(
        private SchoolSearchService $schoolSearchService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.school_search')]
        private RateLimiterFactory $schoolSearchLimiter,
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

        $user = $this->security->getUser();
        $limiter = $this->schoolSearchLimiter->create('school_search_' . ($user?->getUserIdentifier() ?? 'anon'));
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

        if (mb_strlen($query) < 2) {
            return $this->emptyResponse($page, $itemsPerPage);
        }

        $result = $this->searchOpenSearch($query, $page, $itemsPerPage);

        if (! $result instanceof SchoolSearchResult) {
            $result = $this->searchDoctrine($query, $page, $itemsPerPage);
        }

        // Short cache for autocomplete — private so Cloudflare does not share across users
        $request->attributes->set('_api_cache_control', 'private, max-age=5');

        return $this->formatResponse($result);
    }

    private function searchOpenSearch(string $query, int $page, int $limit): ?SchoolSearchResult
    {
        try {
            return $this->schoolSearchService->search($query, $page, $limit);
        } catch (Throwable $throwable) {
            $this->logger->warning('OpenSearch unavailable, falling back to database search', [
                'exception' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fallback: prefix LIKE query on school name.
     *
     * Uses `value%` (not `%value%`) so PostgreSQL can use the B-tree index on
     * the `name` column. SchoolFilter does not apply to School itself.
     */
    private function searchDoctrine(string $query, int $page, int $limit): SchoolSearchResult
    {
        $offset = ($page - 1) * $limit;
        $pattern = mb_strtolower($query) . '%';

        $schools = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(School::class, 's')
            ->where('LOWER(s.name) LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $hits = array_map(
            static fn (School $s): SchoolSearchHit => new SchoolSearchHit(
                schoolId: (int) $s->getId(),
                name: $s->getName() ?? '',
                city: $s->getAddress()?->getCity() ?? '',
                score: 0.0,
            ),
            $schools,
        );

        return new SchoolSearchResult($hits, count($hits), $page, $limit);
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
    private function formatResponse(SchoolSearchResult $result): array
    {
        $results = array_map(
            static fn (SchoolSearchHit $hit): array => [
                'schoolId' => $hit->schoolId,
                'name' => $hit->name,
                'city' => $hit->city,
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
