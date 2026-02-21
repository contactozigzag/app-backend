<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Filters entities by school context for multi-tenant isolation.
 */
final readonly class SchoolContextProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (! $user instanceof User) {
            return $operation->canRead() ? $this->getProvider($operation)->provide($operation, $uriVariables, $context) : null;
        }

        // Add school filter to context for entities that have a school relationship
        if ($user->getSchool() && ! $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $context['filters'] = array_merge((array) ($context['filters'] ?? []), [
                'school' => $user->getSchool()->getId(),
            ]);
        }

        return $this->getProvider($operation)->provide($operation, $uriVariables, $context);
    }

    private function getProvider(Operation $operation): ProviderInterface
    {
        return $operation->canRead()
            ? ($operation->getCollection() ? $this->collectionProvider : $this->itemProvider)
            : $this->itemProvider;
    }
}
