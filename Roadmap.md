- Firewall
  - matching requests/responses
    - test using and/or/not; body; http_header, query_string; user_agent; content_type; status_code matchers
	- urls: optionally accommodate a prefix such as /vXXX/ transparently -> ok, test
	- urls: test forbidding any qs element, via wildcards
	- req. body: wildcard (ok?), literal, regexp, jsonpath-like matching
    - document the wildcard matching format
    - also, support other wildcards besides the `*`?
      - glob has: ? for one char, [...] for char ranges, [!...] for negated char ranges
      - sql LIKE has `%` and `_`
      - we could just allow full regexp instead, at least for char ranges...
      - for which matcher a 'literal' version is importamt? body, header, user_agent, query_string, ...
    - client_address: support v6 IPs without forcing users to write a complex regexp
    - other? eg. ssl on
    - review: can we do the same (but better) as all the haproxy rules in NC-AIO haproxy.cfg?
  - implement and test filtering support
  - create a flow diagram with req/resp matching and filtering
  - clean up the `*MatcherInterface` mess: drop MatcherInterface; move Logic/* matchers to MessageInterface?
  - allow 'restart' as action for (Request) rules
  - allow failures of the MethodMatcher to generate a 501 response instead of the default 403?
  - can we make filters add "tags" to requests/responses?

- Proxy
  - test: support for `unix:/` and `tcp://` sockets, `https://` upstreams
  - add by default the http headers telling upstream about real-ip and real-protocol, patch hop-by-hop headers
    see fe. https://docs.google.com/document/d/1rJRV3s_Kto9_nx-ROjwG0ncA8JNeKz8xaaJXdrbJx7s/edit?pli=1&tab=t.0
  - allow users to specify preference for curl vs socket implementations for the client to upstream
    (note that native client does not support using the `bindto` option with unix sockets)
  - tls & https support
  - review: can/should we drop `*FilterInterface` in favour of the PSR equivalent?
  - make it easy to implement a reverse proxy + give examples on how to do that

- add config examples for common use-cases, eg. 'all readonly', 'redact secrets', 'fix Host', etc...
  see fe. all cases listed at https://codingchallenges.fyi/challenges/challenge-forward-proxy/

- Loggers
  - improve message formatting: add context

- Testing
  - bring back code coverage injection of code executed via http requests, without installing phpunit-selenium
  - on GH, run tests on a matrix of various php / ubuntu / webserver versions (finish apache vhost setup 1st)

- allow fine-tuning resource usage: timeouts, maxconn, etc... (here on in downstream projects?)
