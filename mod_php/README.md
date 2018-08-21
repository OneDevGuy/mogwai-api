# mod_php reference implementation

This implementation seeks to use the minimum amount of dependencies and to be compatible with the largest range of PHP 5 and 7 versions.

# dependencies

* Apache with mod_php  (usually already provided by default and enabled)
* cURL in PHP  (usually already compiled in)
* JSON in PHP  (usually already compiled in)
* mogwaid with RPC settings 
  * server=true
  * rpcuser=
  * rpcpassword=
  * rpcallowip=      (optional if not using localhost)
  * rpcport=         (optional if not using standard port 17710)
* mogwaid with indexing enabled  (if you just enabled the indexing, you will need to reindex)
  * txindex=true
  * addressindex=true
  * timestampindex=true
  * spentindex=true
