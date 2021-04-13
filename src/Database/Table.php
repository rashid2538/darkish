<?php

namespace Darkish\Database;

use Darkish\Component;
use Darkish\Helper;

abstract class Table extends Component
{

    protected $_name;

    public function getTableName()
    {
        return $this->getConfig('db.prefix', '') . Helper::camelToUnderScore($this->_name);
    }
}
