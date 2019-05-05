<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class MMEnvironmentUser extends MMObj
{

    public function getUserEnvironments($id)
    {
        return $this->readMulti("user_id = $id");
    }
}
