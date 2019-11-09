# Slim Playground
Steps to setup on Droplet:
- Clone into droplet
- Create nginx.conf
- Create Mysql Database and User for database
- Set app_url, storage, db and any other config settings
- link to ./storage/app/public from ./public directory
- Give www-data group write permissions: chmod -R g+rws storage