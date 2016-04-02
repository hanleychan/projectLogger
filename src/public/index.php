<?php

require_once '../config.php';

// autoload classes
require_once '../vendor/autoload.php';
spl_autoload_register(function($classname) {
    require_once("../classes/" . $classname . ".php");
});

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
$app->add(function ($request, $response, $next) {
    $this->view->offsetSet('flash', $this->flash);

    return $next($request, $response);
});

// Home route
$app->get('/', function ($request, $response) {
    $router = $this->router;

    // Show main page if logged in. Otherwise redirect to login page
    if ($this->session->isLoggedIn()) {
        return $this->view->render($response, 'index.twig'); 
    } else {
        return $response->withRedirect($router->pathFor('login'));
    }
    
})->setName('home');

// Register route
$app->get('/register', function ($request, $response) {
    return $this->view->render($response, 'register.twig');
})->setName('register');

// Login route
$app->get('/login', function ($request, $response) {
    return $this->view->render($response, 'login.twig');
})->setName('login');

$app->run();
