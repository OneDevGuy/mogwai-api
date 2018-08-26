<?php

require_once('mogwai.api.lib.php');
require_once('rpcclient.php');
require_once('.credentials.php');

header('Content-type: application/json');

if (empty($rpc_credentials['user']) || empty($rpc_credentials['user'])  || empty($rpc_credentials['user']) || empty($rpc_credentials['user'])) {
    die('Credentials are not configured' . PHP_EOL);
}

$rpc = new RPCClient($rpc_credentials['user'], $rpc_credentials['password'], $rpc_credentials['host'], $rpc_credentials['port'])
    or die("Unable to instantiate RPCClient" . PHP_EOL);

$mysqli = new mysqli($db_credentials['host'], $db_credentials['user'], $db_credentials['password'], $db_credentials['name']);

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

$r = register_route('GET', '/getblock/:height/:count', function($height, $count) {
    return get_block(intval($height), intval($count));
});

$r = register_route('GET', '/getevents/:height/:count', function($height, $count) {
    global $rpc, $mysqli;
    $height = intval($height);
    $count = intval($count);

    $max_block = $rpc->getblockcount();

    if ($height < 0 || $height > $max_block) {
        return "Block height out of range";
    }

    if ($count < 1) {
        return "Invalid block count";
    }

    $max_height = $height + $count;

    $events = array(
        "FEED",
        "CACA",
        "FACE",
    );

    // if no events specified, return empty JSON array
    if (empty($events)) {
        return "[]";
    }

    $WHERE = "";
    foreach ($events as $e) {
        if ($WHERE) {
            $WHERE .= " OR ";
        }
        $WHERE .= "`hash` like '%{$e}%' ";
    }

    // query the database for block hashes containing any of the $events strings
    $query = "SELECT *
        FROM `blocks_hashes`
        WHERE `block` >= $height AND `block` <= $max_height
            AND ($WHERE)
        ORDER BY `block`
        LIMIT $count
    ";
    // return $query;
    $res = $mysqli->query($query);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    return $data;

    return get_block(intval($height), intval($count));
});

$r = register_route('GET', '/listtransactions/:address', function($address) {
    return list_transactions($address);
});

$r = register_route('GET', '/listtransactions/:address/:height', function($address, $height) {
    return list_transactions($address, $height);
});

$r = register_route('GET', '/listtransactions/:address/:height/:count', function($address, $height, $count) {
    return list_transactions($address, $height, $count);
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
                if (!empty($block['tx'])) {
                    foreach ($block['tx'] as $key => $tx_hash) {
                        $tx = $rpc->getrawtransaction($tx_hash);

                        if ($tx) {
                            $tx = $rpc->decoderawtransaction($tx);
                        }

                        if ($tx) {
                            $block['tx'][$key] = $tx;
                        }
                    }
                }

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
 
function list_transactions($address, $height = null, $count = null) {
    global $rpc, $mysqli;

    $WHERE_HEIGHT = '';

    if (is_numeric($height) && $height >= 0) {
        $height = intval($height);
        $WHERE_HEIGHT = " AND ta.block_index >= $height ";
    }
    else {
        $height = null;
    }

    if ($height && is_numeric($count) && $count > 0) {
        $count = intval($count);
        $WHERE_HEIGHT .= " AND ta.block_index <= " . ($height + $count);
    }


    $address = $mysqli->real_escape_string($address);
    $output = array();
    $res = $mysqli->query("SELECT ta.*, hash
        FROM `transactions_addresses` ta
        LEFT JOIN `blocks_hashes` bh on ta.block_index = bh.block
        WHERE `address` = '$address'
            $WHERE_HEIGHT
        ORDER BY ta.block_index
    ");

    if ($res && $res->num_rows) {
        while ($row = $res->fetch_assoc()) {
            $raw_tx = $rpc->getrawtransaction($row['transaction']);
            $tx = $rpc->decoderawtransaction($raw_tx);

            // refactor the transaction data
            $my_tx = array();
            $my_tx['blockindex'] = $row['block_index'];
            $my_tx['blockhash'] = $row['hash'];
            $my_tx['amount'] = 0.0;
            $my_tx['amount_satoshi'] = 0;
            $my_tx['txid'] = $row['transaction'];
            $my_tx['category'] = ($row['v'] == "vin") ? "send" : "receive";

            foreach($tx['vin'] as $v) {
                if (@$v['address'] == $address) {
                    $my_tx['amount'] -= $v['value'];
                    $my_tx['amount_satoshi'] -= $v['valueSat'];
                    $my_tx['category'] = 'send';
                }
            }
            foreach($tx['vout'] as $v) {
                if (@$v['scriptPubKey']['addresses'] && in_array($address, $v['scriptPubKey']['addresses'])) {
                    $my_tx['amount'] += $v['value'];
                    $my_tx['amount_satoshi'] += $v['valueSat'];
                    if (@$tx['vin'][0]['coinbase']) {
                        $my_tx['category'] = 'generate';
                    }
                    else {
                        $my_tx['category'] = 'receive';
                    }
                }
            }


            $output[] = $my_tx;
        }
    }

    return $output;
}

