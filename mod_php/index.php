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

// check how close we are to being in sync.  if we are within a few blocks, run the indexer.
$blockcount = $rpc->getblockcount();
$blockcount_db = get_db_blocks_count();
$diff = $blockcount - $blockcount_db;
if ($diff > 0 && $diff < 10) {
    ob_start();
    require_once('.indexer.php');
    ob_end_clean();
}

// declare routes
$r = register_route('GET', '/', "help_function");

$r = register_route('GET', '/test/:a', function($a) {
    return test_route($a);
});

$r = register_route('GET', '/test/:a/:b', function($a, $b) {
    return test_route($a, $b);
});

$r = register_route('GET', '/test/:a/:b/:c', function($a, $b, $c) {
    return test_route($a, $b, $c);
});

$r = register_route('GET', '/test/:a/:b/:c/:d', function($a, $b, $c, $d) {
    return test_route($a, $b, $c, $d);
});

$r = register_route('GET', '/test/:a/:b/:c/:d/:e', function($a, $b, $c, $d, $e) {
    return test_route($a, $b, $c, $d, $e);
});

$r = register_route('GET', '/help', "help_function");

$r = register_route('GET', '/getbalance/:address', function($address) {
    global $rpc;

    $arg = array("addresses" => array($address));
    $result = $rpc->getaddressbalance($arg);
    if ($rpc->error) {
        return "Invalid inputs";
    }
    else {
        return from_satoshi($result["balance"]);
    }

});

$r = register_route('GET', '/listunspent/:minConf/:maxConf/:addresses', 'list_unspent');

$r = register_route('GET', '/getblockcount', function() {
    global $rpc;
    $result = $rpc->getblockcount();

    if (empty($result)) {
        $result = "Invalid inputs";
    }

    return $result;
});

$r = register_route('GET', '/getblockhash/:height', function($height) {
    global $rpc;
    $result = $rpc->getblockhash(intval($height));

    if (empty($result)) {
        $result = "Invalid inputs";
    }

    return $result;
});

$r = register_route('GET', '/getblockhashes/:height/:limit', function($height, $limit) {
    global $mysqli;

    $height = intval($height);
    $limit = intval($limit);

    if ($height < 0 || $limit < 1) {
        return "Invalid inputs";
    }

    $query = "SELECT *
        FROM `blocks_hashes`
        WHERE `block` >= $height
        ORDER BY `block`
        LIMIT $limit
    ";
    $res = $mysqli->query($query);

    $data = $res->fetch_all(MYSQLI_ASSOC);
    return $data;

});

$r = register_route('GET', '/getblock/:hex', function($hex) {
    global $rpc;
    return $rpc->getblock($hex);
});

$r = register_route('GET', '/getevents/:height/:count', function($height, $count) {
    global $rpc, $mysqli;
    $height = intval($height);
    $count = intval($count);

    $max_block = $rpc->getblockcount();

    if ($height < 0 || $height > $max_block) {
        return "Invalid inputs";
    }

    if ($count < 1) {
        return "Invalid inputs";
    }

    $max_height = $height + $count;

    $events = array(
        "feed",
        "caca",
        "face",
    );
    sort($events);

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

    // backfill data with which events it found
    foreach ($data as $key => $val) {
        foreach ($events as $ev) {
            $data[$key][$ev] = strpos($val['hash'], $ev);
        }
    }

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

$r = register_route('GET', '/listallmirrtransactions/', function() {
    return list_mirror_transactions('');
});

$r = register_route('GET', '/listallmirrtransactions/:height', function($height) {
    return list_mirror_transactions('', $height);
});

$r = register_route('GET', '/listallmirrtransactions/:height/:count', function($height, $count) {
    return list_mirror_transactions('', $height, $count);
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

$r = register_route('GET', '/pubkey2address/:pubkey', function($pubkey) {
    global $rpc;

    $res = $rpc->pubkey2address($pubkey);
    if (empty($res)) {
        return array(
            "isvalid" => false,
            "pubKeyIsFullyValid" => false,
            "address" => ""
        );
    }

    return $res;
});

$r = register_route('GET', '/validateaddress/:address', function($address) {
    global $rpc;

    if (empty($address)) {
        return array(
            "isvalid" => false
        );
    }

    return $rpc->validateaddress($address);
});







// run the application to process the request
$result = process_route();



///////////// funcs

function get_db_blocks_count() {
    global $mysqli;

    $res = $mysqli->query("SELECT max(block_index) AS mx FROM `transactions_addresses`");
    if ($res->num_rows) {
        $row = $res->fetch_row();
        return intval($row[0]);
    }

    return -1;
}

function help_function() {
    return "help contents";
}

function list_transactions($address, $height = null, $count = null) {
    global $rpc, $mysqli;

    $max_block = $rpc->getblockcount();

    if (!is_null($height) && $height < 0 || $height > $max_block) {
        return "Block height out of range";
    }

    $address = $mysqli->real_escape_string($address);
    $WHERE_ADDRESS = "`address` = '$address'";
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

    $output = array();
    $query = "SELECT ta.*, hash
        FROM `transactions_addresses` ta
        LEFT JOIN `blocks_hashes` bh on ta.block_index = bh.block
        WHERE $WHERE_ADDRESS
            $WHERE_HEIGHT
        ORDER BY ta.block_index
    ";
    $res = $mysqli->query($query);

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
            $my_tx['address'] = $row['address'];
            $my_tx['height'] = intval($row['block_index']);
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
                if (@$v['address'] == $row['address']) {
                    $my_tx['amount'] -= $v['value'];
                    $my_tx['amount_satoshi'] -= $v['valueSat'];
                    $my_tx['category'] = 'send';
                }
            }
            foreach($tx['vout'] as $v) {
                $my_tx['fee_satoshi'] -= $v['valueSat'];
                if (@$v['scriptPubKey']['addresses'] && in_array($row['address'], $v['scriptPubKey']['addresses'])) {
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

            if ($my_tx['amount_satoshi'] < 0) {
                $my_tx['category'] = 'send';
            }

            $my_tx['fee'] = from_satoshi($my_tx['fee_satoshi']);


            $output[] = $my_tx;
        }
    }

    return $output;
}

function list_mirror_transactions($address = '', $height = null, $count = null) {
    global $rpc, $mysqli;

    $max_block = $rpc->getblockcount();

    if (!is_null($height) && $height < 0 || $height > $max_block) {
        return "Block height out of range";
    }

    $address = $mysqli->real_escape_string($address);
    if ($address) {
        $WHERE_ADDRESS = "`vout` = '$address'";
    }
    else {
        $WHERE_ADDRESS = "1";
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

    $output = array();
    $query = "SELECT mt.*, hash
        FROM `mirror_transactions` mt
        LEFT JOIN `blocks_hashes` bh on mt.block_index = bh.block
        WHERE $WHERE_ADDRESS
            $WHERE_HEIGHT
        ORDER BY mt.block_index
    ";

    $res = $mysqli->query($query);

    $block_header = null;
    if ($res && $res->num_rows) {

        while ($row = $res->fetch_assoc()) {
            // if (empty($block_header) || $block_header["height"] != $row["block_index"]) {
            // }
            $block_header = $rpc->getblock($row['hash']);
            // print_r($block_header); echo PHP_EOL;
            $raw_tx = $rpc->getrawtransaction($row['transaction']);
            $tx = $rpc->decoderawtransaction($raw_tx);

            // refactor the transaction data
            $my_tx = array();
            $my_tx['address'] = $row['vout'];
            $my_tx['from_address'] = $row['vin'];
            $my_tx['height'] = intval($row['block_index']);
            $my_tx['blockhash'] = $row['hash'];
            $my_tx['blocktime'] = @$block_header['time'];
            $my_tx['blockindex'] = array_search($row['transaction'], $block_header['tx']);

            $my_tx['confirmations'] = @$block_header['confirmations'];
            $my_tx['txid'] = $row['transaction'];
            $my_tx['category'] = "receive";

            $my_tx['amount'] = 0.0;
            $my_tx['amount_satoshi'] = 0;

            $my_tx['fee'] = 0;   // fee will be the value of outputs minus value of inputs
            $my_tx['fee_satoshi'] = 0;

            foreach($tx['vin'] as $v) {
                $my_tx['fee_satoshi'] += $v['valueSat'];
                if (@$v['address'] == $row['vout']) {
                    $my_tx['amount'] -= $v['value'];
                    $my_tx['amount_satoshi'] -= $v['valueSat'];
                    $my_tx['category'] = 'send';
                }
            }
            foreach($tx['vout'] as $v) {
                $my_tx['fee_satoshi'] -= $v['valueSat'];
                if (@$v['scriptPubKey']['addresses'] && in_array($row['vout'], $v['scriptPubKey']['addresses'])) {
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

    $txs = array();
    if ($address && (is_null($count) || $max_block < ($height + $count))) {
        $addrmempool = $rpc->getaddressmempool(array("addresses" => array($address)));

        // collect unique transactions from mempool
        foreach ($addrmempool as $mem) {
            $txs[$mem["txid"]] = $mem["timestamp"];
        }

        // process each transaction in mempool
        foreach ($txs as $txid => $timestamp) {
            $raw_tx = $rpc->getrawtransaction($txid);
            $tx = $rpc->decoderawtransaction($raw_tx);

            // refactor the transaction data
            $my_tx = array();
            $my_tx['address'] = $address;
            // $my_tx['from_address'] = $row['vin'];
            $my_tx['height'] = -1;
            $my_tx['blockhash'] = '';
            $my_tx['blocktime'] = $timestamp;
            $my_tx['blockindex'] = -1;

            $my_tx['confirmations'] = 0;
            $my_tx['txid'] = $txid;
            $my_tx['category'] = "receive";

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
                else {
                    $my_tx["from_address"] = $v["address"];
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

function list_unspent($minConf, $maxConf, $addresses) {
    global $rpc, $mysqli;

    $minConf = intval($minConf);
    $maxConf = intval($maxConf);

    if ($minConf < 0 || $maxConf < $minConf) {
        return "Invalid inputs";
    }

    $addrs = explode(',', $addresses);

    $blockcount = $rpc->getblockcount();
    $minBlock = $blockcount - $maxConf;
    $maxBlock = $blockcount - $minConf;

    if ($minBlock <= 0) {
        $minBlock = 0;
    }

    if ($maxBlock <= 0) {
        return "Invalid inputs";
    }

    $WHERE_ADDRESS = '';
    foreach ($addrs as $address) {
        $address = trim($address);

        if (empty($WHERE_ADDRESS)) {
            $WHERE_ADDRESS = " (";
        }
        else {
            $WHERE_ADDRESS .=  " OR ";
        }

        $address = $mysqli->real_escape_string($address);
        $WHERE_ADDRESS .= "address = '$address'";
    }
    $WHERE_ADDRESS .= ') ';

    $query = "SELECT *
        FROM transactions_addresses
        WHERE (v = 'vout' OR v = 'both')
            AND block_index BETWEEN $minBlock AND $maxBlock
            AND $WHERE_ADDRESS
        ORDER BY block_index ASC
    ";

    $vouts = $mysqli->query($query);
    $data = $vouts->fetch_all(MYSQLI_ASSOC);

    $unspent = array();
    foreach ($data as $row) {
        // check the transaction to see if the vout with this address has been spent
        $tx_raw = $rpc->getrawtransaction($row['transaction']);
        $tx = $rpc->decoderawtransaction($tx_raw);

        if ($tx && @$tx['vout']) {
            foreach ($tx['vout'] as $ix => $vout) {
                if (@$vout['scriptPubKey']['addresses'] && in_array($row['address'], $vout['scriptPubKey']['addresses']) && empty($vout['spentTxId'])) {
                    $unspent[] = array(
                        "txid" => $row['transaction'],
                        "vout" => $ix,
                        "address" => $row['address'],
                        "scriptPubKey" => $vout['scriptPubKey']['hex'],
                        "amount" => $vout['value'],
                        "confirmations" => $blockcount - $row['block_index'],
                    );
                }
            }
        }
    }

    if (count($unspent) < 1) {
        return "[]";
    }

    return $unspent;
}


function test_route() {
    $args = array_filter(func_get_args());
    $route_path = implode('/', $args);
    ob_start();
    $route = get_route($route_path, 'GET');
    process_route($route);
    $result = ob_get_clean();

    // clean up the result (trim, normalize white space, remove carriage returns, etc)
    $result = trim($result);
    $result = preg_replace('/[\r\n]/', '', $result);
    $result = preg_replace('/\t/', ' ', $result);
    $result = preg_replace('/ +/', ' ', $result);
    $result = preg_replace('/\[ +/', '[', $result);
    $result = preg_replace('/ +\]/', ']', $result);
    $result = preg_replace('/\{ +/', '{', $result);
    $result = preg_replace('/ +\}/', '}', $result);

    // return $result;

    return hash("sha256", $result);

}

