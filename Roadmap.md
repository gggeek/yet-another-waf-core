- Firewall
  - matching requests/responses
    - test using and/or/not machers; body matcher; http_header matcher
    - implement the todos in Rule class
	- urls: optionally accommodate a prefix such as /vXXX/ transparently -> ok, test
	- urls: optionally accommodate `?aaa` and `#bbb` transparently -> check if the default is to match the whole url incl. qs and anchor
	- urls: query string
	- req. body: literal, wildcard (ok?), regexp, jsonpath-like matching
    - resp. status code
    - content-type, for both req and resp
    - document the wildcard matching format
    - also, support other wildcards besides the `*`?
      - glob has: ? for one char, [...] for char ranges, [!...] for negated char ranges
      - sql LIKE has `%` and `_`
      - we could just allow full regexp instead, at least for char ranges...
    - client_address: support v6 IPs
    - other? eg. ssl on
    - review: can we do the same (but better) as all the haproxy rules in NC-AIO haproxy.cfg?
  - finish and test filtering support
  - create a flow diagram with req/resp matching and filtering
  - clean up the `*MatcherInterface` mess: drop MatcherInterface; move Logic/* matchers to MessageInterface?
  - allow 'restart' as action for (Request) rules

- Proxy
  - test: support for `tcp:/` sockets, `http:/`, `https:/` upstreams
  - tls & https support
  - review: can/should we drop `*FilterInterface` in favour of the PSR equivalent?

- add config examples for common use-cases, eg. 'all readonly', 'redact secrets', 'fix Host', etc...

- Loggers
  - improve message formatting: add context

- add a testsuite

- allow fine-tuning resource usage: timeouts, maxconn, etc... (here on in downstream projects?)
