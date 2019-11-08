<?php

use Slim\App;
use Slim\Middleware\MethodOverrideMiddleware;

return function (App $app) {
    // Twig setup
    $twig = new Slim\Views\Twig('../resources/views', [
        'cache' => false
    ]);
    // TwigMiddleware sets up container binding and TwigExtension for us.
    $app->add(
        new Slim\Views\TwigMiddleware(
            $twig,
            $app->getContainer(),
            $app->getRouteCollector()->getRouteParser()
        )
    );

    // Error Middleware setup
    $app->add(
        new Slim\Middleware\ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            true,
            false,
            false
        )
    );

    $app->add(new MethodOverrideMiddleware);
};
