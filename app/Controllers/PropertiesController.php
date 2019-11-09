<?php

namespace App\Controllers;

use App\Contracts\ConfigInterface;
use Slim\Views\Twig;
use App\Models\Property;
use Valitron\Validator as Validator;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class PropertiesController
{

    /**
     * Reference to template renderer.
     * It annoys me that I can't do something like this out of the box:
     * return $response->renderTemplate('template.name', [data => 'goes here']);
     * 
     * @param Twig $view
     */
    protected $view;

    /**
     * Session message flashing
     * 
     * @param Messages $flash
     */
    protected $flash;

    /**
     * Config instance for retrieving settings.
     * 
     * @param ConfigInterface $config
     */
    protected $config;

    /**
     * Absolute filepath to storage directory.
     * 
     * @param string $storagePath
     */
    protected $storagePath;

    /**
     * Absolute URL to the public storage directory.
     * 
     * @param string $storageUrl
     */
    protected $storageUrl;

    /**
     * Twig is auto injected due to PHP-DI Reflection which is
     * possible as I've added \Slim\Views\Twig::class into
     * container in dependencies.php
     */
    public function __construct(Twig $view, Messages $flash, ConfigInterface $config)
    {
        $this->view = $view;
        $this->flash = $flash;
        $this->config = $config;
        $this->storagePath = $config->get('storage.public_storage_path');
        $this->storageUrl =  $config->get('storage.public_storage_url');
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
        $itemsPerPage = 15;

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
        $property = Property::findOrThrow($request, $args['id']);

        return $this->view->render($response, 'properties/show.twig', compact('property'));
    }

    /**
     * Shows page for creating a new Property
     */
    public function create(Request $request, Response $response)
    {
        $errors = $this->flash->getMessage('errors')[0];
        $old = $this->flash->getMessage('old')[0];

        return $this->view->render($response, 'properties/create.twig', compact('errors', 'old'));
    }

    /**
     * Persist a Property
     */
    public function store(Request $request, Response $response)
    {
        // Validation
        $v = new Validator($input = $request->getParsedBody());

        // Set rules for Validator
        $this->addValidationRules($v);

        // Return validation errors, if any
        if (!$v->validate()) {
            $this->flash->addMessage('errors', $v->errors());
            $this->flash->addMessage('old', $input);
            return $response->withHeader('Location', $request->getAttribute('routeParser')->urlFor('properties.create'));
        }

        // Persist
        $property = Property::create(
            array_merge($input, [
                'image_full' => $this->getFileUrl($this->processImage($request))
            ])
        );

        $router = $request->getAttribute('routeParser');

        // Respond/Redirect (TODO: flash success message)
        return $response
            ->withHeader('Location', $router->urlFor('properties.show', ['id' => $property->id]));
    }

    public function edit(Request $request, Response $response, $args)
    {
        $property = Property::findOrThrow($request, $args['id']);

        $errors = $this->flash->getMessage('errors')[0];

        return $this->view->render($response, 'properties/edit.twig', compact('property', 'errors'));
    }

    public function update(Request $request, Response $response, $args)
    {
        $property = Property::findOrThrow($request, $args['id']);

        // Validation
        $v = new Validator($input = $request->getParsedBody());

        // Add Validation Rules
        $this->addValidationRules($v);

        // Return validation errors, if any
        if (!$v->validate()) {
            $this->flash->addMessage('errors', $v->errors());
            return $response->withHeader(
                'Location',
                $request->getAttribute('routeParser')->urlFor('properties.edit', ['id' => $property->id]),
                compact('property')
            );
        }

        // dd($input);

        // Update
        // processImage will return an empty string if no file in request payload
        if ($imageName = $this->processImage($request)) {
            // Convert absolute disk location to absolute URL
            $imageName = $this->getFileUrl(basename($imageName));
            // Delete old image (or try, at least)
            $oldImagePath = $this->getFileStoragePath(basename($property->image_full));
            if (file_exists($oldImagePath)) unlink($oldImagePath);
        }
        // If no new image was uploaded, set imageName to current image url
        $imageName = $imageName ?: $property->image_full;

        $property->update(array_merge($input, ['image_full' => $imageName]));

        // Respond
        return $response
            ->withHeader(
                'Location',
                $request->getAttribute('routeParser')->urlFor('properties.show', ['id' => $property->id])
            );
    }

    public function delete(Request $request, Response $response, $args)
    {
        // Authorise

        // Delete
        $property = Property::findOrFail($args['id']);
        $property->delete();

        // Get file location on disk of image
        $imagePath = $this->getFileStoragePath(basename($property->image_full));

        // Delete image (or try, at least)
        if (file_exists($imagePath)) unlink($imagePath);

        // Redirect with message (TODO: flash success message)
        return $response
            ->withHeader('Location', $request->getAttribute('routeParser')->urlFor('properties.index'));
    }

    protected function addValidationRules(Validator $v)
    {
        return $v
            ->rule('required', [
                'county',
                'country',
                'town',
                'description',
                'address',
                'num_bedrooms',
                'num_bathrooms',
                'price',
                'property_type',
                'type',
            ])
            ->rule('numeric', [
                'num_bedrooms',
                'num_bathrooms',
                'price'
            ]);
    }

    /**
     * Returns the absolute filepath to a file identified by the given filename.
     * 
     * @param string    $filename Name and extension of required file
     * @return string   Absolute path to the required file
     */
    protected function getFileStoragePath($filename)
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Returns the absolute URL to a file identified by the given filename.
     * 
     * @param string    $filename Name and extension of required file
     * @return string   Absolute URL to the required file
     */
    protected function getFileUrl($filename)
    {
        return $this->storageUrl . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Takes a single image from the request, moves it to
     * storage and returns the randomly generated filename.
     * 
     * @param ServerRequestInterface $request The server request
     * @return string The unique name of the uploaded file
     */
    protected function processImage(ServerRequestInterface $request)
    {
        $files = $request->getUploadedFiles();
        $imageName = "";
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
