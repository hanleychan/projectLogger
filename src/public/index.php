<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once '../config.php';
require_once '../vendor/autoload.php';

$config['displayErrorDetails'] = true;

$app = new \Slim\App(['settings' => $config]);

// Get container
$container = $app->getContainer();

// Register view component on container
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('../templates', [
        'cache' => false,
    ]);
    $view->addExtension(new \Slim\Views\TwigExtension(
        $c['router'],
        $c['request']->getUri()
    ));

    return $view;
};

// Register db component on container
$container['db'] = function ($c) {
    $db = new MySQLDatabase(DB_HOST, DB_NAME, DB_PORT, DB_USER, DB_PASS);

    return $db;
};

// Register session component on container
$container['session'] = function ($c) {
    $session = new Session(BASE_URL);

    return $session;
};

// Register flash component on container
$container['flash'] = function ($c) {
    session_start();

    return new \Slim\Flash\Messages();
};

// Add middleware
$app->add(function (Request $request, Response $response, callable $next) {
    $this->view->offsetSet('flash', $this->flash);

    return $next($request, $response);
});

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'index.twig');
})->setName('home');

// Login route
$app->get('/login', function (Request $request, Response $response) {
    return $this->view->render($response, 'login.twig');
})->setName('home');

$app->run();
