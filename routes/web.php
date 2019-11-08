<?php

use Slim\App;
use App\Controllers\HomeController;
use App\Controllers\UsersController;
use App\Controllers\PropertiesController;
use App\Models\Property;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {

    // App Index
    $app->get('/', HomeController::class)
        ->setName('index');

    // Properties
    $app->group('/properties', function (Group $group) {
        // Index
        $group->get('', PropertiesController::class . ':index')
            ->setName('properties.index');

        // Create
        $group->get('/create', PropertiesController::class . ':create')
            ->setName('properties.create');

        // Store
        $group->post('', PropertiesController::class . ':store')
            ->setName('properties.store');

        // Show
        $group->get('/{id}', PropertiesController::class . ':show')
            ->setName('properties.show');

        // Delete
        $group->delete('/{id}', PropertiesController::class . ':delete')
            ->setName('properties.delete');
    });

    // Contact the API
    $app->get('/get-data', function (Request $request, Response $response) {

        // This will hold all the property data from API
        $apiData = [];

        // Page being requested
        $currentPage = 1;

        // Number of property resources per page (100 is max)
        $pageSize = 100;

        // Next URL for data from API
        $nextUrl = "http://trialapi.craig.mtcdevserver.com/api/properties";

        // API key
        $apiKey = '3NLTTNlXsi6rBWl7nYGluOdkl2htFHug';

        // Setup Guzzle client
        $client = new GuzzleHttp\Client();

        // $nextUrl is updated with API response so this loop will
        // continue until API returns a falsy value for the next URL.
        while ($nextUrl) {
            // Using getContents takes ALL data from the PSR7 Stream Response object.
            $data = $client->get($nextUrl, [
                'query' => [
                    'api_key' => $apiKey,
                    'page[number]' => $currentPage,
                    'page[size]' => $pageSize
                ]
            ])
                ->getBody()
                ->getContents();

            $data = json_decode($data);

            // Do some transforming to each property and add to our collection
            foreach ($data->data as $property) {
                $apiData[] = array_merge(
                    (array) $property,
                    [
                        'property_type' => $property->property_type->title,
                        'api_id' => $property->uuid
                    ]
                );
            }

            // Time to get the next page from the API...
            $currentPage += 1;

            // ... But only if there are any pages left.
            $nextUrl = $data->next_page_url;
        }

        // Ok - Time for some more looping.
        foreach ($apiData as $apiProperty) {
            // Is this API property already in our own Datastore?
            if ($property = Property::where('api_id', $apiProperty['uuid'])->get()->first()) {
                // If yes, just update it.
                $property->update($apiProperty);
            } else {
                // Else persist it in our Datastore.
                Property::create($apiProperty);
            }
        }

        // This is just lazy. I should be redirecting somewhere with a flashed message.
        return $this->get('view')->render($response, 'properties/refreshed.twig');
    })->setName('properties.refresh-from-api');
};
