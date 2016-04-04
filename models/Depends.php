<?php

use Phalcon\Mvc\Model;


class Depends extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "depends";
    }

}

