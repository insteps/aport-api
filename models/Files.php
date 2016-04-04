<?php

use Phalcon\Mvc\Model;


class Files extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "files";
    }

}

