<?php

namespace Test\Synthetic;

use RestService\Server;
use Test\Controller\MyRoutes;

class CustomRoutesTest extends \PHPUnit_Framework_TestCase
{

    public function testOwnController()
    {
        $restService = Server::create('/', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->addPostRoute('login', 'postLogin');

        $response = $restService->simulateCall('/login', 'post');

        $this->assertEquals('{
    "status": 400,
    "error": "MissingRequiredArgumentException",
    "message": "Argument \'username\' is missing."
}', $response);

        $response = $restService->simulateCall('/login?username=bla', 'post');

        $this->assertEquals('{
    "status": 400,
    "error": "MissingRequiredArgumentException",
    "message": "Argument \'password\' is missing."
}', $response);

        $response = $restService->simulateCall('/login?username=peter&password=pwd', 'post');

        $this->assertEquals('{
    "status": 200,
    "data": true
}', $response);

        $response = $restService->simulateCall('/login?username=peter&password=pwd', 'get');

        $this->assertEquals('{
    "status": 400,
    "error": "RouteNotFoundException",
    "message": "There is no route for \'login\'."
}', $response);

    }

    public function testOwnControllerWithDifferentPrefix()
    {
        $restService = Server::create('/v1', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->addPostRoute('login', 'postLogin');

        $response = $restService->simulateCall('/v1/login?username=peter&password=pwd', 'post');

        $this->assertEquals('{
    "status": 200,
    "data": true
}', $response);

    }
}
