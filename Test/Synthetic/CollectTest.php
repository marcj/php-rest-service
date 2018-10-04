<?php

namespace Test\Synthetic;

use RestService\Server;
use Test\Controller\MyRoutes;

class CollectTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Server
     */
    private $restService;

    public function setUp()
    {
        $this->restService = Server::create('/', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->collectRoutes();
    }
    public function testNonPhpDocMethod()
    {
        $response = $this->restService->simulateCall('/method-without-php-doc', 'get');
        $this->assertEquals('{
    "status": 200,
    "data": "hi"
}', $response);
    }

    public function testUrlAnnotation()
    {
        $response = $this->restService->simulateCall('/stats', 'get');
        $this->assertEquals('{
    "status": 200,
    "data": "Stats for 1"
}', $response);

        $response = $this->restService->simulateCall('/stats/23', 'get');
        $this->assertEquals('{
    "status": 200,
    "data": "Stats for 23"
}', $response);

    }

    public function testOwnController()
    {

        $response = $this->restService->simulateCall('/login', 'post');

        $this->assertEquals('{
    "status": 400,
    "error": "MissingRequiredArgumentException",
    "message": "Argument \'username\' is missing."
}', $response);

        $response = $this->restService->simulateCall('/login?username=bla', 'post');

        $this->assertEquals('{
    "status": 400,
    "error": "MissingRequiredArgumentException",
    "message": "Argument \'password\' is missing."
}', $response);

        $response = $this->restService->simulateCall('/login?username=peter&password=pwd', 'post');

        $this->assertEquals('{
    "status": 200,
    "data": true
}', $response);

        $response = $this->restService->simulateCall('/login?username=peter&password=pwd', 'get');

        $this->assertEquals('{
    "status": 400,
    "error": "RouteNotFoundException",
    "message": "There is no route for \'login\'."
}', $response);

    }

}
