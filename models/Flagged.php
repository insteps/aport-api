<?php

use Phalcon\Mvc\Model;

class Flagged extends Model
{

    public function onConstruct()
    {
        // ...
    }

    public function initialize()
    {
        $this->setConnectionService('dbSqlite');
    }

    public function getSource()
    {
        return "flagged";
    }

}

