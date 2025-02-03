F3 Overdrive
---

Level up your Fat-Free application's performance with F3-Overdrive to boot your application by a high-performance HTTP application server reactor.
The application is started once and then kept in memory to serve requests with lightning speeds. 

Power-Ups included:

- Swoole
- Open-Swoole
- RoadRunner

# Installation

Install F3 Overdrive via composer:

```
composer require f3-factory/f3-overdrive
```

## Requirements

1. It is required to enable `CONTAINER` in your app:
    ```php
    $f3->CONTAINER = \F3\Service::instance();
    ```
2. This plugin requires the [F3 PSR7 Factory package](https://github.com/f3-factory/fatfree-psr7-factory). You need to initialize it in your app as well.
3. You need to install the extension or support binary of one of the following http application server adapters: 

## Swoole

For using F3 Overdrive with [swoole](https://wiki.swoole.com/en/#/), you need to have the `swoole` php extension installed.
There are several ways to install it, based on what your setup it:

- via PECL:  
  `pecl install swoole`
- or using Docker image   
  `phpswoole/swoole`
- or using [Docker Php Extension Installer](https://github.com/mlocati/docker-php-extension-installer):    
    ```
    COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
    RUN install-php-extensions swoole
    ```

You also need to require these composer packages:

```
composer require imefisto/psr-swoole-native
composer require swoole/ide-helper
```

## Open-Swoole

For using [OpenSwoole](https://openswoole.com/), you need the `openswoole` php extension to be installed.
Install it via:

- PECL:  
  `pecl install openswoole`
- or using [Docker Php Extension Installer](https://github.com/mlocati/docker-php-extension-installer):
    ```
    COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
    RUN install-php-extensions openswoole
    ```
  
You also need to require these composer packages:

```
composer require openswoole/core:^22.1.5
composer require openswoole/ide-helper:^22.1.5
```

## RoadRunner

For using [RoadRunner](https://roadrunner.dev/), you need the roadrunner binary to be installed on your server.

Please see the [official roadrunner docs](https://docs.roadrunner.dev/docs/general/install#docker) about the installation within Docker or other ways.

Additionally, you need to install these composer packages:

```
composer require spiral/roadrunner-cli
composer require spiral/roadrunner-http
```


# Usage

To start your application server, you need to rewire your traditional F3 app a little bit.

First you need to wrap your app configuration, that you usually have in your `index.php` by implementing the `F3\Overdrive\AppInterface`:

**Before: index.php **

```php
require __DIR__.'/vendor/autoload.php';

$f3 = F3\Base::instance();

$f3->route('GET /', function() {
    echo 'Hallo World';
});
$f3->run();
```

Goes into: `app/App.php` or elsewhere:
```php
namespace App;

use F3\Base;
use F3\Overdrive\AppInterface;

class App implements AppInterface {

    public function init(): void
    {
        $f3 = Base::instance();
        
        // init DI container
        $f3->CONTAINER = \F3\Service::instance();

        // init PSR7 support
        \F3\Http\MessageFactory::registerDefaults();
        
        $f3->route('GET /', function() {
            echo 'Hallo World';
        });
    }
    
    public function run(): void
    {
        Base::instance()->run();
    }
}
```

In your `index.php` you initialise your app with overdrive:

```php
require __DIR__.'/vendor/autoload.php';

use F3\Http\Server\RoadRunner;

$overdrive = new F3\Overdrive(
    app: App\App::class,
    with: new RoadRunner()
);
$overdrive->run();
```

The process is the same for all included adapters:

- `F3\Http\Server\RoadRunner`
- `F3\Http\Server\OpenSwoole`
- `F3\Http\Server\Swoole`


# Developer notes

- don't use global variables
- do not write static vars or singleton classes
- don't mess around with header() or session_* functions yourself. Use the framework tools instead

This is super important to reduce the case of memory leaks, or unexpected behaviours.
