<?php

use Phalcon\Mvc\Model;


class Provides extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "provides";
    }

}

