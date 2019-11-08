<?php

namespace App\Controllers;

use Slim\Views\Twig;
use App\Models\Property;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class PropertiesController
{

    // It annoys me that I can't do something like this out of the box:
    // { return $response->renderTemplate('template.name', [data => 'goes here']); }
    protected $view;

    // Path to public storage folder
    protected $storagePath;

    // URL to public storage folder
    protected $storageUrl;

    /**
     * Twig is auto injected due to PHP-DI Reflection which is
     * possible as I've added \Slim\Views\Twig::class into
     * container in dependencies.php
     */
    public function __construct(Twig $view, $storageConfig)
    {
        $this->view = $view;
        $this->storagePath = $storageConfig['uploads_dir'];
        $this->storageUrl =  $storageConfig['public_storage'];
    }

    /**
     * Show list of Properties
     */
    public function index(Request $request, Response $response)
    {
        // Grab any query params from request
        $queryParams = $request->getQueryParams();

        // Grab the page from the query params if it's there, or set it to page 1
        $page = $queryParams['page'] ?? 1;

        // Number of items to display per page.
        // Not configurable from UI as I have no time. Feel free to change here.
        $itemsPerPage = 30;

        // Grab all properties and chunk for pagination
        $properties = Property::orderBy('id', 'desc')->get()->toArray();
        $totalPropertiesCount = count($properties);
        $properties = array_chunk($properties, $itemsPerPage);

        // Total number of pages
        $noOfPages = count($properties);

        // Constrain page
        if ($page < 1) $page = 1;
        else if ($page > $noOfPages) $page = $noOfPages;

        // Render view with required data
        return $this->view->render($response, 'properties/index.twig', [
            'properties' => $noOfPages > 0 ? $properties[$page - 1] : [],
            'property_count' => $totalPropertiesCount,
            'current_page' => $page,
            'previous_page' => $page > 1 ? $page - 1 : null,
            'next_page' => $page < $noOfPages ? $page + 1 : null,
            'first_page' => 1,
            'last_page' => $noOfPages,
            'items_per_page' => $itemsPerPage
        ]);
    }

    /**
     * Show a single Property
     */
    public function show(Request $request, Response $response, $args)
    {
        // Find and throw if not found
        if (!$property = Property::find($args['id'])) {
            throw new HttpNotFoundException($request, "Property {$args['id']} Not Found");
        }

        return $this->view->render($response, 'properties/show.twig', compact('property'));
    }

    /**
     * Shows page for creating a new Property
     */
    public function create(Request $request, Response $response)
    {
        return $this->view->render($response, 'properties/create.twig');
    }

    /**
     * Persist a Property
     */
    public function store(Request $request, Response $response)
    {
        // Authorise action (Not requested in spec.)

        // Validation
        $this->validate($input = $request->getParsedBody());

        // Process image
        $imageName = $this->storageUrl . '/' . $this->processImage($request);

        // Persist
        $property = Property::create(
            array_merge($input, ['image_full' => $imageName])
        );

        $router = $request->getAttribute('routeParser');

        // Respond/Redirect (TODO: flash success message)
        return $response
            ->withHeader('Location', $router->urlFor('properties.show', ['id' => $property->id]));
    }

    public function delete(Request $request, Response $response, $args)
    {
        // Authorise

        // Delete
        Property::findOrFail($args['id'])->delete();

        // Redirect with message (TODO: flash success message)
        return $response
            ->withHeader('Location', $request->getAttribute('routeParser')->urlFor('properties.index'));
    }

    protected function validate($input)
    {
        // TODO
        return;
    }

    protected function processImage($request)
    {
        $imageName = "";

        $files = $request->getUploadedFiles();

        if (isset($files['image'])) {
            $image = $files['image'];
            if ($image->getError() === UPLOAD_ERR_OK) {
                $imageName = $this->moveUploadedFile($this->storagePath, $image);
            }
        }

        return $imageName;
    }

    /**
     * Moves the uploaded file to the upload directory and assigns it a unique name
     * to avoid overwriting an existing uploaded file.
     *
     * @param string $directory directory to which the file is moved
     * @param UploadedFileInterface $uploaded file uploaded file to move
     * @return string filename of moved file
     */
    protected function moveUploadedFile($directory, UploadedFileInterface $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }
}
