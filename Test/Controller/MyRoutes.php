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

    /**
     * @param string $server
     * @url stats/([0-9]+)
     * @url stats
     * @return string
     */
    public function getStats($server = '1')
    {
        return sprintf('Stats for %s', $server);
    }


    public function getMethodWithoutPhpDoc()
    {
        return 'hi';
    }

}
