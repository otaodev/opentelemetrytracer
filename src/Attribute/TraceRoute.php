<?php

declare(strict_types=1);

namespace Otaodev\Opentelemetrytracer\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class TraceRoute
{
    public function __construct(private ?string $name = null)
    {
    }

    public function getTraceRouteName(): string|null
    {
        return $this->name;
    }
}
