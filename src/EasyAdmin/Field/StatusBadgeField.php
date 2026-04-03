<?php

declare(strict_types=1);

namespace App\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A virtual EasyAdmin field that maps string/enum values to Bootstrap badge colors.
 *
 * Usage:
 *   StatusBadgeField::new('status')->setStatusMap([
 *       'pending'  => 'warning',
 *       'approved' => 'success',
 *       'rejected' => 'danger',
 *   ]);
 */
final class StatusBadgeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/status_badge.html.twig')
            ->setCustomOption('statusMap', []);
    }

    /**
     * @param array<string, string> $map Keys are status values, values are Bootstrap color names
     *                                   (primary, secondary, success, danger, warning, info, dark, light)
     */
    public function setStatusMap(array $map): self
    {
        $this->setCustomOption('statusMap', $map);

        return $this;
    }
}
