<?php

namespace Darkish;

class Dummy implements \ArrayAccess
{

    protected $_properties = [];

    public function __get($prop)
    {
        return isset($this->_properties[$prop]) ? $this->_properties[$prop] : '';
    }

    public function __set($prop, $val)
    {
        $this->_properties[$prop] = $val;
    }

    public function __call($func, $args)
    {
        $prop = 'unknown';
        if ($func == 'get' && isset($args[0])) {
            $prop = $args[0];
        } else if (substr($func, 0, 3) == 'get') {
            $prop = lcfirst(substr($func, 3));
        }
        return $this->$prop;
    }

    public function offsetExists($offset)
    {
        return isset($this->_properties[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->_properties[$offset]) ? $this->_properties[$offset] : null;
    }

    public function offsetSet($offset, $value)
    {
        $this->_properties[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->_properties[$offset]);
    }
}
