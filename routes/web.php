<?php

use App\Controllers\ApiController;
use Slim\App;
use App\Models\Property;
use Slim\Flash\Messages;
use App\Controllers\HomeController;
use App\Controllers\PropertiesController;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

use function GuzzleHttp\Promise\settle;

return function (App $app) {

    // App Index
    $app->get('/', HomeController::class)
        ->setName('index');

    // Properties
    $app->group('/properties', function (Group $group) {
        // Index
        $group->get('', PropertiesController::class . ':index')
            ->setName('properties.index');

        // Refresh from API
        $group->get('/refresh', ApiController::class . ':index')
            ->setName('properties.refresh');

        // Create
        $group->get('/create', PropertiesController::class . ':create')
            ->setName('properties.create');

        // Store
        $group->post('', PropertiesController::class . ':store')
            ->setName('properties.store');

        // Show
        $group->get('/{id}', PropertiesController::class . ':show')
            ->setName('properties.show');

        // Edit
        $group->get('/{id}/edit', PropertiesController::class . ':edit')
            ->setName('properties.edit');

        // Update
        $group->patch('/{id}', PropertiesController::class . ':update')
            ->setName('properties.update');

        // Delete
        $group->delete('/{id}', PropertiesController::class . ':delete')
            ->setName('properties.delete');
    });
};
