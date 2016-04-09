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
    $session = new ProjectSession(BASE_URL);

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


/**
 * Main Routes
 */

// Home route
$app->get('/', function ($request, $response) {
    // redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        $router = $this->router;
        return $response->withRedirect($router->pathFor('login'));
    }

    // Show main page if logged in. Otherwise redirect to login page
    if ($this->session->isLoggedIn()) {
        $user = User::findById($this->db, $this->session->userID);
        return $this->view->render($response, 'index.twig', compact("user")); 
    }
})->setName('home');


/**
 * Register Routes
 */

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

    // Check if username is valid 
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


/**
 * Login Routes
 */

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
    $router = $this->router;

    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        $this->flash->addMessage("fail", "You are not logged in");
        return $response->withRedirect($router->pathFor('login')); 
    }

    $this->session->logout();
    $this->flash->addMessage("success", "Logout success");

    return $response->withRedirect($router->pathFor('login')); 
})->setName('logout');


/**
 * Project Routes
 */

// Projects route
$app->get('/projects', function ($request, $response) {
    $router = $this->router;

    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $user = User::findById($this->db, $this->session->userID);
    $projects = Project::findProjectsByUser($this->db, $user->id);

    return $this->view->render($response, "projects.twig", compact("user", "projects"));
})->setName('projects');

$app->get('/projects/all', function($request, $response) {
    $router = $this->router;

    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $projects = Project::findAll($this->db);
    $user = User::findById($this->db, $this->session->userID);

    return $this->view->render($response, "allProjects.twig", compact("user", "projects"));
})->setName('allProjects');


// Add new project route
$app->get('/projects/add', function ($request, $response) {
    $router = $this->router;

    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $user = User::findById($this->db, $this->session->userID);

    return $this->view->render($response, "addProject.twig", compact("user"));
})->setName('addProject');


// Process add new project route
$app->post('/projects/add', function ($request, $response) {
    $router = $this->router;
 
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $user = User::findById($this->db, $this->session->userID);
    $projectName = strtolower(trim($request->getParam("projectName")));

    if(Project::doesProjectExist($this->db, $projectName)) {
        $this->flash->addMessage("fail", "Project name already exists");
        return $response->withRedirect($router->pathFor('addProject'));
    }

    if(Project::isValidProjectName($projectName)) {
        $project = new Project($this->db);
        $project->projectName = $projectName;
        $project->ownerID = $user->id;
        $project->save();
        
        $projectMember = new ProjectMember($this->db);
        $projectMember->userID = $user->id;
        $projectMember->projectID = $project->id;
        $projectMember->isAdmin = true;
        $projectMember->save();

        $this->flash->addMessage("success", "Project successfully added");
        return $response->withRedirect($router->pathFor('projects'));
    }
    else {
        $this->flash->addMessage("fail", "Project name has an invalid format");
        return $response->withRedirect($router->pathFor('addProject'));
    }
})->setName('processAddProject');

// Route for a specific project
$app->get('/project/{name}', function ($request, $response, $args) {
    $router = $this->router;
 
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $name = $args["name"];
    return $response->withRedirect($router->pathFor("fetchProjectLogs", ["name"=>$name]));
})->setName('project');


// Route for adding a project log entry 
$app->post('/project/{name}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $hours = (int)trim($request->getParam("hours"));
    $minutes = (int)trim($request->getParam("minutes"));
    $date = $request->getParam("datePicker"); 
    $projectID = (int)trim($request->getParam("projectID"));
    $comment = trim($request->getParam("comment"));


    if(!$date = ProjectLog::formatDate($date)) {
        echo "INVALID DATE";
        exit;
    }


    if(!ProjectLog::isValidTime($hours, $minutes)) {
        echo "INVALID TIME";
        exit;
    }

    // Check if user is a member of this project before updating log
    if(!ProjectMember::isProjectMember($this->db, $projectID, $this->session->userID)) {
        echo "ERROR NOT A MEMBER OF GROUP";
        exit;
    }

    // Add log to database
    $projectLog = new ProjectLog($this->db);
    $projectLog->projectTime = "{$hours}:{$minutes}:00";
    $projectLog->userID = $this->session->userID;
    $projectLog->projectID = $projectID;
    $projectLog->comment = $comment;
    $projectLog->date = $date;
    $projectLog->save();

    return "project updated";
})->setName('addProjectLog');


// Route for getting all log entries for a project
$app->get('/project/{name}/projectLogs', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $page = "viewLogs";
    $name = trim($args["name"]);

    // Check if project exists
    if(!Project::doesProjectExist($this->db, $name)) {
        return "NO SUCH PROJECT";
        exit;
    }

    $projectLogs = ProjectLog::findLogsByProjectName($this->db, $name);
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $this->session->userID);

    if($projectLogs) {
        foreach($projectLogs as $projectLog) {
            $hours = (int)substr(trim($projectLog->projectTime), 0, 2);
            $mins = (int)substr(trim($projectLog->projectTime), 3, 2);
            
            $hours .= (($hours > 1) ? " hrs" : " hr");
            $mins .= (($mins > 1) ? " mins" : " min"); 
            $projectLog->projectHours = $hours;
            $projectLog->projectMins = $mins;

            if($projectLog->userID == $this->session->userID) {
                $projectLog->canEdit = true;
            }
        }
    }

    // Check if the request is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return $this->view->render($response, "fetchProjectLogs.twig", compact("page", "projectLogs", "isAdmin"));
    } else {
        $user = User::findById($this->db, $this->session->userID);

        // Check if user is a member of this project
        if(!$project = Project::findProjectByNameAndUser($this->db, $name, $this->session->userID)) {
            $project = Project::findProjectByName($this->db, $name);
            $projectMember = false;
        } else {
            $projectMember = true;
        }

        return $this->view->render($response, "project.twig", compact("user", "project", "projectLogs", "page", "projectMember", "isAdmin"));
    }
})->setName("fetchProjectLogs");


// Route for fetching members of a project
$app->get('/project/{name}/members', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $page = "members";
    $name = trim($args["name"]);
    
    // Check if project exists
    if(!Project::doesProjectExist($this->db, $name)) {
        return "NO SUCH PROJECT";
        exit;
    }

    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name); 

    // Check if the request is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return $this->view->render($response, "fetchProjectMembers.twig", compact("page", "projectMembers"));
    } else {
        $user = User::findById($this->db, $this->session->userID);

        // Check if user is a member of this project
        if(!$project = Project::findProjectByNameAndUser($this->db, $name, $this->session->userID)) {
            $project = Project::findProjectByName($this->db, $name);
            $projectMember = false;
        } else {
            $projectMember = true;
        }

        return $this->view->render($response, "project.twig", compact("user", "project", "projectMembers", "page", "projectMember"));
    }
})->setName("fetchProjectMembers");


// Route for adding a new project log entry
$app->get('/project/{name}/newLog', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $page = "addNewLog";
    $name = trim($args["name"]);

    // Check if project exists
    if(!Project::doesProjectExist($this->db, $name)) {
        return "NO SUCH PROJECT";
        exit;
    }

    if(!$project = Project::findProjectByNameAndUser($this->db, $name, $this->session->userID)) {
        return "NOT A MEMBER OF PROJECT";
        exit;
    }

    // Check if the request is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return $this->view->render($response, "fetchAddNewLog.twig", compact("project"));
    } else { 
        $user = User::findById($this->db, $this->session->userID);
        $projectMember = true;

        return $this->view->render($response, "project.twig", compact("user", "project", "projectMember", "page"));
    }
})->setName("fetchAddNewLog");


/**
 * Account Routes
 */

// Account route
$app->get('/account', function ($request, $response) {
    $router = $this->router;

    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $user = User::findById($this->db, $this->session->userID);
    return $this->view->render($response, "account.twig", compact("user"));
})->setName('account');


$app->run();

