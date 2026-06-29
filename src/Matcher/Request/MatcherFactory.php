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

class MatcherFactory implements MatcherFactoryInterface
{
    use LoggerAwareTrait;

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
     * @param string $type
     * @param mixed $values
     * @return MatcherInterface
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        $target = strtolower(preg_replace('/:[0-9]+$/', '', trim($type)));
        switch($target) {
            case 'body':
                $matcher = new BodyMatcher($values);
                break;
            case 'client_address':
                $matcher = new ClientAddressMatcher($values);
                break;
            case 'client_port':
                $matcher = new ClientPortMatcher($values);
                break;
            case 'content_type':
                $matcher = new ContentTypeMatcher($values);
                break;
            case 'host':
                $matcher = new HostMatcher($values);
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
                $matcher = new HeaderMatcher($hn, $hv);
                break;
            case 'http_method':
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
                $matcher = new QueryStringMatcher($qsn, $qsv);
                break;
            case 'url_path':
                $matcher = new PathMatcher($values);
                break;
            case 'user_agent':
                $matcher = new UserAgentMatcher($values);
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
