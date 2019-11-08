<?php

use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Psr7\Response;

return function (App $app) {

    // I like this for grabbing RouteParser from $request. I'm sure it has many other uses.
    $app->addRoutingMiddleware();

    // Required for PUT/PATCH/DELETE etc.
    $app->add(new MethodOverrideMiddleware);

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

    /**
     * Setup Error Handling Middleware (catch errors gracefully)
     */
    $errorMiddleware = new Slim\Middleware\ErrorMiddleware(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        true, // Should be something like this: $app->get('APP_ENV') == 'development' ? true : false
        false,
        false
    );

    // Render custom 404 view for Not Found Exceptions
    $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, HttpNotFoundException $exception) use ($app) {
        // Error handlers don't get a response object. Fire one up ourselves.
        $response = $app->getResponseFactory()->createResponse();
        // Grab message to pass down to view
        $message = $exception->getMessage();
        // Return response powered by twig
        return $app
            ->getContainer()
            ->get('view')
            ->render($response->withStatus(404), 'errors/404.twig', compact('message'));
    });

    $app->add($errorMiddleware);
};
