<?php

namespace Darkish;

abstract class Helper
{

    static function slugToCamel($str)
    {
        return str_replace(' ', '', lcfirst(ucwords(str_replace('-', ' ', $str))));
    }

    static function camelToUnderScore($str)
    {
        $result = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $char = substr($str, $i, 1);
            $result .= strtolower($char) == $char ? $char : '_' . strtolower($char);
        }
        return $result;
    }

    static function makeKeys( $arrOrObj, $column ) {
        $result = [];
        foreach( $arrOrObj as $val ) {
            $result[ is_array( $val ) ? $val[ $column ] : $val->$column ] = $val;
        }
        return $result;
    }
}
