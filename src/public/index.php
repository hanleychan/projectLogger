<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require('../vendor/autoload.php');

$app = new \Slim\App;

// Get container
$container = $app->getContainer();

// Register view component on container
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('../templates', [
        'cache' => false
    ]);
    $view->addExtension(new \Slim\Views\TwigExtension(
        $c['router'],
        $c['request']->getUri()
    ));

    return $view;
};

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'index.twig');
})->setName("home");

$app->run();

