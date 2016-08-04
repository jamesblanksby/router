<?php

namespace Blanksby\Router;

class Router
{
    /**
     * @var array The default class params - can be extended at __construct().
     */
    private $defaults = [

        /*
         * @var string Current base route to be used for route mounting.
         */
        'baseRoute' => '',

        /*
         * @var string The base path from where all routing is executed.
         */
        'serverBasePath' => null,

        /*
         * @var array Shorthand regex for Dynamic Route Patterns
         */
        'subpatterns' => [
            /* Integer - matches one or more digits (0-9). */
            '[i]' => '(\d+)',
            /* Alphanumeric - Matches one or more word characters (a-z 0-9 _) */
            '[a]' => '(\w+)',
            /* Wildcard - matches any character but /, one or more. */
            '[*]' => '([^/]+)',
            /* Super wildcard - matches any character (including /), zero or more. */
            '[**]' => '(.*)',
        ],
    ];

    /**
     * @var array The before middlware route patterns.
     */
    private $beforeRoutes = [];

    /**
     * @var array THe route patterns.
     */
    private $routes = [];

    /**
     * @var callable The function to be called when no valid route can be found.
     */
    protected $notFoundCallback;

    /**
     * @var string The Request Method that needs to be handled.
     */
    private $requestedMethod = '';

    /**
     * Combines default class params with addtional settings.
     * 
     * @param array $settings Additional parameters to be used.
     */
    public function __construct($settings = [])
    {
        $this->defaults = array_merge($this->defaults, $settings);
    }

    // MAIN FUNCTIONS
    /**
     * Store before middleware route and handling function.
     * 
     * @param string   $methods Allowed methods on which to match, | delimited.
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function before($methods, $pattern, $fn)
    {
        $pattern = $this->_createPattern($pattern);

        foreach (explode('|', $methods) as $method) {
            $this->beforeRoutes[$method][] = [
                'pattern' => $pattern,
                'fn' => $fn,
            ];
        }
    }

    /**
     * Store route and handling function.
     * 
     * @param string   $methods Allowed methods on which to match, | delimited.
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function match($methods, $pattern, $fn)
    {
        $pattern = $this->_createPattern($pattern);

        foreach (explode('|', $methods) as $method) {
            $this->routes[$method][] = [
                'pattern' => $pattern,
                'fn' => $fn,
            ];
        }
    }

    /**
     * Shorthand to match any route Request Method.
     * 
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function any($pattern, $fn)
    {
        $this->match('GET|POST|PUT|DELETE', $pattern, $fn);
    }

    /**
     * Shorthand for a GET request.
     *
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function get($pattern, $fn)
    {
        $this->match('GET', $pattern, $fn);
    }

    /**
     * Shorthand for a POST request.
     *
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function post($pattern, $fn)
    {
        $this->match('POST', $pattern, $fn);
    }

    /**
     * Shorthand for a PUT request.
     *
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function put($pattern, $fn)
    {
        $this->match('POST', $pattern, $fn);
    }

    /**
     * Shorthand for a DELETE request.
     *
     * @param string   $pattern A route pattern, eg. /homepage
     * @param callable $fn      The handling function to be executed.
     */
    public function delete($pattern, $fn)
    {
        $this->match('DELETE', $pattern, $fn);
    }

    /**
     * Group a collection of patterns on a common base route.
     * 
     * @param string $baseRoute The common route subpattern.
     * @param [type] $fn        The handling function to be executed.
     */
    public function group($baseRoute, $fn)
    {
        // store current base route
        $currentBaseRoute = $this->defaults['baseRoute'];

        // append new base route
        $this->defaults['baseRoute'] .= $baseRoute;

        call_user_func($fn);

        // restore original base route
        $this->defaults['baseRoute'] = $currentBaseRoute;
    }

    /**
     * Set the function to run when hitting a 404.
     * 
     * @param callable $fn The handling function to be executed.
     */
    public function set404($fn)
    {
        $this->notFoundCallback = $fn;
    }

    /**
     * Loop through middleware & routes and execute handling function if match is 
     * found.
     */
    public function run()
    {
        $this->requestedMethod = $this->getRequestMethod();

        // process middleware
        if (isset($this->beforeRoutes[$this->requestedMethod])) {
            $this->_process($this->beforeRoutes[$this->requestedMethod]);
        }

        // process all routes
        $numProcessed = 0;
        if (isset($this->routes[$this->requestedMethod])) {
            $numProcessed = $this->_process($this->routes[$this->requestedMethod]);
        }

        // if no routes were processed, trigger a 404
        if ($numProcessed === 0) {
            if ($this->notFoundCallback && is_callable($this->notFoundCallback)) {
                call_user_func($this->notFoundCallback);
            } else {
                header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
            }
        }

        // return true if route was processed, otherwise false
        if ($numProcessed === 0) {
            return false;
        }

        return true;
    }

    /**
     * Get the current Request Method used.
     * 
     * @return string The Request Method to handle.
     */
    public function getRequestMethod()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method == 'POST') {
            $headers = $this->getRequestHeaders();
        }

        return $method;
    }

    /**
     * Get all the Request Headers.
     * 
     * @return array The Request Headers.
     */
    public function getRequestHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if ((substr($key, 0, 5) == 'HTTP_') || ($key == 'CONTENT_TYPE') || ($key == 'CONTENT_LENGTH')) {
                // process key into correct format
                $key = substr($key, 5);
                $key = str_replace('_', ' ', $key);
                $key = strtolower($key);
                $key = ucwords($key);
                $key = str_replace([' ', 'Http'], ['-', 'HTTP'], $key);

                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * Process the routes, if a match is found execute the matching handling function.
     * 
     * @param array $routes Collection of route patterns and their handling functions.
     *
     * @return int The number of routes that were processed.
     */
    private function _process($routes)
    {
        // track the number of routes that have been processed
        $numProcessed = 0;

        // get current page url
        $uri = $this->_getUri();

        // loop through all routes
        foreach ($routes as $route) {
            // check for matching routes
            if (preg_match_all('%^'.$route['pattern'].'$%', $uri, $matches)) {
                // remove original string from matches
                $matches = array_slice($matches, 1);

                // extract matched parameters from the URL
                $params = array_map(function ($match, $index) use ($matches) {
                    return isset($match[0]) ? trim($match[0], '/') : null;
                }, $matches, array_keys($matches));

                // call return function with URL parameters
                if (is_callable($route['fn'])) {
                    call_user_func_array($route['fn'], $params);
                } // if not check for controller routing
                elseif (stripos($route['fn'], '@') !== false) {
                    // explode route segments
                    list($controller, $method) = explode('@', $route['fn']);
                    // check if class exists
                    if (class_exists($controller)) {
                        // instatiate new class
                        call_user_func_array([new $controller(), $method], $params);
                    } else {
                        // @TODO: throw appropriate exception
                    }
                }

                ++$numProcessed;
            }
        }

        return $numProcessed;
    }

    /**
     * Convert shorthand subpattern with actual regex pattern.
     * 
     * @param string $pattern The shorthand subpattern.
     *
     * @return string The matching regex pattern.
     */
    private function _createPattern($pattern)
    {
        // replace shorthand route subpatterns
        $pattern = str_replace(
            array_keys($this->defaults['subpatterns']),
            array_values($this->defaults['subpatterns']),
            $pattern
        );

        if (strpos($pattern, '(.*)') > 0 || strpos($pattern, '(.*)') === false) {
            $pattern = $this->defaults['baseRoute'].'/'.trim($pattern, '/');
            $pattern = $this->defaults['baseRoute'] ? rtrim($pattern, '/') : $pattern;
        }

        return $pattern;
    }

    /**
     * Define the current relative URI.
     * 
     * @return string
     */
    private function _getUri()
    {
        // get full request uri and remove the base path
        $uri = substr($_SERVER['REQUEST_URI'], strlen($this->_getBasePath()));

        // remove query params from url
        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // force preceding & remove trailing slash
        return '/'.trim($uri, '/');
    }

    /**
     * Return the server base path and define it if is not defined.
     * 
     * @return string.
     */
    protected function _getBasePath()
    {
        // check if server base path is defined, if not define it
        if ($this->default['serverBasePath'] === null) {
            $this->default['serverBasePath'] = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)).'/';
        }

        return $this->serverBasePath;
    }
}
