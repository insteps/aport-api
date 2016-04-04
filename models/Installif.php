<?php

use Phalcon\Mvc\Model;


class Installif extends Model
{

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "install_if";
    }

}

