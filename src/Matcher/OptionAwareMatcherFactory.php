<?php

namespace YAWAF\Core\Matcher;

/**
 * Implements functionality common to Matcher factories:
 * - optional postfixes to the matcher type
 * - matcher with options
 */
abstract class OptionAwareMatcherFactory extends SuffixedMatcherFactory
{
    // NB: should not conflict with $this->matcherTypeSuffixRegexp
    protected string $optionSeparatorChar = '/';

    protected function getMatcherType(string $type): string
    {
        $type = parent::getMatcherType($type);
        return str_contains($type, $this->optionSeparatorChar) ? strstr($type, $this->optionSeparatorChar, true) : $type;
    }

    /**
     * Splits the type string based on $this->optionSeparatorChar, looking for boolean options.
     * @param bool[] $options key: name of the option, value: bool. The default value to be returned. The value will be
     *                        flipped in the returned array when the option is present in the type string
     * @return bool[] an array with the same keys as $options
     * @throws \Exception
     */
    protected function parseMatcherBooleanOptions(string $type, array $options): array
    {
        // remove a suffix such as `:1`, which can be used to obviate to key uniqueness issues
        $typeWithOptions = parent::getMatcherType($type);
        $data = explode($this->optionSeparatorChar, $typeWithOptions);
        $out = $options;
        for ($i = 1; $i < count($data); $i++) {
            $optionName = $data[$i];
            if (!array_key_exists($optionName, $options)) {
                throw new \Exception("Matcher modifier '{$this->optionSeparatorChar}{$optionName}' is not supported");
            }
            $out[$optionName] = !$options[$optionName];
        }
        return $out;
    }
}
