PHPRestService
==============

PHPRestService is a PHP class for developing RESTful applications and APIs.

Features
--------

+ Easy to use syntax
+ Regular Expression support
+ Error handling through PHP Exceptions
+ Parameter validation through PHP function signature
+ Can return a summary of all routes or one route through `OPTIONS` method based on PHPDoc (if `OPTIONS` is not overridden)
+ Support of `GET`, `POST`, `PUT`, `DELETE`, `PATCH`, `HEAD` and `OPTIONS`
+ Suppress the HTTP Status code with ?_suppress_status_code=1 (for clients that has troubles with that)
+ Supports ?_method=<method>
+ With auto-generation through PHP's `reflection`

Installation
------------

 - https://packagist.org/marcj/php-rest-service.
 - More information available under https://packagist.org/.

Requirements
------------

 - PHP 5.3 and above.
 - PHPUnit to execute the test suite.


Usage Demo
----------

### Way 1. The dirty & fast


```php

use RestService\Server;

Server::create('/')
    ->addGetRoute('test', function(){
        return 'Yay!';
    })
    ->addGetRoute('foo/(.*)', function($bar){
        return $bar;
    })
->run();

```

### Way 2. Auto-Collection

`index.php`:

```php

use RestService\Server;

Server::create('/admin', 'myRestApi\Admin')
    ->collectRoutes()
->run();

```

`MyRestApi/Admin.php`:

```php

namespace MyRestApi;

class Admin {

    /**
    * Checks if a user is logged in.
    *
    * @return boolean
    */
    public function getLoggedIn(){
        return $this->getContainer('auth')->isLoggedIn();
    }

    public function postLogin($username, $password){
        return $this->getContainer('auth')->doLogin($username, $password);
    }

}

```

Generates folling entry points:
```
    + GET  /admin/logged-in
    + POST /admin/login?username=&password=
```


### Way 3. Custom rules with controller

`index.php`:

```php

use RestService\Server;

Server::create('/admin', new MyRestApi\Admin) //base entry points `/admin`
    ->setDebugMode(true) //prints the debug trace, line number and file if a exception has been thrown.

    ->addGetRoute('login', 'doLogin') // => /admin/login
    ->addGetRoute('logout', 'doLogout') // => /admin/logout

    ->addGetRoute('page', 'getPages')
    ->addPutRoute('page', 'addPage')
    ->addGetRoute('page/([0-9]+)', 'getPage')
    ->addDeleteRoute('page/([0-9]+)', 'deletePage')
    ->addPostRoute('page/([0-9]+)', 'updatePage')

    ->addGetRoute('foo/bar/too', 'doFooBar')

    ->addSubController('tools', \RestApi\Tools) //adds a new sub entry point 'tools' => admin/tools
        ->addDeleteRoute('cache', 'clearCache')
        ->addGetRoute('rebuild-index', 'rebuildIndex')
    ->done()

->run();

```

`MyRestApi/Admin.php`:

```php

namespace MyRestApi;

class Admin {


    public function login($username, $password){

        if (!$this->validLogin($username, $password)
            throw new InvalidLoginException('Login is invalid or no access.');

        return $this->getToken();

    }

    public function logout(){

        if (!$this->hasSession()){
            throw new NoCurrentSessionException('There is no current session.');
        }

        return $this->killSession();

    }


    public function getPage($id){
        //...
    }

}

namespace RestAPI;

class Tools {

    /**
    * Clears the cache of the app.
    *
    * @param boolean $withIndex If true, it clears the search index too.
    * @return boolean True if the cache has been cleared.
    */
    public function clearCache($withIndex = false){
        return true;
    }

}
```


## Responses

The Response body is always a array (JSON per default) containing a status code and the actual data. If a exception has been thrown, it contains
the status 500, the exception class name as error and the message as message.

Some examples:

```

+ GET admin/login?username=foo&password=bar
  => {"status": "200", "data": true}

+ GET admin/login?username=foo&password=invalidPassword
  => {"status": "500", "error": "InvalidLoginException", "message": "Login is invalid or no access"}

+ GET admin/login
  => {"status: "400", "MissingRequiredArgumentException", "Argument 'username' is missing");

+ GET admin/login?username=foo&password=invalidPassword
  With active debugMode we'll get:
  => {"status": "500", "error": "InvalidLoginException", "message": "Login is invalid or no access",
      "line": 10, "file": "libs/RestAPI/Admin.class.php", "trace": <debugTrace>}

+ GET admin/tools/cache
  => {"status": 200, "data": true}


```

License
-------

Licensed under the MIT License. See the LICENSE file for more details.

Take a look into the code, to get more information about the possibilities. It's well documented.