# mogwai-api v1.0
Lightweight API to support World Of Mogwai mobile phone instances

This repository is a collection of reference API implementations for the World of Mogwai mobile phone game.

You do not need to run all the code in this repository, just the code in the language of your choice.  Each language will provide the same interface and return the same data.

You may write your own. Any API that conforms to the specification in this document, and returns the same results as the reference implementation(s) can be considered a valid mogwai-api.

It is recommended, but not required, that you "Pretty Print" JSON output return values.  However, note that, in order to make direct comparisons, the "test" route should collapse all extraneous whitespace (remove carriage returns, collapse multiple spaces to one space); this will ensure that output and hashes of that output are identical on different systems.

# core dependency
All implementations will need to connect to a full mogwaid node to provide up-to-the-moment information.  The means of connecting is left as an implementation specific detail for each language and environment.

# routes
A valid mogwai-api implementation MUST provide the following RESTful API routes.  

For simplicity, all routes use GET.

In the following, the input types are as follows:
* :address is a valid Mogwai address ("Base58" string)
* :height is a non-negative integer
* :numblocks is a positive integer
* :hex is a hexidecimal string (without leading 0x)

All API calls return non-empty results; an API call that returns an empty result is in error.  Simple scalar results are returned as strings representing their value.  Complex/structured results are returned as JSON-encoded strings.

## help 
GET /

_and_ 

GET /help 

_Return help contents including the API version, and information about each of the routes supported_

## test
GET /test

_Return a JSON-encoded object demonstrating the result of test data for each route (except test), and also a hash of all results, for easy comparison of compliance between two implementations. IMPORTANT: JSON responses must collapse extraneous whitespace (remove carriage returns and collapse multiple spaces to a single space) in order for different implementations to output the same results and hashes_

## getbalance
GET /getbalance/:address

Success: _Return scalar string representing a floating point value for the balance of the provided address_

Error: _Return error string "Invalid address"_

## listtransactions
GET /listtransactions/:address

GET /listtransactions/:address/:height

GET /listtransactions/:address/:height/:numblocks

Success: _Return JSON-encoded array of transactions for this address, optionally starting at a given height and limiting the number of blocks to scan_


## listmirrtransactions
GET /listmirrtransactions/:address

GET /listmirrtransactions/:address/:height

GET /listmirrtransactions/:address/:height/:numblocks

Success: _Return JSON-encoded array of transactions for this address, optionally starting at a given height and limiting the number of blocks to scan_


## getblock
GET /getblock

GET /getblock/:height

GET /getblock/:height/:numblocks

Success: _Return JSON string repesenting a single block (or array of blocks if numblocks is passed in) at the specified height or at current block height_

Error: _Return error string "Block height out of range" or "Invalid block count"_


## getevents
GET /getevents/:height/:numblocks

## sendrawtransaction
GET /sendrawtransaction/:hex

# 404 not found 
Any unmatched route should return the literal text "404: No route found", including valid routes that are not yet implemented.
