<?php

namespace YAWAF\Core\Matcher;

trait OptionAwareMatcherFactoryTrait
{
    protected function parseMatcherType(string $type): array
    {
        // remove a suffix such as `:1`, which can be used to obviate to key uniqueness issues
        $type = strtolower(preg_replace('/:[0-9]+$/', '', trim($type)));

        $data = explode('/', $type);
        $out = ['type' => $data[0], 'caseInsensitive' => false, 'expandWildcards' => true];
        for($i = 1; $i < count($data); $i++) {
            switch ($data[$i]) {
                case 'case_insensitive':
                case 'insensitive':
                    $out['caseInsensitive'] = true;
                    break;
                case 'exact':
                case 'exact_match':
                    $out['expandWildcards'] = false;
                    break;
                default:
                    throw new \Exception("Matcher modifier '/{$data[$i]}' is not supported");
            }
        }
        return $out;
    }
}
