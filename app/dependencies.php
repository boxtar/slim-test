<?php

use Slim\Views\Twig;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use App\Controllers\PropertiesController;

return function (ContainerBuilder $containerBuilder) {

    /**
     * Twig Middleware adds configured Twig instance to container as 'view'.
     * To allow autowiring when constructing Controllers, alias to
     * the classes name.
     */
    $containerBuilder->addDefinitions([
        Twig::class => function ($c) {
            return $c->get('view');
        }
    ]);

    /**
     * Configure Eloquent and add to container.
     * As this is a builder function we need to actually retrieve
     * the container entry to boot this up (done in bootstrap/app.php)
     */
    $containerBuilder->addDefinitions([
        'db' => function (ContainerInterface $container) {

            $capsule = new \Illuminate\Database\Capsule\Manager;

            $capsule->addConnection($container->get('settings')['db']);

            $capsule->bootEloquent();

            $capsule->setAsGlobal(); // INVESTIGATE: Not sure why this is required...

            return $capsule;
        }
    ]);

    // CANNOT believe I actually have to do this...
    $containerBuilder->addDefinitions([
        PropertiesController::class => function(ContainerInterface $container) {
            return new PropertiesController(
                $container->get('view'),
                $container->get('settings')['storage']
            );
        }
    ]);
};
