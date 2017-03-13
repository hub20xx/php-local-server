# PHP Local Server: A class to manage PHP's built-in server

[![Build Status](https://travis-ci.org/hub20xx/php-local-server.svg)](https://travis-ci.org/hub20xx/php-local-server)

You're on linux and you want a simple way to start and stop a local server.

## Install

Via Composer

```bash
$ composer require hub20xxx/php-local-server
```

## Usage

```php
require __DIR__ . '/vendor/autoload.php';

$docroot = __DIR__ . '/www';

// by default the server is running on 127.0.0.1:1111
$server = new PhpLocalServer\Server($docroot);

// you can specify the address and port
// in the constructor
$address = '127.0.0.2';
$port = '1234';
$server = new Server($docroot, $address, $port);

// or using the setters
$server = new Server($docroot);
$server->setAddress($address);
$server->setPort($port);

// setting environment variables
$server->setEnvironmentVariable('ENV', 'testing');
$server->setEnvironmentVariable('FOO', 'bar');

// starting the server
$server->start();

// using the server

// stopping the server
$server->stop();

// $server->stop() is called in the destructor in case you omit to stop the server
```

## Testing

```bash
$ phpunit
```

## License

[MIT](LICENSE.md)

## Credits / Thanks

This package was inpired by [this blog post](http://tech.vg.no/2013/07/19/using-phps-built-in-web-server-in-your-test-suites/)

Many thanks :)

## Contributing

If you'd like to contribute, please use Github (issues, pull requests etc).
