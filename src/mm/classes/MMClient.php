<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMUtils;

class MMClient extends MMObj
{

    public function __construct()
    {
        parent::__construct();
        self::setColumnProperty(
            ['client_token'],
            'editable',
            false
        );
    }

    public function defineChildObjs()
    {
        if (!isset(self::$childObjs[$this->className])) {
            self::$childObjs[$this->className] = [
                'clientCtl' => [
                    'className'         =>  MM_CLASS_PATH . 'MMClientCtl',
                    'cardinality'       =>  'one',
                    'parameters'        =>  [ 'client_id', ],
                    'loading'           =>  'onRead',
                    'onDeleteCascade'   =>  true,
                    'onCreateCallback'  =>  'clientCtlInit',
                ],
            ];
        }
    }

    public function clientCtlInit()
    {
        $this->clientCtl->client_id = $this->client_id;
    }

    public function create()
    {
        $this->client_token = MMUtils::generateToken(20);
        $res = parent::create();
    }

    public function getClientFromToken($token)
    {
        $res = $this->readSingle("client_token = '$token'");
//      if($res) $this->read($this->client_id);
        return $this;
    }
}
