<?php

/**
 * Library functions to support the mogwai-api
 */

/** Holds mogwai route handling information */
global $mogwai_router;

$mogwai_router = array();

/**
 * Get the REST route, relative to this script's directory
 * @return array Associative array representing a route handler that matches the HTTP request, or the 404 route handler if no match found
 */
function get_route() {
    global $mogwai_router;

    if (empty($mogwai_router)) {
        return false;
    }

    // strip off directory name of this script to get base dir
    $dirname = dirname(@$_SERVER['SCRIPT_FILENAME']);

    // ignore document_root portion to reveal path to this parent dir
    $dirname = str_replace(@$_SERVER['DOCUMENT_ROOT'], '', $dirname);

    // strip out dirname from the request
    $request = str_replace($dirname . '/', '', @$_SERVER['REDIRECT_URL']);

    // get the http verb
    $verb = @$_SERVER['REQUEST_METHOD'] ? $_SERVER['REQUEST_METHOD'] : 'GET';

    // look through valid registered routes to find a matching route
    foreach ($mogwai_router as $route) {
        if ($verb != $route['verb']) {
            continue;
        }

        $path_parts = explode('/', $request);
        if (count($path_parts) != count($route['path_parts'])) {
            continue;
        }

        foreach ($route['path_parts'] as $ix => $p) {
            if (substr($p, 0, 1) == ':') {
                continue;
            }

            // if a non-variable part of path does not match, this is the wrong route
            if ($p != $path_parts[$ix]) {
                return false;
            }
        }

        // add the actual route to the route object
        $route['actual_path'] = $request;

        return $route;
    }

    // if no route found, return 404 route
    return array(
        'callback' => 'not_found_404',
        'actual_path' => $request,
    );
}

/**
 * Submit a route definition
 * @param  array $route_obj Associative array of route definition
 * @return bool            Success or failure
 */
function register_route($verb, $path, $callback) {
    global $mogwai_router;

    if (empty($verb) || empty($path) || empty($callback)) {
        return false;
    }

    if (!is_callable($callback)) {
        return false;
    }

    $verb = strtoupper($verb);
    if (!in_array($verb, array('GET', 'POST', 'PUT', 'DELETE'))) {
        return false;
    }

    $orig_path = $path;

    // discard leading and trailing slash in path, if found
    $path = trim($path, '/');
    $variables = array();
    $id = "$verb /"; // a unique signature of this route, so we can detect duplicates

    // if path is not empty (special case allows empty path), parse the path
    if ($path) {
        $path_parts = explode('/', $path);
        foreach ($path_parts as $ix => $p) {
            if (empty($p)) {
                return false;
            }

            // sanitize paths exclude non alpha-numeric or hyphen or underscore, and leading colon
            // leading colon treats path part as a variable
            if (substr($p, 0, 1) == ':') {
                $id .= ":/";
                $variables[] = $ix;
                $p = substr($p, 1);
            }
            else {
                $id .= "$p/";
            }
            $sanitized = preg_replace('/[^a-zA-Z_-]/', '', $p);

            // if sanitization does not return the same string, an invalid char was submitted
            if ($sanitized !== $p) {
                return false;
            }
        }

        $id = trim($id, '/');
    }

    // check that variables count matches the callback
    $reflect = new ReflectionFunction($callback);
    if (count($variables) != $reflect->getNumberOfParameters()) {
        return false;
    }

    if (array_key_exists($id, $mogwai_router)) {
        return false;
    }

    $mogwai_router[$id] = array(
        'id' => $id,
        'verb' => $verb,
        'path' => $path,
        'path_parts' => $path_parts,
        'callback' => $callback,
        'variables' => $variables,
        'orig_path' => $orig_path,
    );

    return $mogwai_router[$id];
}

/**
 * Process route by running the callback on parameters in path if the actual_path is set
 * @param  array $route Associative array containing route info
 * @return string|array        Output of callback function
 */
function process_route($route = null) {
    if (empty($route)) {
        $route = get_route();
    }

    if (empty($route['actual_path'])) {
        return false;
    }

    if (!is_callable($route['callback'])) {
        return false;
    }

    // if we don't have args, just call the function
    if (count($route['variables']) < 1) {
        return $route['callback']();
    }

    // get parameters from actual_path
    $path_parts = explode('/', $route['actual_path']);
    sort($route['variables']); // make sure this is sorted
    $args = array();
    foreach ($route['variables'] as $ix) {
        if ($ix < 0 || $ix >= count($path_parts)) {
            // index array out of bounds
            return false;
        }

        $args[] = $path_parts[$ix];
    }

    return call_user_func_array($route['callback'], $args);
}

/**
 * Return message that route is not found
 * @return string Error message
 */
function not_found_404() {
    return "404: No route found";
}

/**
 * Load credentials from a php file containing $rpc_credentials array
 * @param  string $filename Path of the credential file
 * @return array           Associative array containing rpcuser, rpcpassword, rpcip, or empty array on failure
 */
function load_credentials_from_php($filename = ".credentials.php") {
    if (!file_exists($filename)) {
        return array();
    }

    include($filename);

    if (empty($rpc_credentials)) {
        return array();
    }

    // provide reasonable defaults for host and port
    if (empty($rpc_credentials['host'])) {
        $rpc_credentials['host'] = 'localhost';
    }

    if (empty($rpc_credentials['port'])) {
        $rpc_credentials['port'] = '17710';
    }

    return $rpc_credentials;
}

/**
 * Load credentials from mongwai.conf config file
 * @param  string $filename Path of the config file
 * @return array           Associative array containing rpcuser, rpcpassword, rpcip, or empty array on failure
 */
function load_credentials_from_mogwai($filename = "~/.mogwaicore/mogwai.conf") {
    $filename = expand_tilde($filename);
    if (!file_exists($filename)) {
        return array();
    }

    $fp = fopen($filename, 'r');
    if (empty($fp)) {
        return array();
    }

    // read each line and check for key/value pairs separated by equal sign
    $rpc_credentials = array();
    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        $pair = explode('=', $line);
        if (count($pair) < 2) {
            continue;
        }

        $key = array_shift($pair);
        if ($key == 'rpcuser') {
            $rpc_credentials['user'] = implode('=', $pair);
        }
        elseif ($key == 'rpcpassword') {
            $rpc_credentials['password'] = implode('=', $pair);
        }

        // abort if we have the user and password already
        if (!empty($rpc_credentials['user']) && !empty($rpc_credentials['password'])
            && !empty($rpc_credentials['host']) && !empty($rpc_credentials['port'])) {
            break;
        }
    }

    // provide reasonable defaults for host and port
    if (empty($rpc_credentials['host'])) {
        $rpc_credentials['host'] = 'localhost';
    }

    if (empty($rpc_credentials['port'])) {
        $rpc_credentials['port'] = '17710';
    }

    return $rpc_credentials;
}

// http://jonathonhill.net/2013-09-03/tilde-expansion-in-php/
function expand_tilde($path)
{
    if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
        $info = posix_getpwuid(posix_getuid());
        $path = str_replace('~', $info['dir'], $path);
    }

    return $path;
}
