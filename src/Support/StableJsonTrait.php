<?php

declare(strict_types=1);

namespace BrainCore\Support;

trait StableJsonTrait
{
    /**
     * Sort array keys and nested arrays recursively for stable JSON output.
     */
    protected function stabilizeArray(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    sort($value);
                    $data[$key] = array_map(fn($item) => is_array($item) ? $this->stabilizeArray($item) : $item, $value);
                } else {
                    $data[$key] = $this->stabilizeArray($value);
                }
            }
        }

        return $data;
    }
}
