# PHPRestService

## What?

PHPRestService is a PHP class for developing RESTful applications and APIs.

## Features

+ Easy to use syntax
+ Regular Expression support
+ Error handling through PHP Exceptions
+ Parameter validation through PHP function definition
+ Describes all routes or one route through method OPTIONS based on PHPDoc (if OPTIONS is not overridden)
+ Support of GET, POST, PUT, DELETE, HEAD, OPTIONS
+ Suppress the HTTP Status code with ?_suppress_status_code=1 (for clients that has troubles with that)

## Usage

```php

//Setup the routing

\RestService\Server::create('admin', \RestApi\Admin) //entry points 'admin'
    ->setDebugMode(true) //prints the debug trace, line number and file if a exception has been thrown.

    ->addGetRoute('login', 'doLogin') // => admin/login
    ->addGetRoute('logout', 'doLogout') // => admin/logout

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

//Setup the controller classes

namespace RestApi;

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

Responses are always hashes, that contains a status code and the data. If a exception has been thrown, it contains
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

## Method describing function

You can call each method or a entry point with method OPTIONS to get some descriptions about it.

```
+ OPTIONS admin/tools/cache
  => {
         "status": 200,
         "data": {
             "parameters": {
                 "_method": {
                     "description": "Can be used as HTTP METHOD if the client does not support HTTP methods.",
                     "type": "string",
                     "values": "GET, POST, PUT, DELETE, HEAD, OPTIONS"
                 },
                 "_suppress_status_code": {
                     "description": "Suppress the HTTP status code.",
                     "type": "boolean",
                     "values": "1, 0"
                 }
             },
             "controller": {
                 "entryPoint": "admin\/tools",
                 "routes": {
                     "login": {
                         "uri": "admin\/tools\/cache",
                         "methods": {
                             "DELETE": {
                                 "description": "Clears the cache of the app.",
                                 "parameters": {
                                     "withIndex": {
                                         "type": "boolean",
                                         "description": "If true, it clears the search index too.",
                                         "required": false
                                     }
                                 },
                                 "return": {
                                     "type": "boolean",
                                     "description": "True if the cache has been cleared."
                                 }
                             }
                         }
                     }
                 }
             }
         }
     }

```


Take a look into the code, to get more information about the possibilities. It's well documented.