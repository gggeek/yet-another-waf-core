<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Request;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\MatcherInterface;
use YAWAF\Core\Matcher\Message\BodyMatcher;
use YAWAF\Core\Matcher\Message\ContentTypeMatcher;
use YAWAF\Core\Matcher\Message\HeaderMatcher;
use YAWAF\Core\Matcher\OptionAwareMatcherFactoryTrait;

class MatcherFactory implements MatcherFactoryInterface
{
    use LoggerAwareTrait;
    use OptionAwareMatcherFactoryTrait;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    public function supports(string $type): bool
    {
        $type = strtolower(preg_replace('/:[0-9]+$/', '', trim($type)));
        return in_array($type, ['body', 'client_address', 'client_port', 'content_type', 'host', 'http_header', 'http_method', 'query_string', 'url_path', 'user_agent']);
    }

    /**
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        $target = $this->parseMatcherType($type);
        switch($target['type']) {
            case 'body':
                $matcher = new BodyMatcher($values, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'client_address':
                /// @todo throw if $target['caseInsensitive'] is used
                $matcher = new ClientAddressMatcher($values, $target['expandWildcards']);
                break;
            case 'client_port':
                /// @todo throw if $target['caseInsensitive'] is used
                $matcher = new ClientPortMatcher($values, $target['expandWildcards']);
                break;
            case 'content_type':
                /// @todo throw if $target['caseInsensitive'] is used
                $matcher = new ContentTypeMatcher($values, $target['expandWildcards']);
                break;
            case 'host':
                $matcher = new HostMatcher($values, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'http_header':
                if (!is_array($values) || count($values) !== 1) {
                    throw new \Exception("Invalid request matching configuration: '$type' should be followed with an object with 1 element only");
                }
                $hv = reset($values);
                $hn = array_key_first($values);
                if (!is_string($hn) || !(is_string($hv) || is_array($hv))) {
                    throw new \Exception("Invalid request matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                }
                $matcher = new HeaderMatcher($hn, $hv, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'http_method':
                /// @todo throw if $target['expandWildcards'] or $target['caseInsensitive'] are used
                $matcher = new MethodMatcher($values);
                break;
            /// @todo...
            //case 'port':
            //    $matcher = ...;
            //    break;
            case 'query_string':
                if (!is_array($values) || count($values) !== 1) {
                    throw new \Exception("Invalid request matching configuration: '$type' should be followed with an object with 1 element only");
                }
                $qsv = reset($values);
                $qsn = array_key_first($values);
                if (!is_string($qsn) || !(is_string($qsv) || is_array($qsv))) {
                    throw new \Exception("Invalid request matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                }
                $matcher = new QueryStringMatcher($qsn, $qsv, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'url_path':
                $matcher = new PathMatcher($values, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'user_agent':
                $matcher = new UserAgentMatcher($values, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            default:
                throw new \Exception("Invalid request matching configuration: '$type' => " . var_export($values, true));
        }
        if ($this->logger && $matcher instanceof LoggerAwareInterface) {
            $matcher->setLogger($this->logger);
        }
        return $matcher;
    }
}
