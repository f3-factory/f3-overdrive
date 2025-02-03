<?php

namespace F3;

use F3\Http\Server\ServerAdapter;
use F3\Overdrive\AppInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Overdrive {

    protected ServerAdapter $adapter;
    protected AppInterface $app;

    /**
     * @var $app class-string<AppInterface>
     */
    protected string $appClass;
    protected string $cwd;

    /**
     * @param class-string<AppInterface> $app
     * @param ServerAdapter $with
     */
    public function __construct(
        string $app,
        ServerAdapter $with
    ) {
        // initialize pre-server start in order to load $_SERVER defaults
        // NB: do NOT bind the framework instance to a property
        Base::instance();
        if (!\is_a($app, AppInterface::class, true)) {
            throw new \RuntimeException('App class must implement OverdriveApp');
        }
        $this->appClass = $app;
        $this->adapter = $with;
    }

    public function run(): void
    {
        $this->adapter->onInit($this->onInit(...));
        $this->adapter->onRun($this->onRun(...));
        $this->adapter->onEnd($this->onEnd(...));
        $this->adapter->start();
    }

    protected function bootApp(): AppInterface
    {
        return new $this->appClass();
    }

    protected function onInit(): void
    {
        // ensure all global instances are wiped
        Registry::reset();
        // force creation of new framework instance
        $fw = Base::instance();
        // this enables internal reactor mode
        $fw->NONBLOCKING = TRUE;

        // bind the HTTP server to the hive, in case the app needs it to manage itself
        $fw->set('SERVER_APP', $this->adapter);

        // initialize application
        $this->app = $this->bootApp();
        $this->app->init();

        // ensure GLOBALS sync is disabled (it would technically even work with sync,
        // but there's a risk of leaking information through requests, hence don't rely on this)
        $fw->desync();
        $this->cwd = \getcwd();

        // settings
        $fw->QUIET = true; // don't echo any output directly
        $fw->CLI = false;  // needed for the router and other parts to behave correctly
        $fw->HALT = FALSE; // do not exit the script on finish or failure
    }

    /**
     * handle the actual execution of the request router
     * @throws \Throwable
     */
    protected function onRun(ServerRequestInterface $psr7Request): ResponseInterface
    {
        $fw = Base::instance();
        return $fw->handle($psr7Request, function() use ($fw) {
            $this->adapter->onBeforeRun();
            return $fw->run();
        });
    }

    /**
     * handle end of request, use for clean up
     * @return void
     */
    protected function onEnd(): void
    {
        Base::instance()->unload($this->cwd);
    }
}