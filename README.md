# mogwai-api v1.0
Lightweight API to support World Of Mogwai mobile phone instances

This repository is a collection of reference API implementations for the World of Mogwai mobile phone game.

You do not need to run all the code in this repository, just the code in the language of your choice.  Each language will provide the same interface and return the same data.

You may write your own. Any API that conforms to the specification in this document, and returns the same results as the reference implementation(s) can be considered a valid mogwai-api.

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

_Return a JSON-encoded object demonstrating the result of test data for each route (except test), and also a hash of all results, for easy comparison of compliance between two implementations_

## getbalance
GET /getbalance/:address
_Return scalar string representing a floating point value for the balance of the provided address_

## listtransactions
GET /listtransactions/:address

## listmirrtransactions
GET /listmirrtransactions/:address

## getblock
GET /getblock

GET /getblock/:height

## getblocks
GET /getblock/:height/:numblocks

## getevents
GET /getevents/:height/:numblocks

## sendrawtransaction
GET /sendrawtransaction/:hex

# 404 not found 
Any unmatched route should return the literal text "404: No route found", including valid routes that are not yet implemented.
