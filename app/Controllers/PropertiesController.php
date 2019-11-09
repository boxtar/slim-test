<?php

namespace App\Controllers;

use App\Contracts\ConfigInterface;
use Slim\Views\Twig;
use App\Models\Property;
use Intervention\Image\ImageManager;
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
     * Image processor
     * 
     * @param ImageManager $image
     */
    protected $images;

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
    public function __construct(Twig $view, Messages $flash, ConfigInterface $config, ImageManager $images)
    {
        $this->view = $view;
        $this->flash = $flash;
        $this->config = $config;
        $this->images = $images;
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
                'image_full' => $imgName = $this->getUrlTo($this->processImage($request)),
                'image_thumbnail' => $this->getUrlTo($this->getThumbNameFor($imgName))
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

    /**
     * Update the resource
     * TODO: This method has become way too heavy. Refactor immediately.
     */
    public function update(Request $request, Response $response, $args)
    {
        // Get the property that is being updated
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

        // Update image - processImage will return an empty string if no file in request payload
        if ($imageName = $this->processImage($request)) {
            // The processImage method also saves a thumbnail - Grab its name.
            $thumbName = $this->getUrlTo(
                $this->getThumbNameFor($imageName)
            );
            // Convert absolute disk location to absolute URL
            $imageName = $this->getUrlTo(basename($imageName));
            // Delete old image and thumbnail (or try, at least)
            $oldImagePath = $this->getPathTo(basename($property->image_full));
            if (file_exists($oldImagePath)) unlink($oldImagePath);
            $oldThumbPath = $this->getPathTo(basename($property->image_thumbnail));
            if (file_exists($oldThumbPath)) unlink($oldThumbPath);
        }
        // If no new image was uploaded $imageName will be falsy, use current
        $imageName = $imageName ?: $property->image_full;
        // If no new image was uploaded, then $thumbName won't exist so use current
        $thumbName = $thumbName ?? $property->image_thumbnail;
        // Perform the update
        $property->update(array_merge($input, [
            'image_full' => $imageName,
            'image_thumbnail' => $thumbName
        ]));

        // Respond
        return $response
            ->withHeader(
                'Location',
                $request->getAttribute('routeParser')->urlFor('properties.show', ['id' => $property->id])
            );
    }

    public function delete(Request $request, Response $response, $args)
    {
        // Delete
        $property = Property::findOrFail($args['id']);
        $property->delete();

        // Get file location on disk of image
        $imagePath = $this->getPathTo(basename($property->image_full));
        $thumbPath = $this->getPathTo(basename($property->image_thumbnail));

        // Delete image (or try, at least)
        if (file_exists($imagePath)) unlink($imagePath);
        if (file_exists($thumbPath)) unlink($thumbPath);

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
                // Move full resolution image
                $imageName = $this->moveUploadedFile($this->storagePath, $image);
                // Create and save thumbnail image
                $this->createThumbnail($imageName);
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

    /**
     * Creates and saves a thumbnail image in the same location as
     * the given file.
     *
     * @param string $imageName name of the image to be used to create the thumbnail
     * @return string filename of new thumbnail image
     */
    protected function createThumbnail($imageName)
    {
        $thumbnailName = $this->getThumbNameFor($imageName);

        $this->images
            ->make($this->getPathTo($imageName))
            ->resize(150, 150)
            ->save($this->getPathTo($thumbnailName));

        return $thumbnailName;
    }

    /**
     * Returns the absolute filepath to a file identified by the given filename.
     * 
     * @param string    $filename Name and extension of required file
     * @return string   Absolute path to the required file
     */
    protected function getPathTo($filename)
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Returns the absolute URL to a file identified by the given filename.
     * 
     * @param string    $filename Name and extension of required file
     * @return string   Absolute URL to the required file
     */
    protected function getUrlTo($filename)
    {
        return $this->storageUrl . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Returns thumbnail name based on passed in filename. Simply shoves
     * '_thumb' in the middle of it.
     * 
     * @param string    $filename Name and extension of original image
     * @return string   Generated thumbnail name
     */
    protected function getThumbNameFor($imageName)
    {
        $imageNameParts = pathinfo($imageName);
        return $imageNameParts['filename'] . '_thumb.' . $imageNameParts['extension'];
    }
}
