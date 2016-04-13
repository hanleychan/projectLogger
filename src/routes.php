<?php 

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

    $name = trim($args["name"]);
    return $response->withRedirect($router->pathFor("fetchProjectLogs", compact("name")));
})->setName('project');


// Route for adding a project log entry 
$app->post('/project/{name}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $name = trim($args["name"]);

    // Fetch project
    if(!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is a member of this project
    if(!ProjectMember::isProjectMemberByProjectName($this->db, $name, $this->session->userID)) {
        $this->flash->addMessage("fail", "You do not have permission to add a new log to this project");
        return $response->withRedirect($router->pathFor('fetchAddNewLog', compact("name")));
    }

    $hours = (int)trim($request->getParam("hours"));
    $minutes = (int)trim($request->getParam("minutes"));
    $date = $request->getParam("datePicker"); 
    $comment = trim($request->getParam("comment"));
    $inputError = false;

    if(!$date = ProjectLog::formatDateToSQL($date)) {
        $this->flash->addMessage("fail", "Invalid date entered");
        $inputError = true;
    }

    if(!ProjectLog::isValidTime($hours, $minutes)) {
        $this->flash->addMessage("fail", "Time must be between 0 to 24 hours");
        $inputError = true;
    }

    if($inputError) {
        return $response->withRedirect($router->pathFor('fetchAddNewLog', compact("name")));
    }

    // Add log to database
    $projectLog = new ProjectLog($this->db);
    $projectLog->projectTime = "{$hours}:{$minutes}:00";
    $projectLog->userID = $this->session->userID;
    $projectLog->projectID = $project->id;
    $projectLog->comment = $comment;
    $projectLog->date = $date;
    $projectLog->save();

    $this->flash->addMessage("success", "Log successfully added to project");
    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact("name")));
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

    // Check if user is a member of this project
    if(!$project = Project::findProjectByNameAndUser($this->db, $name, $this->session->userID)) {
        $project = Project::findProjectByName($this->db, $name);

        // Can't fetch project (Project doesn't exist)
        if(!$project) {
            $this->flash->addMessage("fail", "Project does not exist");
            return $response->withRedirect($router->pathFor('projects'));
        }
        $projectMember = false;
    } else {
        $projectMember = true;
    }

    $projectLogs = ProjectLog::findLogsByProjectName($this->db, $name);
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $this->session->userID);
    $user = User::findById($this->db, $this->session->userID);

    if($projectLogs) {
        foreach($projectLogs as $projectLog) {
            // Reformat project time for a log entry
            $hours = (int)substr(trim($projectLog->projectTime), 0, 2);
            $mins = (int)substr(trim($projectLog->projectTime), 3, 2);
            $hours .= (($hours > 1) ? " hrs" : " hr");
            $mins .= (($mins > 1) ? " mins" : " min"); 
            $projectLog->projectHours = $hours;
            $projectLog->projectMins = $mins;

            // Check if project log belongs to user (check if edittable by user)
            if($projectLog->userID === $user->id) {
                $projectLog->canEdit = true;
            }
        }
    }

    return $this->view->render($response, "project.twig", compact("user", "project", "projectLogs",
                                                                  "page", "projectMember", "isAdmin",
                                                                  "name"));
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
    $user = User::findById($this->db, $this->session->userID);
    
    // Check if user is a member of this project
    if(!$project = Project::findProjectByNameAndUser($this->db, $name, $user->id)) {
        $project = Project::findProjectByName($this->db, $name);

        // Can't fetch project (Project doesn't exist)
        if(!$project) {
            $this->flash->addMessage("fail", "Project does not exist");
            return $reponse->withRedirect($router->pathFor('projects'));
        }

        $projectMember = false;
    } else {
        $projectMember = true;
    }

    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name); 
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    $isOwner = ($project->ownerID === $user->id) ? true : false;
 
    // Fetch project join requests
    if($isAdmin) {
        $requests = RequestJoinProject::getRequestsByProjectName($this->db, $name); 
    }

    return $this->view->render($response, "project.twig", compact("user", "project", "projectMembers", 
                                                                  "page", "projectMember", "isAdmin",
                                                                  "isOwner", "requests"));
})->setName("fetchProjectMembers");


// Route for accepting and declining project membership
$app->post('/project/{name}/members/request/{username}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $action = trim($request->getParam("action"));
    $name = trim($args["name"]);
    $username = trim($args["username"]);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if(!$project =  Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    } 

    // Check if user is admin
    if(!ProjectMember::isProjectAdmin($this->db, $name, $user->id)) {
        $this->flash->addMessage("fail", "You do not have permission to perform this action");
        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact("name")));
    }

    // Fetch request
    if(!$request = RequestJoinProject::getRequestByProjectNameAndUsername($this->db, $name, $username)) {
        $this->flash->addMessage("fail", "Could not find request");
        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact("name")));
    }

    // Remove request
    $request->delete();

    if($action === "accept") {
        // Add user to project member database 
        $projectMember = new ProjectMember($this->db);
        $projectMember->userID = $request->userID;
        $projectMember->projectID = $project->id;
        $projectMember->isAdmin = false;
        $projectMember->save();

        $this->flash->addMessage("success", "You have added {$username} to this project");
    } elseif ($action === "decline") {
        $this->flash->addMessage("success", "You have declined {$username} from joining this project");
    } else {
        $this->flash->addMessage("fail", "There was a problem processing this request");
    }

    return $response->withRedirect($router->pathFor('fetchProjectMembers', compact("name")));
})->setName('processMemberRequest');

$app->get('/project/{name}/remove/{username}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $page = "removeMember";
    $name = trim($args["name"]);
    $username = trim($args["username"]);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if(!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project member
    if(!$projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username)) {
        $this->flash->addMessage("fail", "User not found");
        return $response->withRedirect($router->pathFor("fetchProjectMembers", compact("name")));
    }

    // Check for permission to remove member
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    if(!(($isAdmin && !$projectMember->isAdmin) || ($project->ownerID === $user->id))) {
        $this->flash->addMessage("fail", "You do not have permission to remove this member");
        return $response->withRedirect($router->pathFor("fetchProjectMembers", compact("name")));
    }

    return $this->view->render($response, 'project.twig', compact("user", "project", "page", "projectMember"));
})->setName('confirmRemoveMember');

// Route for removing a user from a project
$app->post('/project/{name}/remove/{username}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    return "PROCESS REMOVE MEMBER PAGE";
})->setName('processRemoveMember');


// Route for adding a new project log entry
$app->get('/project/{name}/newLog', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $page = "addNewLog";
    $name = trim($args["name"]);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if(!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is a member of this project
    if(!$project = Project::findProjectByNameAndUser($this->db, $name, $user->id)) {
        $this->flash->addMessage("fail", "You do not have permission to add a new log to this project");
        return $response->withRedirect($router->pathFor('projects'));
    }

    $projectMember = true;

    return $this->view->render($response, "project.twig", compact("user", "project", "projectMember", "page"));
})->setName("fetchAddNewLog");


// Route for editing an existing log
$app->get('/project/{name}/edit/{logID}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $page = "editLog";
    $name = trim($args["name"]);
    $logID = (int)$args["logID"];
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if(!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project log entry
    if(!$projectLog = ProjectLog::findById($this->db, $logID)) {
        $this->flash->addMessage("fail", "Log does not exist");
        return $response->withRedirect($router->pathFor('project', compact("name")));
    }

    // check if user is admin or log entry belongs to user 
    if (!(ProjectMember::isProjectAdmin($this->db, $name, $user->id) || $user->id === $projectLog->userID)) {   
        $this->flash->addMessage("fail", "You do not have permission to edit this log entry");
        return $response->withRedirect($router->pathFor('project', compact("name")));
    }
        
    // fetch project
    if(!$project = Project::findProjectByNameAndUser($this->db, $name, $this->session->userID)) {
        $this->flash->addMessage("fail", "Could not fetch project");
        return $response->withRedirect($router->pathFor('project', compact("name")));
    }

    // Reformat date and time formats
    $projectLog->date = ProjectLog::formatDateFromSQL($projectLog->date);
    $projectLog->hours = (int)substr($projectLog->projectTime, 0, 2);
    $projectLog->minutes = (int)substr($projectLog->projectTime, 3, 2);
    $projectMember = true;

    return $this->view->render($response, "project.twig", compact("user", "project", "projectLog", "page", "projectMember"));
})->setName('fetchEditLog');


// Route for processing edit log changes
$app->post('/project/{name}/edit/{logID}', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $name = trim($args["name"]);
    $logID = (int)($args["logID"]);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if(!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project log entry
    if(!$projectLog = ProjectLog::findById($this->db, $logID)) {
        $this->flash->addMessage("fail", "Log doesn't exist");
        return $response->withRedirect($router->pathFor('fetchProjectLog', compact('name'))); 
    }

    // check if is admin or log entry belongs to user 
    if(!(ProjectMember::isProjectAdmin($this->db, $name, $user->id) || $user->id === $projectLog->userID)) {
        $this->flash->addMessage("fail", "You do not have permission to edit this log");
        return $response->withRedirect($router->pathFor('fetchProjectLog', compact('name'))); 
    }

    $hours = (int)trim($request->getParam("hours"));
    $minutes = (int)trim($request->getParam("minutes"));
    $date = $request->getParam("datePicker"); 
    $projectID = (int)trim($request->getParam("projectID"));
    $comment = trim($request->getParam("comment"));
    $inputError = false;

    if(!$date = ProjectLog::formatDateToSQL($date)) {
        $this->flash->addMessage("fail", "Invalid date entered");
        $inputError = true;
    }

    if(!ProjectLog::isValidTime($hours, $minutes)) {
        $this->flash->addMessage("fail", "Time must be between 0 to 24 hours");
        $inputError = true;
    }

    if($inputError) {
        return $response->withRedirect($router->pathFor('fetchEditLog', compact('name', 'logID')));
    }

    // Update log in database
    $projectLog->date = $date;
    $projectLog->projectTime = "{$hours}:{$minutes}:00";
    $projectLog->comment = $comment;
    $projectLog->save();

    $this->flash->addMessage("success", "Log entry successfully updated");
    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->setName('editLog');

// Route for requesting to join a project group
$app->post('/project/{name}/request', function($request, $response, $args) {
    $router = $this->router;
    // Redirect to login page if not logged in
    if(!$this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('login'));
    }

    $name = trim($args["name"]);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if(!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage("fail", "Project does not exist");
        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is already a member of this project
    if(ProjectMember::isProjectMemberByProjectName($this->db, $name, $user->id)) {
        $this->flash->addMessage("fail", "You are already a member of this project");
        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Check if request already exists
    if(RequestJoinProject::doesRequestExistByProjectName($this->db, $name, $user->id)) {
        $this->flash->addMessage("fail", "You have already requested to join this project");
        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Add request to database
    $requestJoinProject = new RequestJoinProject($this->db);
    $requestJoinProject->userID = $user->id;
    $requestJoinProject->projectID = $project->id;
    $requestJoinProject->save();

    $this->flash->addMessage("success", "Request to join project sent");
    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->setName('requestJoin');


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

