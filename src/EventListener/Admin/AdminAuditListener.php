<?php

declare(strict_types=1);

namespace App\EventListener\Admin;

use App\Entity\AdminAuditLog;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AdminAuditListener
{
    /**
     * @var list<AdminAuditLog>
     */
    private array $pending = [];

    private bool $isFlushing = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (! $this->isAuditable($entity)) {
            return;
        }

        $this->pending[] = $this->createLog($entity, 'create', null);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (! $this->isAuditable($entity)) {
            return;
        }

        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
        $changes = $this->formatChangeSet($changeSet);

        $this->pending[] = $this->createLog($entity, 'update', $changes ?: null);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (! $this->isAuditable($entity)) {
            return;
        }

        $this->pending[] = $this->createLog($entity, 'delete', null);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isFlushing || $this->pending === []) {
            return;
        }

        $this->isFlushing = true;
        $em = $args->getObjectManager();

        foreach ($this->pending as $log) {
            $em->persist($log);
        }

        $this->pending = [];
        $em->flush();
        $this->isFlushing = false;
    }

    private function isAuditable(object $entity): bool
    {
        if ($entity instanceof AdminAuditLog) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();

        return $request instanceof Request && str_starts_with($request->getPathInfo(), '/admin');
    }

    /**
     * @param array<string, list<string>>|null $changes
     */
    private function createLog(object $entity, string $action, ?array $changes): AdminAuditLog
    {
        $log = new AdminAuditLog();
        $log->setEntityType(new ReflectionClass($entity)->getShortName());
        $log->setEntityId($this->resolveEntityId($entity));
        $log->setAction($action);
        $log->setChanges($changes);

        $user = $this->security->getUser();
        if ($user instanceof UserInterface && method_exists($user, 'getEmail')) {
            $log->setAdminEmail((string) $user->getEmail());
        }

        return $log;
    }

    private function resolveEntityId(object $entity): string
    {
        if (method_exists($entity, 'getId')) {
            return (string) $entity->getId();
        }

        return spl_object_id($entity) . '(unsaved)';
    }

    /**
     * @param array<string, mixed> $changeSet
     *
     * @return array<string, list<string>>
     */
    private function formatChangeSet(array $changeSet): array
    {
        $result = [];

        foreach ($changeSet as $field => $change) {
            if (! is_array($change)) {
                continue;
            }

            if (! array_key_exists(0, $change)) {
                continue;
            }

            if (! array_key_exists(1, $change)) {
                continue;
            }

            $result[$field] = [
                $this->formatValue($change[0]),
                $this->formatValue($change[1]),
            ];
        }

        return $result;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            if (method_exists($value, 'getId')) {
                $rawId = $value->getId();
                $id = is_scalar($rawId) ? (string) $rawId : '?';
            } else {
                $id = '?';
            }

            return new ReflectionClass($value)->getShortName() . '#' . $id;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
