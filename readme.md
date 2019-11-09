# Slim Playground

## Steps to setup on Droplet:
- Clone into droplet
- composer install
- Create nginx.conf
- Create Mysql Database and User for database
- Set app_url, storage, db and any other config settings
- link to ./storage/app/public from ./public directory
- Give www-data group write permissions: chmod -R g+rws storage

## Steps to pull changes onto Droplet:
- git pull
- Make any required changes to config
- composer install (or, if not required: composer dump-autoload -o)