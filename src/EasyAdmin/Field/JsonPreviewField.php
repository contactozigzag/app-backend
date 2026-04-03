<?php

declare(strict_types=1);

namespace App\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A virtual EasyAdmin field that renders a JSON value as a collapsible <pre><code> block.
 * Collapsed by default, expanded on click via the json-preview Stimulus controller.
 */
final class JsonPreviewField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/json_preview.html.twig');
    }
}
