<?php

declare(strict_types=1);

namespace Otaodev\Opentelemetrytracer\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

class ErrorJsonResponse extends JsonResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        private \Throwable $throwable,
        array $headers = []
    ) {
        $code = $throwable->getCode() >= 100 && $throwable->getCode() <= 599 ? $throwable->getCode() : 500;
        parent::__construct($throwable->getMessage(), $code, $headers);
    }

    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }
}
