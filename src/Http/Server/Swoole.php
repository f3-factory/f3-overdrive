<?php

namespace F3\Http\Server;

use Dflydev\FigCookies\SetCookies;
use F3\Base;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Imefisto\PsrSwoole\ServerRequest as PsrRequest;
use Imefisto\PsrSwoole\ResponseMerger;

/**
 * Swoole Server Adapter
 */
class Swoole extends ServerAdapter {

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
            'worker_num' => swoole_cpu_num(),
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
//        Co::set(['hook_flags'=> SWOOLE_HOOK_ALL]);
        $this->http = new Server($this->host, $this->port);
        $this->http->set($settings = $this->getSettings());

        $this->http->on('start', function ($server) use($settings) {
            swoole_error_log(SWOOLE_LOG_INFO, sprintf("Swoole http server is started at http://%s:%s \n".
                'Swoole version: %s'.PHP_EOL.
                'Document root: %s'.PHP_EOL.
                "Workers: %d".PHP_EOL,
                $server->host, $server->port, swoole_version(),
                $settings['document_root'], $settings['worker_num']
            ));
        });

        $this->http->on("request", function (Request $swooleRequest, Response $swooleResponse)
        {
            call_user_func($this->onInitHandler);
            $fw = Base::instance();
            // convert swoole request to PSR7 server request
            $psr7Request = new PsrRequest(
                swooleRequest: $swooleRequest,
                uriFactory: $fw->make(UriFactoryInterface::class),
                streamFactory: $fw->make(StreamFactoryInterface::class),
                uploadedFileFactory: $fw->make(UploadedFileFactoryInterface::class)
            );
            try {
                $psr7Response = call_user_func($this->onRunHandler, $psr7Request);
                // swoole/open-swoole uses urlencode automatically,
                // which breaks encoding space chars (+ vs %20),
                // hence manual treatment with rawurlencode here
                $setCookies = SetCookies::fromSetCookieStrings($psr7Response->getHeader('Set-Cookie'));
                foreach ($setCookies->getAll() as $setCookie) {
                    $swooleResponse->rawcookie(
                        rawurlencode($setCookie->getName()),
                        rawurlencode($setCookie->getValue()),
                        $setCookie->getExpires(),
                        $setCookie->getPath(),
                        $setCookie->getDomain(),
                        $setCookie->getSecure(),
                        $setCookie->getHttpOnly(),
                        ($sameSite = $setCookie->getSameSite())
                            ? explode('=', strtolower($sameSite->asString()))[1]
                            : null
                    );
                }
                // ensure cookie header is removed, otherwise responseMerger will add it again
                $psr7Response = $psr7Response->withoutHeader('Set-Cookie');

                // send response
                $responseMerger = new ResponseMerger();
                $responseMerger->toSwoole(
                    $psr7Response,
                    $swooleResponse
                )->end();

            } catch (\Throwable $e) {
                swoole_error_log(SWOOLE_LOG_ERROR, $e->getMessage());
                $swooleResponse->end($e->getMessage());

            } finally {
                call_user_func($this->onEndHandler);
            }
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