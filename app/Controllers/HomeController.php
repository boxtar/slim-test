<?php

namespace App\Controllers;

use App\Services\Config;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class HomeController {

    protected $view;
    
    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function __invoke(Request $request, Response $response)
    {
        return $this->view->render($response, 'home.twig');
    }
    
}