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
});

$r = register_route('GET', '/listtransactions/:address', function($address) {
    return list_transactions($address);
});

$r = register_route('GET', '/listtransactions/:address/:height', function($address, $height) {
    return list_transactions($address, intval($height));
});

$r = register_route('GET', '/listtransactions/:address/:height/:count', function($address, $height, $count) {
    return list_transactions($address, intval($height), intval($count));
});

$r = register_route('GET', '/listmirrtransactions/:address', function($address) {
    return list_mirror_transactions($address);
});

$r = register_route('GET', '/listmirrtransactions/:address/:height', function($address, $height) {
    return list_mirror_transactions($address, intval($height));
});

$r = register_route('GET', '/listmirrtransactions/:address/:height/:count', function($address, $height, $count) {
    return list_mirror_transactions($address, intval($height), intval($count));
});

$r = register_route('GET', '/createrawtransaction/:transactions/:outputs', function($transactions, $outputs) {
    return create_raw_transaction($transactions, $outputs);
});

$r = register_route('GET', '/sendrawtransaction/:hex', function($hex) {
    global $rpc;

    return $rpc->sendrawtransaction($hex);
});

$r = register_route('GET', '/decoderawtransaction/:hex', function($hex) {
    global $rpc;

    return $rpc->decoderawtransaction($hex);
});

$r = register_route('GET', '/createmirtransaction/:addr/:amount/:txid/:offset', function($address, $amount, $txid, $offset) {
    global $rpc;

    if (!is_numeric($amount) || !is_numeric($offset)) {
        return "Invalid inputs";
    }

    if ($amount <= 0 || $offset < 0) {
        return "Invalid inputs";
    }

    if (empty($address) || empty($txid)) {
        return "Invalid inputs";
    }

    // convert to numeric from string
    $amount += 0;
    $offset += 0;

    $in = array();
    $in[] = array(
        "txid" => $txid,
        "vout" => $offset,
        "sequence" => 9,
    );

    $out = array(
        $address => $amount,
        "data" => strtoupper(bin2hex("mogwai")),
    );

    $result = $rpc->createrawtransaction($in, $out);

    if (empty($result)) {
        return "Invalid inputs";
    }

    return $result;
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

    $max_block = $rpc->getblockcount();

    if (!is_null($height) && $height < 0 || $height > $max_block) {
        return "Block height out of range";
    }

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

    $block_header = null;

    if ($res && $res->num_rows) {

        while ($row = $res->fetch_assoc()) {
            if (empty($block_header) || $block_header["height"] != $row["block_index"]) {
                $block_header = $rpc->getblock($row['hash']);
            }

            $raw_tx = $rpc->getrawtransaction($row['transaction']);
            $tx = $rpc->decoderawtransaction($raw_tx);

            // refactor the transaction data
            $my_tx = array();
            $my_tx['height'] = $row['block_index'];
            $my_tx['blockhash'] = $row['hash'];
            $my_tx['blocktime'] = @$block_header['time'];
            $my_tx['blockindex'] = array_search($row['transaction'], $block_header['tx']);

            $my_tx['confirmations'] = @$block_header['confirmations'];
            $my_tx['txid'] = $row['transaction'];
            $my_tx['category'] = ($row['v'] == "vin") ? "send" : "receive";

            $my_tx['amount'] = 0.0;
            $my_tx['amount_satoshi'] = 0;

            $my_tx['fee'] = 0;   // fee will be the value of outputs minus value of inputs
            $my_tx['fee_satoshi'] = 0;

            foreach($tx['vin'] as $v) {
                $my_tx['fee_satoshi'] += $v['valueSat'];
                if (@$v['address'] == $address) {
                    $my_tx['amount'] -= $v['value'];
                    $my_tx['amount_satoshi'] -= $v['valueSat'];
                    $my_tx['category'] = 'send';
                }
            }
            foreach($tx['vout'] as $v) {
                $my_tx['fee_satoshi'] -= $v['valueSat'];
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

            $my_tx['fee'] = from_satoshi($my_tx['fee_satoshi']);


            $output[] = $my_tx;
        }
    }

    return $output;
}

function list_mirror_transactions($address, $height = null, $count = null) {
    global $rpc, $mysqli;

    $max_block = $rpc->getblockcount();

    if (!is_null($height) && $height < 0 || $height > $max_block) {
        return "Block height out of range";
    }

    $WHERE_HEIGHT = '';

    if (is_numeric($height) && $height >= 0) {
        $height = intval($height);
        $WHERE_HEIGHT = " AND mt.block_index >= $height ";
    }
    else {
        $height = null;
    }

    if ($height && is_numeric($count) && $count > 0) {
        $count = intval($count);
        $WHERE_HEIGHT .= " AND mt.block_index <= " . ($height + $count);
    }

    $address = $mysqli->real_escape_string($address);
    $output = array();
    $res = $mysqli->query("SELECT mt.*, hash
        FROM `mirror_transactions` mt
        LEFT JOIN `blocks_hashes` bh on mt.block_index = bh.block
        WHERE `vout` = '$address'
            $WHERE_HEIGHT
        ORDER BY mt.block_index
    ");

    $block_header = null;

    if ($res && $res->num_rows) {

        while ($row = $res->fetch_assoc()) {
            if (empty($block_header) || $block_header["height"] != $row["block_index"]) {
                $block_header = $rpc->getblock($row['hash']);
            }

            $raw_tx = $rpc->getrawtransaction($row['transaction']);
            $tx = $rpc->decoderawtransaction($raw_tx);

            // refactor the transaction data
            $my_tx = array();
            $my_tx['height'] = $row['block_index'];
            $my_tx['blockhash'] = $row['hash'];
            $my_tx['blocktime'] = @$block_header['time'];
            $my_tx['blockindex'] = array_search($row['transaction'], $block_header['tx']);

            $my_tx['confirmations'] = @$block_header['confirmations'];
            $my_tx['txid'] = $row['transaction'];
            $my_tx['category'] = ($row['v'] == "vin") ? "send" : "receive";

            $my_tx['amount'] = 0.0;
            $my_tx['amount_satoshi'] = 0;

            $my_tx['fee'] = 0;   // fee will be the value of outputs minus value of inputs
            $my_tx['fee_satoshi'] = 0;

            foreach($tx['vin'] as $v) {
                $my_tx['fee_satoshi'] += $v['valueSat'];
                if (@$v['address'] == $address) {
                    $my_tx['amount'] -= $v['value'];
                    $my_tx['amount_satoshi'] -= $v['valueSat'];
                    $my_tx['category'] = 'send';
                }
            }
            foreach($tx['vout'] as $v) {
                $my_tx['fee_satoshi'] -= $v['valueSat'];
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

            $my_tx['fee'] = from_satoshi($my_tx['fee_satoshi']);


            $output[] = $my_tx;
        }
    }

    return $output;
}

/**
 * [create_raw_transaction description]
 * @param  string $transactions JSON blob of input transactions to use
 * @param  string $outputs      JSON blob of outputs (address and value, or data)
 * @return mixed               Return value array, or error string "Invalid inputs"
 * @see mogwai-cli help createrawtransaction
 */
function create_raw_transaction($transactions, $outputs) {
    global $rpc;

    // make sure these are valid JSON strings
    $in = json_decode($transactions, true);
    $out = json_decode($outputs, true);

    if (empty($in) || empty($out)) {
        return "Invalid inputs";
    }

    $result = $rpc->createrawtransaction($in, $out);

    if (empty($result)) {
        return "Invalid inputs";
    }

    return $result;
}
