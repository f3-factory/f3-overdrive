<?php

namespace F3\Http\Server;

use F3\Base;
use F3\Web;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

abstract class ServerAdapter {

    protected ?\Closure $onInitHandler = null;
    protected ?\Closure $onRunHandler = null;
    protected ?\Closure $onEndHandler = null;

    abstract public function start(): void;

    public function onInit(callable $handler): void
    {
        $this->onInitHandler = $handler;
    }

    public function onRun(callable $handler): void
    {
        $this->onRunHandler = $handler;
    }

    public function onEnd(callable $handler): void
    {
        $this->onEndHandler = $handler;
    }

    public function onBeforeRun(): void
    {
        // overwrite if needed
    }

    /**
     * if HTTP server adapter has no own static file provider,
     * you can use this as fallback
     */
    public function registerStaticFileProvider(): void
    {
        Base::instance()->route('GET|HEAD /*', function(ServerRequestInterface $request, ResponseInterface $response, StreamFactoryInterface $streamFactory) {
            $path = str_replace('..','', $request->getUri()->getPath());
            if ($filepath = realpath(__DIR__.$path)) {
                return $response
                    ->withHeader('Content-Type', Web::instance()->mime($filepath))
                    ->withBody($streamFactory->createStreamFromResource(fopen($filepath, 'r')));
            }
            return $response->withStatus(404);
        });
    }
}