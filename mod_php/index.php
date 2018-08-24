<?php

require_once('mogwai.api.lib.php');
require_once('rpcclient.php');
require_once('.credentials.php');

header('Content-type: application/json');

$rpc = new RPCClient($rpc_credentials['user'], $rpc_credentials['password'], $rpc_credentials['host'], $rpc_credentials['port'])
    or die("Unable to instantiate RPCClient" . PHP_EOL);

// declare routes
$r = register_route('GET', '/', "help_function");

$r = register_route('GET', '/help', "help_function");

$r = register_route('GET', '/getbalance/:address', function($address) {
    global $rpc;

    $arg = array("addresses" => array($address));
    $result = $rpc->getaddressbalance($arg);
    if ($rpc->error) {
        return $rpc->error;
    }
    else {
        return from_satoshi($result["balance"]);
    }

});

$r = register_route('GET', '/getblock', function() {
    return get_block(null);
});

$r = register_route('GET', '/getblock/:height', function($height) {
    return get_block(intval($height));
});

$r = register_route('GET', '/getblocks/:height/:count', function($height, $count) {
    return get_block(intval($height), intval($count));
});

// run the application to process the request
$result = process_route();




function help_function() {
    return "help contents";
}


function get_block($height = null, $count = 1) {
    global $rpc;

    $block_array = array();

    if (intval($count) < 1) {
        return "Invalid block count";
    }
    $found = 0;

    if ($height === null) {
        $block_hash = $rpc->getbestblockhash();
    }
    else {
        $block_hash = $rpc->getblockhash(intval($height));
        if (!$block_hash) {
            return $rpc->error;
        }
    }

    while ($found < $count) { 
        $found++;
        if ($block_hash) {
            $block = $rpc->getblock($block_hash);
            if ($block) {
                if ($count == 1) {
                    return $block;
                }
                else {
                    $block_array[] = $block;
                    $block_hash = @$block['nextblockhash'];
                }
            }
            else {
                return $rpc->error;
            }
        }
    }

    return $block_array;
}
 

