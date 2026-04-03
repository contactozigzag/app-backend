<?php

declare(strict_types=1);

namespace App\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A virtual EasyAdmin field that renders an integer (0–100) as a Bootstrap progress bar.
 * Typically used to display route completion percentage.
 */
final class ProgressBarField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/progress_bar.html.twig');
    }
}
