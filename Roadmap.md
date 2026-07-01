- Firewall
  - matching requests/responses
    - req. body: regexp
    - req. body: jsonpath-like matching
    - document supported matchers
    - support other wildcards besides the `*`?
      - glob has: ? for one char, [...] for char ranges, [!...] for negated char ranges
      - sql LIKE has `%` and `_`
      - we could just allow full regexp instead, at least for char ranges...
      - for which matcher a 'literal' version is importamt? body, header, user_agent, query_string, ...
    - client_address: support v6 IPs without forcing users to write a complex regexp
    - other?
      - add a `/trim` modifier, similar to `/case_insensitive` and `/no_wildcards`
      - eg. ssl on
  - implement filtering support
      - check: can we make filters add "tags" to requests/responses, to ease later processing? See psr 'attributes'
  - create a flow diagram with req/resp matching and filtering
  - allow 'restart' as action for (Request) rules
    - allow setting a maxRestarts limit
    - q: should we remove from the current rule chain a rule, after it did trigger a restart? (possibly use 2 `restart` types?)
  - review: can we do the same (but better) as all the haproxy rules in NC-AIO haproxy.cfg?
  - xml req./resp. body with xpath/css matchers
  - allow failures of the MethodMatcher to generate a 501 response instead of the default 403?
  - API reworking:
    - clean up the `*MatcherInterface` mess: drop MatcherInterface; move Logic/* matchers to MessageInterface?
    - check: could we use the firewall filters to implement something like https://github.com/terrylinooo/shieldon instead
      of a waf to remote apps, or would it need some api changes?

- Proxy
  - finish support for `tcp://` upstreams
  - add by default (or via a filter?) the http headers telling upstream about real-ip and x-forwarded-protocol, patch hop-by-hop headers
    see fe. https://docs.google.com/document/d/1rJRV3s_Kto9_nx-ROjwG0ncA8JNeKz8xaaJXdrbJx7s/edit?pli=1&tab=t.0
  - tls & https support
  - take a look at supporting somehow https://github.com/php-http/client-common/blob/2.x/src/Plugin.php, so that
    we can allow users to profit from the existing plugins and/or vice-versa make our Firewall available as plugin...
    -> the firewall rules work off a ServerRequestInterface, not a RequestInterface. Some of those matchers _do_ need
       access to the extra info the former has over the latter, so we can not just replace our interfaces.
    _But_
    - we could adopt the PluginClient style of chaining plugins, if that makes it easier to implement async clients
    - could we implement adapters that wrap existing plugins and run them as either middlewares or filters?
      (either that or allow usage of PluginClient in a byoc scenario)
    - could we implement adapters that wrap the firewall into a plugin?
  - make it easy to implement a reverse proxy too + add tests + give examples on how to do that
  - add http client adapters for php-http/curl-client and other "well known" psr-18 http clients (there are eg. a plethora
    of them in httplug's client-common package. Including the PluginClient, which allows to add further processing to
    the request before it hits upstream)

- Docs
  - add config examples for common use-cases, eg. 'all readonly', 'redact secrets', 'inject headers', 'fix Host', etc...
    see fe. all cases listed at https://codingchallenges.fyi/challenges/challenge-forward-proxy/

- Loggers
  - improve message formatting: add context

- Testing
  - add tests which try to exploit issues in http parsers, see f.e. https://hostoftroubles.com/
    (will need an http client based on fsockopen rather than psr stuff)
  - on GH, run tests on a matrix of all supported php, ubuntu but also webserver versions
    - add one test using frankenphp worker mode
    - test also against: apache+mod_php, php-http-server, lighttpd, openlitespeed, roadrunner, swoole
      - use a cloud-based platform that provides those ready-built, rather than installing each one by ourselves?
        Either that, or move to a multi-container setup for testing...
      - we might give a strong preference to how frankenphp sets up $_SERVER, as that will be the default way to
        deploy a WAF based on this code (in the downstream YAWAF project)
      - swoole has built-in support for psr-15 (mapping an \OpenSwoole\HTTP\Request to a psr one, see https://github.com/openswoole/openswoole/blob/master/core/src/Helper.php)
  - add tests which make use of middleware from other projects, eg. rate-limiting and caching

- Misc
  - introduce more structured exceptions
  - allow fine-tuning resource usage: timeouts, maxconn, etc... (here on in downstream projects?)
