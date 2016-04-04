<?php

session_start();

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
        $user = User::findById($this->db, $this->session->userID);
        return $this->view->render($response, 'index.twig', compact("user")); 
    } else {
        return $response->withRedirect($router->pathFor('login'));
    }
})->setName('home');


// Register route
$app->get('/register', function ($request, $response) {
    // redirect to main page if already logged in
    if($this->session->isLoggedIn()) {
        $router = $this->router;
        return $response->withRedirect($router->pathFor('home'));
    }

    return $this->view->render($response, 'register.twig');
})->setName('register');


// Process register form
$app->post('/register', function ($request, $response) {
    $username = strtolower(trim($request->getParam("username")));
    $password = $request->getParam("password");
    $password2 = $request->getParam("password2");
    $router = $this->router;

    if(User::fetchUser($this->db, $username)) {
        $this->flash->addMessage("fail", "Username {$username} already exists");
        return $response->withRedirect($router->pathFor('register'));
    }
    elseif(!User::isValidFormatUsername($username)) {
        $this->flash->addMessage("fail", 
                                 "Username can contain only letters and numbers and be between " . 
                                 User::USERNAME_MIN_LENGTH . " & " . User::USERNAME_MAX_LENGTH . 
                                 " characters long");
        return $response->withRedirect($router->pathFor('register'));
    }
    elseif(!User::isValidPassword($password)) {
        $this->flash->addMessage("fail",
                                 "Passwords must be at least " . USER::PASSWORD_MIN_LENGTH . " characters long");
        return $response->withRedirect($router->pathFor('register'));
    }
    elseif(!User::doPasswordsMatch($password, $password2)) {
        $this->flash->addMessage("fail", "Passwords must match");
        return $response->withRedirect($router->pathFor('register'));
    }
    else {
        $user = new User($this->db);
        $user->username = $username;
        $user->password = User::encryptPassword($password);
        $user->save();
        $this->flash->addMessage("success", "You have successfully registered");

        // redirect to login page
        return $response->withRedirect($router->pathFor('login'));
    }

})->setName('processRegister');


// Login route
$app->get('/login', function ($request, $response) {
    // Redirect to main page if already logged in
    if($this->session->isLoggedIn()) {
        $router = $this->router;
        return $response->withRedirect($router->pathFor('home'));
    }

    return $this->view->render($response, 'login.twig');
})->setName('login');


// Process login form
$app->post('/login', function ($request, $response) {
    $username = strtolower(trim($request->getParam("username")));
    $password = $request->getParam("password");
    $router = $this->router;

    if($user = User::fetchUser($this->db, $username)) {
        if(password_verify($password, $user->password)) {
            // login user and redirect to main page
            $this->session->login($user);
            $this->flash->addMessage("success", "Login success");
            return $response->withRedirect($router->pathFor('home'));
        }
    }

    $this->flash->addMessage("fail","Username and/or password is incorrect");
    return $response->withRedirect($router->pathFor('login'));

})->setName('processLogin');


// Logout route
$app->get('/logout', function ($request, $response) {
    if($this->session->isLoggedIn()) {
        $this->session->logout();
        $this->flash->addMessage("success", "Logout success");
    } 
    else {
        $this->flash->addMessage("fail", "You are not logged in");
    }

    $router = $this->router;
    return $response->withRedirect($router->pathFor('login')); 
})->setName('logout');


// Projects route
$app->get('/projects', function ($request, $response) {
    $router = $this->router;

    // Show main page if logged in. Otherwise redirect to login page
    if ($this->session->isLoggedIn()) {
        $user = User::findById($this->db, $this->session->userID);
        return $this->view->render($response, "projects.twig", compact("user"));
    } else {
        return $response->withRedirect($router->pathFor('login'));
    }
})->setName('projects');


// Account route
$app->get('/account', function ($request, $response) {
    $router = $this->router;

    // Show main page if logged in. Otherwise redirect to login page
    if ($this->session->isLoggedIn()) {
        $user = User::findById($this->db, $this->session->userID);
        return $this->view->render($response, "account.twig", compact("user"));
    } else {
        return $response->withRedirect($router->pathFor('login'));
    }
})->setName('account');


$app->run();

