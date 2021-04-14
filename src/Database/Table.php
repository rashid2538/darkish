<?php

namespace Darkish\Database;

use Darkish\Database\Db;
use Darkish\Component;
use Darkish\Helper;

abstract class Table extends Component
{

    protected $_name;
    protected Db $_context;

    public function getContext() {
        return $this->_context;
    }

    public function setContext(Db $context) {
        $this->_context = $context;
    }

    public function getTableName()
    {
        return $this->getConfig('db.prefix', '') . Helper::camelToUnderScore($this->_name);
    }
}
