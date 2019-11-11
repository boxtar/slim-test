<?php

namespace App\Controllers;

use App\Models\Property;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Flash\Messages;

use function GuzzleHttp\Promise\settle;

class ApiController
{
    // Guzzle Client
    protected $client;

    // API endpoint
    protected $uri = "http://trialapi.craig.mtcdevserver.com/api/properties";

    // API key
    protected $key = '3NLTTNlXsi6rBWl7nYGluOdkl2htFHug';

    // Number of property resources per page (100 is max)
    protected $pageSize = 100;

    // Constructore
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Ping API to get 'total' property. This is the total number of
     * properties available from the API. Use the total number to
     * figure out how many concurrent requests are needed based on the number
     * of properties required per request.
     * 
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        // This will hold the transformed API data for storing in DB
        $transformedData = $this->transformApiData($this->getDataFromApi());

        // Save or Update Property in Database.
        // This needs to be optimised.
        foreach ($transformedData as $apiProperty) {
            // Is this API property already in our own Datastore?
            if ($property = Property::where('api_id', $apiProperty['uuid'])->get()->first()) {
                // If yes, just update it.
                $property->update($apiProperty);
            } else {
                // Else persist it in our Datastore.
                Property::create($apiProperty);
            }
        }

        // Flash message to session
        (new Messages())->addMessage('notifications', 'Properties updated.');

        // Redirect to property index
        return $response
            ->withHeader(
                'Location',
                $request->getAttribute('routeParser')->urlFor('properties.index')
            );
    }

    /**
     * Initiate all requests, but do not block.
     */
    protected function getDataFromApi()
    {
        // This array will hold all the promises for the async requests.
        $promises = [];
        $requests = $this->getNumberOfRequestsRequired();
        for ($i = 1; $i <= $requests; $i++) {
            $promises[] = $this->client->getAsync($this->uri, [
                'query' => [
                    'api_key' => $this->key,
                    'page[number]' => $i,
                    'page[size]' => $this->pageSize
                ]
            ]);
        }

        return settle($promises)->wait();
    }

    /**
     * Ping API quickly for the total property count.
     * This will allow us to calculate the no. of
     * requests we need to make.
     */
    protected function getNumberOfRequestsRequired()
    {
        $options = [
            'query' => [
                'api_key' => $this->key,
                'page[number]' => 1,
                'page[size]' => 1
            ]
        ];
        // Total no of properties available from API
        $totalProperties = json_decode(
            $this->client->get($this->uri, $options)->getBody()->getContents(),
            true
        )['total'];
        // Ok, so this is the number of concurrent requests we need to fire off.
        return (int) ceil($totalProperties / $this->pageSize);
    }

    /**
     * Perform a few necessary transformations of the API data.
     */
    protected function transformApiData($data)
    {
        // Do some transforming to each property and add to our collection
        $transformedData = [];
        foreach ($data as $result) {
            $data = json_decode($result['value']->getBody()->getContents(), true)['data'];
            foreach ($data as $property) {
                $transformedData[] = array_merge(
                    (array) $property,
                    [
                        'property_type' => $property['property_type']['title'],
                        'api_id' => $property['uuid']
                    ]
                );
            }
        }
        return $transformedData;
    }
}
