<?php

namespace PayWithAmazon;

class ArrayUtil
{
    public static function trimArray($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::trimArray($value);
            } elseif (is_string($value) && $key !== 'proxy_password') {
                $array[$key] = trim($value);
            }
        }
        return $array;
    }
}
