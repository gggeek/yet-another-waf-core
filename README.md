# Yet Another Web API Firewall - core

A php library for building Web API Firewalls - and other assorted http proxies.

Primary finished-product targets are forward proxies for filtering the requests and responses of calls to HTTP APIs to only allow
what you want to expose.

Example use-cases:
- reducing the surface of an API, eg. only allowing READ requests or access to specific URLs
- removing sensitive data from an API responses
- adding/modifying/removing http headers
- tracing of requests and responses
- rate limiting (not implemented yet, but should be implementable with existing components from other packages)
- caching (not implemented yet, but should be implementable with existing components from other packages)

## Work In Progress

See the [Roadmap](Roadmap.md) for features not yet implemented

Not in scope (yet?):
- a GUI
- dispatching requests to different upstream backends based on conditions
- filtering request/response bodies other than Json
- feature parity with Varnish or performance parity with HAProxy
- using async requests to connect to upstream servers

## Requirements:

PHP 8.2 and up.

Either `symfony/http-client` or `guzzlehttp/guzzle`.

## Installation

Via Composer: `composer require gggeek/yet-another-waf-core:dev-main`

Then install either `symfony/http-client` or `guzzlehttp/guzzle`.

## Usage

More examples will come...

For the moment, seeee projects https://github.com/gggeek/yet-another-docker-socket-proxy and
https://github.com/gggeek/yet-another-waf as examples.

Or take a look at the Proxy used for the unit testing suite in `./tests/public`

## Design principles

1. Security first. No requests are allowed by default, everything has to be whitelisted.
2. Ease of use. Error messages should be clear and rather verbose than cryptic. Logging facilities should be extensive.
   Ambiguous configuration should be rejected.
3. Flexibility. The proxies should be easy to configure for common scenarios and extend to achieve uncommon ones.
   A Docker image shall be provided to get started running a "whitelabel" Firewall with no fuss.
4. Stability. No API breackage allowed after version 1.0 is released. Strict adherence to semantic versioning.
5. Performance. Maximum speed of execution and minimum cpu usage / memory usage are _important_. But not the main concern:
   safety, robustness and flexibility come first.
6. Versatility. Proxies and Firewalls built on this library should work the same regardless of the webserver used to
   run PHP, be it Apache, Nginx, FrankenPHP or something else. The library should interoperate seamlessly with 3rd-party
   components readily available in the php ecosystem.

Which translates into:
- PHP 8.2 and up
- strict typing everywhere
- using DI patterns as much as possible
- using the PSR-7, PSR-15, PSR-18 interfaces means it should be easy to extend/embed the Proxy classes in other middlewares
- avoid relying on too many, big dependencies - f.e. no Monolog, Symfony ConfigTreeBuilder
- delegate all possible processing to a 'bootstrap' phase, so that the processing loop can be as efficient as possible
  when used in eg. `worker` mode with FrankenPHP
- taking care about memory leaks
- prefer end-to-end testing to unit testing, as the specific webserver used to run php does have an impact on the
  processing by the YAWAF code of http requests, esp. the ones which are not conforming to the http standard

## Testing

Given the non-trivial set of configuration required to carry out end-to-end tests, the recommended setup is to use
the provided docker-based stack to run the test suite

```shell
./tests/ci/vm.sh build
./tests/ci/vm.sh start
./tests/ci/vm.sh runtests
./tests/ci/vm.sh stop
```

## FAQ

...

## License

Use of this software is subject to the terms in the [LICENSE](LICENSE) file


[![License](https://poser.pugx.org/gggeek/yet-another-waf-core/license)](https://packagist.org/packages/gggeek/yet-another-waf-core)
[![Latest Stable Version](https://poser.pugx.org/gggeek/yet-another-waf-core/v/stable)](https://packagist.org/packages/gggeek/yet-another-waf-core)
[![Total Downloads](https://poser.pugx.org/gggeek/yet-another-waf-core/downloads)](https://packagist.org/packages/gggeek/yet-another-waf-core)

[![Build Status](https://github.com/gggeek/yet-another-waf-core/actions/workflows/ci.yaml/badge.svg)](https://github.com/gggeek/yet-another-waf-core/actions/workflows/ci.yaml)
[![Code Coverage](https://codecov.io/github/gggeek/yet-another-waf-core/branch/main/graph/badge.svg)](https://app.codecov.io/github/gggeek/yet-another-waf-core)
