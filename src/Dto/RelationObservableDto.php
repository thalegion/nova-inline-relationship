<?php

declare(strict_types=1);

namespace KirschbaumDevelopment\NovaInlineRelationship\Dto;

final class RelationObservableDto
{
    public array $items;

    public array $callbacks;

    public function __construct(array $items, array $callbacks)
    {
        $this->items = $items;
        $this->callbacks = $callbacks;
    }
}
