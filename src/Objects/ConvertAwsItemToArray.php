<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-05
 * Time: 10:20
 */

namespace Broadway\EventStore\DynamoDb\Objects;

use Aws\ResultInterface;

class ConvertAwsItemToArray
{
    private static array $keyMap = ['S', 'SS', 'N', 'NS', 'B', 'BS'];

    /**
     * @param ResultInterface|array|null $item
     */
    public static function convert($item): ?array
    {
        if (empty($item)) {
            return null;
        }

        $converted = [];
        foreach ($item as $k => $v) {
            $keyFound = false;
            foreach (self::$keyMap as $key) {
                if (isset($v[$key])) {
                    $converted[$k] = $v[$key];
                    $keyFound = true;
                }
            }

            if (!$keyFound) {
                throw new \Exception('Not implemented type');
            }
        }

        return $converted;
    }
}
