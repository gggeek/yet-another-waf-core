# Yet Another Web API Firewall - core

A php library for building Web API Firewalls.

Aka. small forward proxies for filtering the requests and responses of calls to HTTP APIs to only allow what you want
to expose.

Example use-cases:
- reducing the surface of an API, eg. only allowing READ requests or access to specific URLs
- removing sensitive data from an API responses
- rate limiting
- adding/modifying/removing http headers
- tracing of requests and responses

## Work In Progress

See the [Roadmap](Roadmap.md) for features not yet implemented

Not in scope (yet?):
- a GUI
- routing requests to multiple upstream backends
- filtering request/response bodies other than Json
- feature parity with Varnish or performance parity with HAProxy

## Installation

Via Composer: `composer require gggeek/yet-another-waf-core:dev-main`

## Usage

See projects https://github.com/gggeek/yet-another-docker-socket-proxy and https://github.com/gggeek/yet-another-waf
as examples.

## Design principles

1. Security first. No requests are allowed by default, everything has to be whitelisted.
2. Ease of use. Error messages should be clear and rather verbose than cryptic. Logging facilities should be extensive.
   Ambiguous configuration should be rejected.
3. Flexibility. The proxies should be easy to configure for common scenarios and extend to achieve uncommon ones
4. Performance. Maximum speed of execution and minimum cpu usage / memory usage are _important_. But not the main concern,
   safety, robustness and flexibility come first.

Which translates into:
- PHP 8.2 and up
- strict typing everywhere
- using DI patterns as much as possible
- using the psr-7, psr-15, psr-18 interfaces means it should be easy to extend/embed the Proxy class in other middlewares
- avoid relying on too many dependencies - f.e. no Monolog, Symfony ConfigTreeBuilder
- delegate all possible processing to a 'bootstrap' phase, so that the processing loop can be as efficient as possible
  when used in eg. `worker` mode with FrankenPHP

## FAQ

...

## License

Use of this software is subject to the terms in the [LICENSE](LICENSE) file


[![License](https://poser.pugx.org/gggeek/yet-another-waf-core/license)](https://packagist.org/packages/gggeek/yet-another-waf-core)
[![Latest Stable Version](https://poser.pugx.org/gggeek/yet-another-waf-core/v/stable)](https://packagist.org/packages/gggeek/yet-another-waf-core)
[![Total Downloads](https://poser.pugx.org/gggeek/yet-another-waf-core/downloads)](https://packagist.org/packages/gggeek/yet-another-waf-core)

[![Build Status](https://github.com/gggeek/yet-another-waf-core/actions/workflows/ci.yaml/badge.svg)](https://github.com/gggeek/yet-another-waf-core/actions/workflows/ci.yaml)
[![Code Coverage](https://codecov.io/gh/gggeek/yet-another-waf-core/branch/master/graph/badge.svg)](https://app.codecov.io/gh/gggeek/yet-another-waf-core)
