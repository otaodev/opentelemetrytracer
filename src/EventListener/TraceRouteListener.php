<?php

declare(strict_types=1);

namespace Otaodev\Opentelemetrytracer\EventListener;

use OpenTelemetry\API\Signals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use Otaodev\Opentelemetrytracer\Attribute\TraceRoute;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Otaodev\Opentelemetrytracer\Http\Response\ErrorJsonResponse;

class TraceRouteListener implements EventSubscriberInterface
{
    private TracerProvider $tracerProvider;
    private TracerInterface $tracer;
    private TransportInterface $transport;
    private SpanExporter $exporter;
    private SpanInterface $span;

    public function __construct()
    {
        $this->transport = $this->createTransport();
        $this->exporter = new SpanExporter($this->transport);

        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($this->exporter)
        );
    }

    public function __destruct()
    {
        if (isset($this->span)) {
            $this->span->end();
        }
        ShutdownHandler::register([$this->tracerProvider, 'shutdown']);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (is_array($controller)) {
            [$controllerInstance, $methodName] = $controller;

            $this->tracer = $this->tracerProvider->getTracer(
                (string) get_class($controllerInstance)
            );

            $reflectionMethod = new \ReflectionMethod($controllerInstance, $methodName);
            $traceRouteAttributes = $reflectionMethod->getAttributes(TraceRoute::class);
            if (count($traceRouteAttributes) > 0) {
                foreach ($traceRouteAttributes as $traceAttribute) {
                    $traceRouteName = $traceAttribute->newInstance()->getTraceRouteName() ?? $methodName;

                    $this->span = $this->tracer->spanBuilder($traceRouteName)->startSpan();

                    $this->span->setAttribute(TraceAttributes::CODE_FUNCTION, debug_backtrace()[0]['file'] ?? '');
                    $this->span->setAttribute(TraceAttributes::CODE_LINENO, debug_backtrace()[0]['line'] ?? 0);

                    $request = $event->getRequest();

                    $parameters = $reflectionMethod->getParameters();

                    $parameters = array_map(function (\ReflectionParameter $param) use ($request) {
                        $paramType = $param->getType();

                        if (Request::class === (string) $paramType) {
                            return $request;
                        }

                        return $request->attributes->get($param->getName());
                    }, $parameters);

                    try {
                        $response = $controllerInstance->$methodName(...$parameters);

                        if ($response instanceof ErrorJsonResponse) {
                            throw $response->getThrowable();
                        }
                    } catch (\Throwable $th) {
                        $this->span->setStatus(StatusCode::STATUS_ERROR, 'Something bad happened!');
                        $this->span->recordException(
                            $th,
                            [
                                'exception.escaped' => true,
                                'exception.code' => $th->getCode(),
                            ]
                        );
                    }
                }
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function getTracerProvider(): TracerProvider
    {
        return $this->tracerProvider;
    }

    private function createTransport(): TransportInterface
    {
        $endpoint = $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] . OtlpUtil::method(Signals::TRACE);
        return (new GrpcTransportFactory())->create($endpoint);
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }
}
