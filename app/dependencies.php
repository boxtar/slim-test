<?php

use Slim\Views\Twig;
use DI\ContainerBuilder;
use App\Contracts\ConfigInterface;
use Psr\Container\ContainerInterface;

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
     * Bind a Config implementation into the container
     */
    $containerBuilder->addDefinitions([
        ConfigInterface::class => function ($c) {
            return new \App\Services\Config($c);
        }
    ]);
    
    /**
     * Get DB setup and added to container
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

    // Session flashing
    $containerBuilder->addDefinitions([
        'flash' => function (ContainerInterface $container) {
            return new \Slim\Flash\Messages();
        }
    ]);

    // Container can autowire Twig ('view'), but not storage config
    // so I'm manually passing that in as a dependency.
    // $containerBuilder->addDefinitions([
    //     PropertiesController::class => function (ContainerInterface $container) {
    //         return new PropertiesController(
    //             $container->get('view'),
    //             $container->get('flash'),
    //             $container->get('settings')['storage']
    //         );
    //     }
    // ]);
};
