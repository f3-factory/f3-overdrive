<?php

namespace F3\Http\Server;

use F3\Base;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use OpenSwoole\Http\Server;
use OpenSwoole\Core\Psr\Request;

/**
 * Open Swoole Server Adapter
 */
class OpenSwoole extends ServerAdapter {

    protected array $settings = [];
    protected ?Server $http = null;

    public function __construct(
        protected string $host = '0.0.0.0',
        protected int $port = 9501,
        /**
         * can be used to overwrite the used framework PORT,
         * used for clients, reroutes, link rendering
         */
        protected ?int $external_port = null,
    ) {
        //
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function getSettings(): array
    {
        return $this->settings + [
            'enable_static_handler' => true,
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'worker_num' => \OpenSwoole\Util::getCPUNum(),
            'max_wait_time' => 3,
            'max_request' => 1000,
            'reload_async' => true,
            /**
             * TODO: cannot use coroutines feature as long as $_SESSION global and session_* functions are used
             * @see https://openswoole.com/article/isolating-variables-with-coroutine-context
             */
            'enable_coroutine' => false,
        ];
    }

    public function start(): void
    {
        $this->http = new Server($this->host, $this->port);
        $this->http->set($settings = $this->getSettings());

        $this->http->on('start', function (Server $server) use($settings) {
            error_log(sprintf("Open-Swoole http server started at http://%s:%s".PHP_EOL.
                'Document root: %s'.PHP_EOL.
                "Workers: %d".PHP_EOL,
                $server->host, $server->port,
                $settings['document_root'], $settings['worker_num']
            ), E_USER_NOTICE);
        });

        $this->http->handle(function(Request $request) {

            $request = $request->withServerParams(
                array_change_key_case($request->getServerParams() ?? [], CASE_UPPER)
            );
            call_user_func($this->onInitHandler);
            try {
                $psr7Response = call_user_func($this->onRunHandler, $request);
            } catch (\Throwable $e) {
                $fw = Base::instance();
                $responseFactory = $fw->make(ResponseFactoryInterface::class);
                $streamFactoy = $fw->make(StreamFactoryInterface::class);
                $psr7Response = $responseFactory
                    ->createResponse(500)
                    ->withBody($streamFactoy->createStream($e->getMessage()));
            } finally {
                call_user_func($this->onEndHandler);
            }
            return $psr7Response;
        });

        $this->http->start();
    }

    public function onBeforeRun(): void
    {
        if ($this->external_port) {
            $fw = Base::instance();
            $fw->PORT = $this->external_port;
        }
    }

    public function reload(): bool
    {
        return $this->http->reload();
    }

}