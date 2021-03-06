<?php

session_start();

require_once '../config.php';

// autoload classes
require_once '../vendor/autoload.php';
spl_autoload_register(function ($classname) {
    require_once '../classes/'.$classname.'.php';
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

// Register monolog component on container
$container['logger'] = function ($c) {
    // Create a log channel
    $log = new Monolog\Logger('myLogger');
    $log->pushHandler(new Monolog\Handler\StreamHandler('../logs/app.log'));

    return $log;
};

// Register db component on container
$container['db'] = function ($c) {
    $db = new MySQLDatabase(DB_HOST, DB_NAME, DB_PORT, DB_USER, DB_PASS);

    return $db;
};

// Register session component on container
$container['session'] = function ($c) {
    $session = new ProjectSession(BASE_URL);

    return $session;
};

// Register flash component on container
$container['flash'] = function ($c) {
    return new \Slim\Flash\Messages();
};

// Override the default Not Found Handler
$container['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        return $c['view']->render($response, '404.twig', ['noMenu'=>true]); 
    };
};

// load middleware
require_once '../middleware.php';

// load routes
require_once '../routes.php';

$app->run();
