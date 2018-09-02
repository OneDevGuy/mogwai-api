<?php

/**
 * Index a ton of data from mogwaid into MySQL
 */

if (php_sapi_name() !== 'cli') {
    die("Command line only" . PHP_EOL);
}

require_once('.credentials.php');
require_once('rpcclient.php');

$opts = getopt("", array("reindex"));

$rpc = new RPCClient($rpc_credentials['user'], $rpc_credentials['password'], $rpc_credentials['host'], $rpc_credentials['port'])
    or die("Unable to instantiate RPCClient" . PHP_EOL);

$mysqli = new mysqli($db_credentials['host'], $db_credentials['user'], $db_credentials['password'], $db_credentials['name']);

if (!$mysqli || $mysqli->connect_errno) {
    die("Could not instantiate mysqli" . PHP_EOL);
}

// check that tables exist, or create them
create_tables();

// check the highest block count and index data from mogwaid if there is more
$blockcount = $rpc->getblockcount();
if (array_key_exists("reindex", $opts)) {
    $mysqli->query("TRUNCATE TABLE `blocks_hashes`");
    $mysqli->query("TRUNCATE TABLE `block_addresses`");
    $mysqli->query("TRUNCATE TABLE `transactions_addresses`");
    $blockcount_db = 0;
    echo "Reindexing from 0" . PHP_EOL;
}
else {
    $blockcount_db = get_db_blockcount() + 1;
}
echo "blockcount: $blockcount vs $blockcount_db" . PHP_EOL;


while ($blockcount_db < $blockcount) {
    if ($blockcount_db < 1) {
        $hash = $rpc->getblockhash(0);
    }
    else {
        $hash = $rpc->getblockhash($blockcount_db);
    }

    echo "$blockcount_db: $hash" . PHP_EOL;

    if (empty($hash)) {
        die("Panic!  Received empty blockhash at height $blockcount_db" . PHP_EOL);
    }

    $mysqli->query("INSERT IGNORE INTO `blocks_hashes` (`block`, `hash`)
        VALUES ($blockcount_db, '$hash')") or die ("invalid query" . PHP_EOL);

    $block = $rpc->getblock($hash);
    // make sure out count is the same as the block index
    if ($blockcount_db != $block['height']) {
        die("$blockcount_db != '{$block['height']}'" . PHP_EOL);
    }

    foreach ($block["tx"] as $tx) {
        $tx = $mysqli->real_escape_string($tx);

        $rawtx = $rpc->getrawtransaction($tx);
        if ($rawtx) {
            $transaction = $rpc->decoderawtransaction($rawtx);

            $vin = $transaction['vin'];
            $vout = $transaction['vout'];

            foreach ($vin as $input) {
                if (@$input['address']) {
                    $mysqli->query("INSERT IGNORE INTO `transactions_addresses` (`transaction`, `address`,`block_index`, `v`)
                        VALUES ('$tx', '{$input['address']}', '$blockcount_db', 'vin')") or die("invalid query" . PHP_EOL);
                }
            }

            foreach ($vout as $input) {
                if (@$input['scriptPubKey']['addresses']) {
                    foreach ($input['scriptPubKey']['addresses'] as $address) {
                        $address = $mysqli->real_escape_string($address);
                        $mysqli->query("INSERT IGNORE INTO `transactions_addresses` (`transaction`, `address`,`block_index`, `v`) VALUES ('$tx', '$address', '$blockcount_db', 'vout')") or die("invalid query" . PHP_EOL);
                    }
                }
            }

            // if $vin is not coinbase and there is only one vin and one vout, store this as
            // a potential mirror transaction
            if (count($vin) == 1 && count($vout) == 1 && empty($vin[0]['coinbase'])) {
                $vin_address = @$vin[0]['address'];
                $vout_address = @$vout[0]['scriptPubKey']['addresses'][0];

                if ($vin_address && $vout_address && count($vout[0]['scriptPubKey']['addresses']) == 1) {
                    // this it not a mirror transaction if the $vout was ever a $vin in another transaction
                    $query = "SELECT vin FROM `mirror_transactions` WHERE vin = '$vout_address'";
                    $res_vout = $mysqli->query($query);
                    if ($res_vout->num_rows) {
                        continue;
                    }

                    // delete any other possible mirror transactions if the $vin was ever a vout
                    $query = "DELETE FROM `mirror_transactions` WHERE vout = '$vin_address'";
                    $mysqli->query($query);

                    $query = "INSERT IGNORE INTO `mirror_transactions`
                        (`block_index`, `transaction`, `vin`, `vout`)
                        VALUES
                        ($blockcount_db, '$tx', '$vin_address', '$vout_address')
                    ";
                    $mysqli->query($query) or die("invalid query: $query" . PHP_EOL);
                }
            }

        }
        else {
            echo "Could not get raw transaction for $blockcount_db : $tx" . PHP_EOL;
        }
    }

    $blockcount_db = intval($block['height']) + 1;

}









//// functions

function create_tables($tablename = null) {
    global $mysqli;


    if (!$mysqli->query('select 1 from `blocks_hashes` LIMIT 1')) {
        $query = "CREATE TABLE IF NOT EXISTS `blocks_hashes` (
              `block` int(11) unsigned NOT NULL,
              `hash` char(64) NOT NULL DEFAULT '',
              UNIQUE KEY `ix_blocks_hashes` (`block`,`hash`),
              KEY `ix_block` (`block`),
              KEY `ix_hashes` (`hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";

        $mysqli->real_query($query);
        echo "Created table `block_hashes`" . PHP_EOL;
    }

    if (!$mysqli->query('select 1 from `blocks_addresses` LIMIT 1')) {
        $query = "CREATE TABLE IF NOT EXISTS `blocks_addresses` (
              `block` int(11) unsigned NOT NULL,
              `address` char(34) NOT NULL DEFAULT '',
              UNIQUE KEY `ix_block_address` (`block`,`address`),
              KEY `ix_block` (`block`),
              KEY `ix_address` (`address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";

        $mysqli->real_query($query);
        echo "Created table `block_addresses`" . PHP_EOL;
    }

    if (!$mysqli->query('select 1 from `transactions_addresses` LIMIT 1')) {
        $query = "CREATE TABLE IF NOT EXISTS `transactions_addresses` (
              `block_index` int(11) NOT NULL,
              `transaction` char(64) NOT NULL DEFAULT '',
              `address` char(34) NOT NULL DEFAULT '',
              `v` char(4) NOT NULL DEFAULT '',
              UNIQUE KEY `ix_transaction_address` (`block_index`, `transaction`,`address`),
              KEY `ix_block_index` (`block_index`),
              KEY `ix_transaction` (`transaction`),
              KEY `ix_address` (`address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";

        $mysqli->real_query($query);
        echo "Created table `transactions_addresses`" . PHP_EOL;
    }

    if (!$mysqli->query('select 1 from `mirror_transactions` LIMIT 1')) {
        $query ="CREATE TABLE IF NOT EXISTS `mirror_transactions` (
              `block_index` int(11) unsigned NOT NULL,
              `transaction` varchar(64) NOT NULL DEFAULT '',
              `vin` varchar(34) NOT NULL DEFAULT '',
              `vout` varchar(34) NOT NULL DEFAULT '',
              PRIMARY KEY (`block_index`),
              KEY `ix_addresses` (`vin`,`vout`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";
        $mysqli->real_query($query);
        echo "Created table `mirror_transactions`" . PHP_EOL;
    }
}

function get_db_blockcount() {
    global $mysqli;

    $res = $mysqli->query("SELECT max(block_index) AS mx FROM `transactions_addresses`");
    if ($res->num_rows) {
        $row = $res->fetch_row();
        return intval($row[0]);
    }

    return -1;
}
