<?php

/**
 * This file builds up the Slim App with the DI Container
 * and allows the user to define configuration, 
 * dependencies, middlewares and routes.
 */

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

// Let's build up a container allowing user to hook into it during build
$containerBuilder = new ContainerBuilder();

// User config
$containerBuilder->addDefinitions(__DIR__ . '/../config/app.php');

// User dependencies
(require __DIR__ . '/../app/dependencies.php')($containerBuilder);

// Set the container to use when building Slim App
AppFactory::setContainer($containerBuilder->build());

// Instantiate our Slim app
$app = AppFactory::create();

// Fire up DB instance (Required for Eloquent to resolve connection)
$app->getContainer()->get('db');

// Register user middlewares
(require __DIR__ . '/../app/middleware.php')($app);

// Register user web routes
(require __DIR__ . '/../routes/web.php')($app);

// Return Slim app back to caller
return $app;
