<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enables the school filter for authenticated users.
 */
class SchoolFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (! $user instanceof User) {
            return;
        }

        // Don't apply filter for super admins
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $school = $user->getSchool();
        if (! $school instanceof \App\Entity\School) {
            return;
        }

        $filter = $this->entityManager->getFilters()->enable('school_filter');
        $filter->setParameter('school_id', $school->getId());
    }
}
