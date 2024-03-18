# opentelemetrytracer
A basic use for the opentelemetry tracer in php applications based in symfony framework

## Instalation

```shell
composer require otaodev/opentelemetrytracer
```

Add the snippet to your app/config/services.yaml
```yaml
services:
    Otaodev\Opentelemetrytracer\EventListener\TraceRouteListener:
        public: true
        autowire: true
        autoconfigure: true
```

Add the environments variables to your .env file

```environment
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=YourServiceAppName
OTEL_TRACES_EXPORTER=console
OTEL_METRICS_EXPORTER=none
OTEL_LOGS_EXPORTER=none

OTEL_PROPAGATORS=baggage,tracecontext
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT="http://the-collector-ip:4317"
```

## Utilization
use the php8 attribute #[TraceRoute()] in your desired route, for example:
```php
<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Otaodev\Opentelemetrytracer\Attribute\TraceRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/', name: 'default_')]
class DefaultController extends AbstractController
{

    #[Route('/healthcheck', methods: ['GET'])]
    #[TraceRoute('any_route_name')]
    public function index(): Response
    {
        $return = $this->json('black tests is ON!');

        return $return;
    }
}
```
---
**NOTE**

If you not pass a name, the TraceRouter will assume the method name.

---