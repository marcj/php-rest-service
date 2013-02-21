<?php

namespace Test\Controller;

class MyRoutes
{
    /**
     * @param  string $username
     * @param  string $password
     * @return bool
     */
    public function postLogin($username, $password)
    {
        return $username == 'peter' && $password == 'pwd';
    }

}
