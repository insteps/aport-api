<?php

use Phalcon\Mvc\Model;


class Repoversion extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "repoversion";
    }

}

