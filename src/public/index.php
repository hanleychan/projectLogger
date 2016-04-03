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

// Process register form
$app->post('/register', function ($request, $response) {
    $username = strtolower(trim($request->getParam("username")));
    $password = $request->getParam("password");
    $password2 = $request->getParam("password2");

    if(User::doesUsernameExist($this->db, $username)) {
        $this->flash->addMessage("fail", "Error: Username {$username} already exists");
        echo "USERNAME EXISTS";
        exit;
    }
    elseif(!User::isValidFormatUsername($username)) {
        $this->flash->addMessage("fail", 
                                 "Error: Username can contain only letters and numbers and be between " . 
                                 User::USERNAME_MIN_LENGTH . " & " . User::USERNAME_MAX_LENGTH . 
                                 " characters long");
        echo "INVALID FORMAT";
        exit;
    }
    elseif(!User::isValidPassword($password)) {
        echo "PASSWORD BETWEEN " . User::PASSWORD_MIN_LENGTH . "& " . User::PASSWORD_MAX_LENGTH . " characters";
        exit;
    }
    elseif(!User::doPasswordsMatch($password, $password2)) {
        echo "PASSWORDS NOT MATCHING";
        exit;
    }
    else {
        $user = new User($this->db);
        $user->username = $username;
        $user->password = $password;
        $user->save();
        echo "USER CREATED";
    }

})->setName('processRegister');

// Login route
$app->get('/login', function ($request, $response) {
    return $this->view->render($response, 'login.twig');
})->setName('login');

// Process login form
$app->post('/login', function ($request, $response) {
    return "PROCESS LOGIN FORM";
})->setName('processLogin');

$app->run();

