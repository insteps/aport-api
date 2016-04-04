<?php

use Phalcon\Mvc\Model;


class Maintainer extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "maintainer";
    }

    public function getMaintainerByIds($ids) # a comma separated list
    {
        return "maintainer";
    }

    public function populateMaintainer()
    {
        return "maintainer";
    }

}

