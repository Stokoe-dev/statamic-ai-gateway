<?php

namespace Stokoe\AiGateway\Support;

class FieldFilter
{
    /**
     * Filter an associative array by removing all keys on the deny list.
     *
     * @param  array  $data          The data to filter.
     * @param  array  $deniedFields  List of field names to remove.
     * @return array  The filtered data with denied fields removed.
     */
    public function filter(array $data, array $deniedFields): array
    {
        if (empty($deniedFields)) {
            return $data;
        }

        $denySet = array_flip($deniedFields);

        return array_diff_key($data, $denySet);
    }
}
