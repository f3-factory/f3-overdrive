<?php

namespace F3\Http\Server;

use F3\Base;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

/**
 * RoadRunner Server Adapter
 */
class RoadRunner extends ServerAdapter {

    public function start(): void {

        call_user_func($this->onInitHandler);
        $fw = Base::instance();

        $worker = Worker::create();
        $psr7 = new PSR7Worker(
            worker: $worker,
            requestFactory: $fw->make(RequestFactoryInterface::class),
            streamFactory: $streamFactoy = $fw->make(StreamFactoryInterface::class),
            uploadsFactory: $fw->make(UploadedFileFactoryInterface::class)
        );
        $responseFactory = $fw->make(ResponseFactoryInterface::class);

        while (TRUE) {
            try {
                $request = $psr7->waitRequest();
                if ($request===NULL) {
                    break;
                }
            } catch (\Throwable $e) {
                $psr7->respond($responseFactory->createResponse(400));
                continue;
            }

            try {
                $psr7Response = call_user_func($this->onRunHandler, $request);
                $psr7->respond($psr7Response);

            } catch (\Throwable $e) {
                $psr7->respond($responseFactory
                    ->createResponse(500)
                    ->withBody($streamFactoy->createStream($e->getMessage()))
                );
                $psr7->getWorker()->error((string) $e);
            } finally {
                call_user_func($this->onEndHandler);
            }
        }
    }
}