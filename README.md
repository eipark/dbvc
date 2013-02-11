DBVC - Database Version Control
Originally created for MicroEval, DBVC is a system written in PHP that maintains database versions and schemas. Rather than having a bug tell you if your code is not up to date with your database or vice versa, just run this script every time you check out a new version of your code and stay in sync.

There are three main components: 1. migrate.php script that runs the migration scripts on your database 2. Migration scripts - these are sequentially numbered (e.g. 00001-name.php, 00002-name.php) and contain the SQL you want to run 3. A configs database table that keeps the version of the last run script in the database.

There is one issue in which version numbers may collide in large distributed teams if people are creating a new update script locally with the same number, however this was designed primarily for small teams.

There are also hooks to backup the database data to Amazon S3 when running the script against your production database.

Copyright 2012 under MIT License. Written by Ernie Park for . Contact ernestipark gmail
