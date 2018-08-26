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

# deployment

Place files in a directory served by Apache running mod_php.  The directory does not need to be the root web directory (this 
API can run in a subdirectory).

Add a database and database user in MySQL. i.e.: 

* mysql [-u privilegeduser -p]
* mysql> CREATE USER 'mogwai'@'localhost' IDENTIFIED BY 'somedifficultpassword';
* mysql> CREATE DATABASE \`mogwai\`;
* mysql> GRANT ALL ON mogwai.\* TO 'mogwai'@'localhost';

Edit .credentials.php with rpc and db credentials.

To bootstrap the database, import mogwai.sql from https://tba  :

* mysql mogwai -u mogwai < mogwai.sql   # enter your difficult password when prompted

Set up cron to regularly update the database:

* crontab -eu www-data  # replace www-data with your apache user, if different.  some systems use httpd
* \* \* \* \* \* [/path/to/your/mogwaiapi/directory/].indexer.php

