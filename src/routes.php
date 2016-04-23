<?php


// Home route
$app->get('/', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    // Fetch notifications
    $notifications = Notification::getNotifications($this->db, $user->id);

    // Fetch pending actions
    $pendingProjectActions = RequestJoinProject::getAllRequestsForOwner($this->db, $user->id);

    // Fetch pending project requests
    $pendingProjects = RequestJoinProject::getAllRequestsForUser($this->db, $user->id);

    return $this->view->render($response, 'index.twig', compact('user', 'notifications', 'pendingProjectActions', 'pendingProjects'));
})->add($redirectToLoginMW)->setName('home');

$app->post('/deleteAllNotifications', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    // Fetch notifications
    $notifications = Notification::getNotifications($this->db, $user->id);
    foreach ($notifications as $notification) {
        $notification->delete();
    }

    $router = $this->router;

    return $response->withRedirect($router->pathFor('home'));
})->add($redirectToLoginMW)->setName('deleteAllNotifications');

$app->post('/deleteNotification', function ($request, $response) {
    $isAJAX = false;
    $error = false;
    $loginExpired = false;

    // Check if AJAX request
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $isAJAX = true;
    }

    // Check if logged in
    if ($isAJAX) {
        if (!$this->session->isLoggedIn()) {
            $error = true;
            $loginExpired = true;

            return json_encode(compact('error', 'loginExpired'));
        }
    }

    $router = $this->router;
    $notificationID = $request->getParam('notificationID');
    $user = User::findById($this->db, $this->session->userID);

    // Fetch notification
    if (!$notification = Notification::findById($this->db, $notificationID)) {
        $error = true;

        if ($isAJAX) {
            return json_encode(compact('error', 'loginExpired'));
        } else {
            $this->flash->addMessage('fail', 'Could not fetch notification');

            return $response->withRedirect($router->pathFor('home'));
        }
    }

    // Check if notification belongs to user
    if ($notification->userID !== $user->id) {
        $error = true;

        if ($isAJAX) {
            return json_encode(compact('error', 'loginExpired'));
        } else {
            $this->flash->addMessage('fail', 'You do not have permission to perform this action');

            return $response->withRedirect($router->pathFor('home'));
        }
    }

    // Remove notification from database
    if ($error === false) {
        $notification->delete();
    }

    if ($isAJAX) {
        return json_encode(compact('error', 'loginExpired'));
    } else {
        $this->flash->addMessage('success', 'Notification deleted');

        return $response->withRedirect($router->pathFor('home'));
    }

})->add($redirectToLoginMW)->setName('deleteNotification');

/*
 * Register Routes
 */

// Register route
$app->get('/register', function ($request, $response) {
    // Get form data if available
    $postData = $this->session->getPostData();

    return $this->view->render($response, 'register.twig', compact('postData'));
})->add($redirectToMainPageMW)->setName('register');

// Process register form
$app->post('/register', function ($request, $response) {
    $username = strtolower(trim($request->getParam('username')));
    $password = $request->getParam('password');
    $password2 = $request->getParam('password2');
    $router = $this->router;

    // Check if username is valid 
    if (User::fetchUser($this->db, $username)) {
        $this->flash->addMessage('fail', "Username {$username} already exists");

        // Store form data in session variable
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('register'));
    } elseif (!User::isValidFormatUsername($username)) {
        $this->flash->addMessage('fail',
                                 'Username can contain only letters and numbers and be between '.
                                 User::USERNAME_MIN_LENGTH.' & '.User::USERNAME_MAX_LENGTH.
                                 ' characters long');
        // Store form data in session variable
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('register'));
    } elseif (!User::isValidPassword($password)) {
        $this->flash->addMessage('fail',
                                 'Passwords must be at least '.USER::PASSWORD_MIN_LENGTH.' characters long');

        // Store form data in session variable
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('register'));
    } elseif (!User::doPasswordsMatch($password, $password2)) {
        $this->flash->addMessage('fail', 'Passwords must match');

        // Store form data in session variable
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('register'));
    } else {
        $user = new User($this->db);
        $user->username = $username;
        $user->password = User::encryptPassword($password);
        $user->save();
        $this->flash->addMessage('success', 'You have successfully registered');

        // redirect to login page
        return $response->withRedirect($router->pathFor('login'));
    }

})->add($redirectToMainPageMW)->setName('processRegister');

/*
 * Login Routes
 */

// Login route
$app->get('/login', function ($request, $response) {
    return $this->view->render($response, 'login.twig');
})->add($redirectToMainPageMW)->setName('login');

// Process login form
$app->post('/login', function ($request, $response) {
    $username = strtolower(trim($request->getParam('username')));
    $password = $request->getParam('password');
    $router = $this->router;

    if ($user = User::fetchUser($this->db, $username)) {
        if (password_verify($password, $user->password)) {
            // login user and redirect to main page
            $this->session->login($user);
            $this->flash->addMessage('success', 'Login success');

            return $response->withRedirect($router->pathFor('home'));
        }
    }

    $this->flash->addMessage('fail', 'Username and/or password is incorrect');

    return $response->withRedirect($router->pathFor('login'));
})->add($redirectToMainPageMW)->setName('processLogin');

// Logout route
$app->get('/logout', function ($request, $response) {
    $this->session->logout();
    $this->flash->addMessage('success', 'Logout success');

    $router = $this->router;

    return $response->withRedirect($router->pathFor('login'));
})->add($redirectToLoginMW)->setName('logout');

/*
 * Project Routes
 */

// Projects route
$app->get('/projects', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);
    $projects = Project::findProjectsByUser($this->db, $user->id);

    return $this->view->render($response, 'projects.twig', compact('user', 'projects'));
})->add($redirectToLoginMW)->setName('projects');

$app->get('/projects/all', function ($request, $response) {
    $isAJAX = false;
    $error = false;
    $loginExpired = false;
    $search = trim($request->getParam("search"));

    // Check if AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $isAJAX = true;
    }

    if($this->session->isLoggedIn()) {
        $user = User::findById($this->db, $this->session->userID);
    }


    $numProjects = Project::getTotalProjectsBySearch($this->db, $search);
    $pageNum = (int)$request->getParam("page");
    $numItemsPerPage = 20;
    $pagination = new Pagination($numProjects, $pageNum, $numItemsPerPage);
    $offset = $pagination->calculateOffset();
    
    $projects = Project::findProjectsBySearch($this->db, $search, $numItemsPerPage, $offset);

    if ($isAJAX) {
        return $this->view->render($response, 'fetchProjectsListByFilter.twig', compact("projects", "pagination", "search"));
    } else {
        return $this->view->render($response, 'allProjects.twig', compact('user', 'projects', 'search', "pagination"));
    }
})->add($redirectToLoginMW)->setName('allProjects');

// Add new project route
$app->get('/projects/add', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    return $this->view->render($response, 'addProject.twig', compact('user'));
})->add($redirectToLoginMW)->setName('addProject');

// Process add new project route
$app->post('/projects/add', function ($request, $response) {
    $router = $this->router;
    $user = User::findById($this->db, $this->session->userID);
    $projectName = trim($request->getParam('projectName'));

    if (Project::doesProjectExist($this->db, $projectName)) {
        $this->flash->addMessage('fail', 'Project name already exists');

        return $response->withRedirect($router->pathFor('addProject'));
    }

    if (Project::isValidProjectName($projectName)) {
        $project = new Project($this->db);
        $project->projectName = $projectName;
        $project->ownerID = $user->id;
        $project->save();

        $projectMember = new ProjectMember($this->db);
        $projectMember->userID = $user->id;
        $projectMember->projectID = $project->id;
        $projectMember->isAdmin = true;
        $projectMember->save();

        $this->flash->addMessage('success', 'Project successfully added');

        return $response->withRedirect($router->pathFor('projects'));
    } else {
        $this->flash->addMessage('fail', 'Project name has an invalid format');

        return $response->withRedirect($router->pathFor('addProject'));
    }
})->add($redirectToLoginMW)->setName('processAddProject');

// Route for a specific project
$app->get('/project/{name}', function ($request, $response, $args) {
    $name = trim($args['name']);

    $router = $this->router;

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('project');

// Route for adding a project log entry 
$app->post('/project/{name}', function ($request, $response, $args) {
    $router = $this->router;
    $name = trim($args['name']);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is a member of this project
    if (!ProjectMember::isProjectMemberByProjectName($this->db, $name, $this->session->userID)) {
        $this->flash->addMessage('fail', 'You do not have permission to add a new log to this project');

        return $response->withRedirect($router->pathFor('fetchAddNewLog', compact('name')));
    }

    $hours = (int) trim($request->getParam('hours'));
    $minutes = (int) trim($request->getParam('minutes'));
    $date = $request->getParam('datePicker');
    $comment = trim($request->getParam('comment'));
    $inputError = false;

    if (!$date = ProjectLog::formatDateToSQL($date)) {
        $this->flash->addMessage('fail', 'Invalid date entered');
        $inputError = true;
    }

    if (!ProjectLog::isValidTime($hours, $minutes)) {
        $this->flash->addMessage('fail', 'Time must be between 1 minute to 24 hours');
        $inputError = true;
    }

    if ($inputError) {
        return $response->withRedirect($router->pathFor('fetchAddNewLog', compact('name')));
    }

    // Add log to database
    $projectLog = new ProjectLog($this->db);
    $projectLog->userID = $this->session->userID;
    $projectLog->projectID = $project->id;
    $projectLog->comment = $comment;
    $projectLog->date = $date;
    $projectLog->minutes = ProjectLog::calculateTotalMinutes($hours, $minutes);
    $projectLog->save();

    $this->flash->addMessage('success', 'Log successfully added to project');

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('addProjectLog');

// Route for getting all log entries for a project
$app->get('/project/{name}/projectLogs', function ($request, $response, $args) {
    $router = $this->router;
    $page = 'viewLogs';
    $name = trim($args['name']);

    $user = User::findById($this->db, $this->session->userID);

    $displayLogsUsername = $request->getParam('show');
    if ($displayLogsUsername === 'all') {
        $displayLogsUsername = '';
    }

    // Check if user is a member of this project
    if (!$project = Project::findProjectByNameAndUser($this->db, $name, $user->id)) {
        $project = Project::findProjectByName($this->db, $name);

        // Can't fetch project (Project doesn't exist)
        if (!$project) {
            $this->flash->addMessage('fail', 'Project does not exist');

            return $response->withRedirect($router->pathFor('projects'));
        }
        $projectMember = false;
    } else {
        $projectMember = true;
    }

    // Fetch project members
    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name, false);

    // Get number of logs
    $numLogEntries =  ProjectLog::getNumLogsByProjectName($this->db, $name, $displayLogsUsername);

    // Setup pagination
    $pageNum = (int)$request->getParam("page");
    $numEntriesPerPage = 20;
    $pagination = new Pagination($numLogEntries, $pageNum, $numEntriesPerPage); 
    $offset = $pagination->calculateOffset();
    $projectLogs = ProjectLog::findLogsByProjectName($this->db, $name, $displayLogsUsername, $numEntriesPerPage, $offset);

    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $this->session->userID);

    // Check if user has requested to join this project if not a project member
    if (!$projectMember && RequestJoinProject::getRequestByProjectName($this->db, $name, $user->id)) {
        $userHasJoinRequest = true;
    } else {
        $userHasJoinRequest = false;
    }

    if ($projectLogs) {
        foreach ($projectLogs as $projectLog) {
            // Reformat project time for a log entry
            $projectLog->projectTime = ProjectLog::formatTimeOutput($projectLog->minutes);

            // Check if project log belongs to user (check if edittable by user)
            if ($projectLog->userID === $user->id) {
                $projectLog->canEdit = true;
            }
        }
    }

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    if ($projectMember) {
        $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
        $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);
    } else {
        $totalMinutesByMe = false;
    }
    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectLogs',
                                                                  'page', 'projectMember', 'isAdmin',
                                                                  'userHasJoinRequest', 'totalMinutes',
                                                                  'totalMinutesByMe', 'projectMembers',
                                                                  'displayLogsUsername', 'pagination'));
})->add($redirectToLoginMW)->setName('fetchProjectLogs');

// Route for fetching members of a project
$app->get('/project/{name}/members', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'members';
    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Check if user is a member of this project
    if (!$project = Project::findProjectByNameAndUser($this->db, $name, $user->id)) {
        $project = Project::findProjectByName($this->db, $name);

        // Can't fetch project (Project doesn't exist)
        if (!$project) {
            $this->flash->addMessage('fail', 'Project does not exist');

            return $reponse->withRedirect($router->pathFor('projects'));
        }

        $projectMember = false;
    } else {
        $projectMember = true;
    }

    // Check if user has requested to join this project if not a project member
    if (!$projectMember && RequestJoinProject::getRequestByProjectName($this->db, $name, $user->id)) {
        $userHasJoinRequest = true;
    } else {
        $userHasJoinRequest = false;
    }

    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name);
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    $isOwner = ($project->ownerID === $user->id) ? true : false;

    // Fetch project join requests
    if ($isAdmin) {
        $requests = RequestJoinProject::getRequestsByProjectName($this->db, $name);
    }

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    if ($projectMember) {
        $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
        $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);
    } else {
        $totalMinutesByMe = false;
    }

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectMembers',
                                                                  'page', 'projectMember', 'isAdmin',
                                                                  'isOwner', 'requests', 'userHasJoinRequest',
                                                                  'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('fetchProjectMembers');

// Route for accepting and declining project membership
$app->post('/project/{name}/members/request/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $action = trim($request->getParam('action'));
    $name = trim($args['name']);
    $username = trim($args['username']);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is admin
    if (!ProjectMember::isProjectAdmin($this->db, $name, $user->id)) {
        $this->flash->addMessage('fail', 'You do not have permission to perform this action');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    // Fetch request
    if (!$request = RequestJoinProject::getRequestByProjectNameAndUsername($this->db, $name, $username)) {
        $this->flash->addMessage('fail', 'Could not find member request');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    // Remove request
    $request->delete();

    if ($action === 'accept') {
        // Add user to project member database 
        $projectMember = new ProjectMember($this->db);
        $projectMember->userID = $request->userID;
        $projectMember->projectID = $project->id;
        $projectMember->isAdmin = false;
        $projectMember->save();

        // Add notification for accepted user 
        $notification = new Notification($this->db);
        $notification->date = date('Y-m-d');
        $notification->userID = $projectMember->userID;
        $notification->notification = 'Your request to join project ';
        $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
        $notification->notification .= "has been approved by {$user->username}";
        $notification->save();

        $this->flash->addMessage('success', "You have added {$username} to this project");
    } elseif ($action === 'decline') {
        // Add notification for declined user 
        $notification = new Notification($this->db);
        $notification->date = date('Y-m-d');
        $notification->userID = $request->userID;
        $notification->notification = 'Your request to join project ';
        $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
        $notification->notification .= "has been declined by {$user->username}";
        $notification->save();

        $this->flash->addMessage('success', "You have declined {$username} from joining this project");
    } else {
        $this->flash->addMessage('fail', 'There was a problem processing this request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
})->add($redirectToLoginMW)->setName('processMemberRequest');

// Confirm remove user from project route
$app->get('/project/{name}/remove/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'removeMember';
    $name = trim($args['name']);
    $username = trim($args['username']);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project member
    if (!$projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username)) {
        $this->flash->addMessage('fail', 'User not found');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    // Check for permission to remove member
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    if (!(($isAdmin && !$projectMember->isAdmin) || ($project->ownerID === $user->id))) {
        $this->flash->addMessage('fail', 'You do not have permission to access this page');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMember', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('confirmRemoveMember');

// Route for processing removing a user from a project
$app->post('/project/{name}/remove/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $username = trim($args['username']);
    $user = User::findById($this->db, $this->session->userID);
    $action = $request->getParam('action');

    // Check if project exists
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project member
    if (!$projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username)) {
        $this->flash->addMessage('fail', 'User not found');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    // Check for permission to remove member
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    if (!(($isAdmin && !$projectMember->isAdmin) || ($project->ownerID === $user->id))) {
        $this->flash->addMessage('fail', 'You do not have permission to access this page');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    if ($action === 'Yes') {
        // Remove user from project database
        $projectMember->delete();

        // Add notification removed user 
        $notification = new Notification($this->db);
        $notification->date = date('Y-m-d');
        $notification->userID = $projectMember->userID;
        $notification->notification = 'You were removed from project ';
        $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
        $notification->notification .= "by {$user->username}";
        $notification->save();

        $this->flash->addMessage('success', "{$projectMember->username} has been removed");
    } elseif ($action !== 'No') {
        $this->flash->addMessage('fail', 'There was an error processing this request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
})->add($redirectToLoginMW)->setName('processRemoveMember');

// Route for changing a project member to admin status or removing admin status
$app->post('/project/{name}/rank/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $username = trim($args['username']);
    $user = User::findById($this->db, $this->session->userID);
    $action = $request->getParam('action');

    // fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    // fetch project member
    if (!$projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username)) {
        $this->flash->addMessage('fail', 'Could not find user');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    if ($action === 'promote') {
        if ($projectMember->isAdmin == false) {
            $projectMember->isAdmin = true;
            $projectMember->save();

            // Add notification
            $notification = new Notification($this->db);
            $notification->date = date('Y-m-d');
            $notification->userID = $projectMember->userID;
            $notification->notification = 'Your rank was changed to admin for project ';
            $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
            $notification->notification .= "by {$user->username}";
            $notification->save();

            $this->flash->addMessage('success', "{$username} is now an admin of project {$name}");
        } else {
            $this->flash->addMessage('fail', "{$username} is already an admin of project {$name}");
        }
    } elseif ($action === 'demote') {
        if ($projectMember->isAdmin == true) {
            $projectMember->isAdmin = false;
            $projectMember->save();

            // Add notification
            $notification = new Notification($this->db);
            $notification->date = date('Y-m-d');
            $notification->userID = $projectMember->userID;
            $notification->notification = 'Your rank was changed to normal user for project ';
            $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
            $notification->notification .= "by {$user->username}";
            $notification->save();
            $this->flash->addMessage('success', "{$username} is no longer an admin of project {$name}");
        } else {
            $this->flash->addMessage('fail', "{$username} is already a regular member of project {$name}");
        }
    } else {
        $this->flash->addMessage('fail', 'There was an error processing this request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
})->add($redirectToLoginMW)->setName('processToggleAdmin');

// Route for adding a new project log entry
$app->get('/project/{name}/newLog', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'addNewLog';
    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is a member of this project
    if (!$project = Project::findProjectByNameAndUser($this->db, $name, $user->id)) {
        $this->flash->addMessage('fail', 'You do not have permission to add a new log to this project');

        return $response->withRedirect($router->pathFor('projects'));
    }

    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectMember', 'page',
                                                                  'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('fetchAddNewLog');

// Route for editing an existing log
$app->get('/project/{name}/edit/{logID}', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'editLog';
    $name = trim($args['name']);
    $logID = (int) $args['logID'];
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project log entry
    if (!$projectLog = ProjectLog::findById($this->db, $logID)) {
        $this->flash->addMessage('fail', 'Log does not exist');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // check if user is admin or log entry belongs to user 
    if (!(ProjectMember::isProjectAdmin($this->db, $name, $user->id) || $user->id === $projectLog->userID)) {
        $this->flash->addMessage('fail', 'You do not have permission to edit this log entry');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // fetch project
    if (!$project = Project::findProjectByNameAndUser($this->db, $name, $this->session->userID)) {
        $this->flash->addMessage('fail', 'Could not fetch project');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Fetch post data
    $postData = $this->session->getPostData();

    // Reformat date and time formats
    $projectLog->date = ProjectLog::formatDateFromSQL($projectLog->date);
    $projectLog->hours = floor($projectLog->minutes / 60);
    $projectLog->minutes = $projectLog->minutes % 60;
    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectLog', 'page', 'projectMember', 'totalMinutes', 'totalMinutesByMe', 'postData'));
})->add($redirectToLoginMW)->setName('fetchEditLog');

$app->post('/project{name}/deleteLog/{logID}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $logID = (int) ($args['logID']);
    $action = $request->getParam('action');
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project log entry
    if (!$projectLog = ProjectLog::findById($this->db, $logID)) {
        $this->flash->addMessage('fail', "Log doesn't exist");

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // check if is admin or log entry belongs to user 
    if (!(ProjectMember::isProjectAdmin($this->db, $name, $user->id) || $user->id === $projectLog->userID)) {
        $this->flash->addMessage('fail', 'You do not have permission to delete this log');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    if ($action === 'delete') {
        // Delete log in database
        $projectLog->delete();

        $this->flash->addMessage('success', 'Log entry successfully deleted');
    } elseif ($action !== 'cancel') {
        $this->flash->addMessage('fail', 'There was an error processing your request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('deleteLog');

// Route for processing edit log changes
$app->post('/project/{name}/edit/{logID}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $logID = (int) ($args['logID']);
    $action = $request->getParam('action');
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // fetch project log entry
    if (!$projectLog = ProjectLog::findById($this->db, $logID)) {
        $this->flash->addMessage('fail', "Log doesn't exist");

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // check if is admin or log entry belongs to user 
    if (!(ProjectMember::isProjectAdmin($this->db, $name, $user->id) || $user->id === $projectLog->userID)) {
        $this->flash->addMessage('fail', 'You do not have permission to edit this log');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $hours = (int) trim($request->getParam('hours'));
    $minutes = (int) trim($request->getParam('minutes'));
    $date = $request->getParam('datePicker');
    $comment = trim($request->getParam('comment'));
    $inputError = false;

    if (!$date = ProjectLog::formatDateToSQL($date)) {
        $this->flash->addMessage('fail', 'Invalid date entered');
        $inputError = true;
    }

    if (!ProjectLog::isValidTime($hours, $minutes)) {
        $this->flash->addMessage('fail', 'Time must be between 0 to 24 hours');
        $inputError = true;
    }

    if ($inputError) {
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('fetchEditLog', compact('name', 'logID')));
    }

    if ($action === 'save') {
        // Update log in database
        $projectLog->date = $date;
        $projectLog->projectTime = "{$hours}:{$minutes}:00";
        $projectLog->comment = $comment;
        $projectLog->save();
        $this->flash->addMessage('success', 'Log entry successfully updated');
    } elseif ($action !== 'cancel') {
        $this->flash->addMessage('fail', 'There was an error processing your request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('editLog');

// Route for requesting to join a project group
$app->post('/project/{name}/request', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is already a member of this project
    if (ProjectMember::isProjectMemberByProjectName($this->db, $name, $user->id)) {
        $this->flash->addMessage('fail', 'You are already a member of this project');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Check if request already exists
    if (RequestJoinProject::getRequestByProjectName($this->db, $name, $user->id)) {
        $this->flash->addMessage('fail', 'You have already requested to join this project');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Add request to database
    $requestJoinProject = new RequestJoinProject($this->db);
    $requestJoinProject->userID = $user->id;
    $requestJoinProject->projectID = $project->id;
    $requestJoinProject->save();

    $this->flash->addMessage('success', 'Request to join project sent');

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('requestJoin');

// Route for canceling a request to join a project
$app->post('/project/{name}/request/cancel', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Fetch request 
    if (!$requestJoinProject = RequestJoinProject::getRequestByProjectName($this->db, $name, $user->id)) {
        $this->flash->addMessage('fail', 'You do not have a pending request to join this project');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Remove request from database
    $requestJoinProject->delete();

    $this->flash->addMessage('success', 'Request to join this project has been cancelled');

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('cancelRequestJoin');

// Route for project actions
$app->get('/project/{name}/actions', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'projectActions';
    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is a project member of this project
    if (!ProjectMember::isProjectMemberByProjectName($this->db, $name, $user->id)) {
        $this->flash->addMessage('fail', 'You are not a member of this project');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $isOwner = $project->ownerID === $user->id;
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    if ($projectMember) {
        $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
        $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);
    } else {
        $totalMinutesByMe = false;
    }

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'isOwner', 'page', 'projectMember', 'isAdmin', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('projectActions');

$app->get('/project/{name}/leave/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'leaveProject';
    $name = trim($args['name']);
    $username = trim($args['username']);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Fetch project member
    $projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username);

    // Check if project member is the user
    if (!$projectMember || ($projectMember->userID !== $user->id)) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    if ($projectMember) {
        $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
        $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);
    } else {
        $totalMinutesByMe = false;
    }

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMember', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('confirmLeaveProject');

$app->post('/project/{name}/leave/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $username = trim($args['username']);
    $action = trim($request->getParam('action'));
    $user = User::findById($this->db, $this->session->userID);

    // Check if project exists
    if (!Project::doesProjectExist($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Fetch project member
    $projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username);

    // Check if project member is the user
    if (!$projectMember || ($projectMember->userID !== $user->id)) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    if ($action === 'yes') {
        // Remove project member from database
        $projectMember->delete();

        $this->flash->addMessage('success', 'You have left this project');
    } elseif ($action !== 'no') {
        $this->flash->addMessage('fail', 'There was an error processing your request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('processLeaveProject');

$app->get('/project/{name}/delete', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'deleteProject';
    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is the owner
    if ($project->ownerID !== $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    if ($projectMember) {
        $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
        $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);
    } else {
        $totalMinutesByMe = false;
    }

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMember', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('confirmDeleteProject');

$app->post('/project/{name}/delete', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $action = trim($request->getParam('action'));
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user is the owner
    if ($project->ownerID !== $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $projectMember = true;
    if ($action === 'yes') {
        $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name);

        // Delete the project
        $project->delete();

        // Add a notification for all other project members
        foreach ($projectMembers as $projectMember) {
            if ($projectMember->userID != $user->id) {
                $notification = new Notification($this->db);
                $notification->userID = $projectMember->userID;
                $notification->date = date('Y-m-d');
                $notification->notification = "Project {$name} has been deleted ";
                $notification->notification .= "by {$user->username}";
                $notification->save();
            }
        }

        $this->flash->addMessage('success', "Project {$project->projectName} has been deleted successfully");
    } elseif ($action === 'no') {
        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
    } else {
        $this->flash->addMessage('fail', 'There was an error processing your request');
    }

    return $response->withRedirect($router->pathFor('projects'));
})->add($redirectToLoginMW)->setName('processDeleteProject');

$app->get('/project/{name}/rename', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'renameProject';
    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check for permission
    if (!ProjectMember::isProjectAdmin($this->db, $name, $user->id) || !$project->ownerID === $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $projectMember = true;

    // Get form session data if available
    $postData = $this->session->getPostData();

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectMember', 'page', 'projectMember', 'totalMinutes', 'totalMinutesByMe', 'postData'));
})->add($redirectToLoginMW)->setName('renameProject');

$app->post('/project/{name}/rename', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $newName = trim($request->getParam('projectName'));
    $action = trim($request->getParam('action'));
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check for permission
    if (!ProjectMember::isProjectAdmin($this->db, $name, $user->id) || !$project->ownerID === $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Validate new project name
    if (!Project::isValidProjectName($newName)) {
        $this->flash->addMessage('fail', 'New project name was entered in an invalid format');

        // Store form data in session
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('renameProject', compact('name')));
    }

    // Check if new name is the same as the existing name
    if ($name === $newName) {
        $this->flash->addMessage('fail', 'New project name is the same as the existing one');

        return $response->withRedirect($router->pathFor('renameProject', compact('name')));
    }

    // Check if project already exists
    if (Project::doesProjectExist($this->db, $newName) && strtolower($name) !== strtolower($newName)) {
        $this->flash->addMessage('fail', "Project {$newName} already exists");

        return $response->withRedirect($router->pathFor('renameProject', compact('name')));
    }

    if ($action === 'rename') {
        // Rename project in database
        $project->projectName = $newName;
        $project->save();

        // Add a notification for all other project members
        $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $newName);
        foreach ($projectMembers as $projectMember) {
            if ($projectMember->userID != $user->id) {
                $notification = new Notification($this->db);
                $notification->userID = $projectMember->userID;
                $notification->date = date('Y-m-d');
                $notification->notification = "Project {$name} has been renamed to ";
                $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', ['name' => $newName])}\">{$newName}</a> ";
                $notification->notification .= "by {$user->username}";
                $notification->save();
            }
        }

        $this->flash->addMessage('success', "Project {$name} has been renamed to {$newName} ");
    } elseif ($action !== 'cancel') {
        $this->flash->addMessage('fail', 'There was an error processing your request');
    }

    return $response->withRedirect($router->pathFor('projectActions', ['name' => $newName]));
})->add($redirectToLoginMW)->setName('processRenameProject');

$app->get('/project/{name}/transferOwnership', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'transferOwnership';
    $name = trim($args['name']);
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($route->pathFor('projects'));
    }

    // Check if user is the owner
    if ($project->ownerID !== $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Fetch project members
    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name);
    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMembers', 'projectMember', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('transferOwnership');

$app->post('/project/{name}/transferOwnership/confirm', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'confirmTransferOwnership';
    $name = trim($args['name']);
    $newOwner = trim($request->getParam('newOwner'));
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($route->pathFor('projects'));
    }

    // Check if user is the owner
    if ($project->ownerID !== $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Fetch new owner
    if (!$owner = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $newOwner)) {
        $this->flash->addMessage('fail', 'There was an error processing your request');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'page', 'project', 'owner', 'projectMember', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('confirmTransferOwnership');

$app->post('/project/{name}/transferOwnership/{newOwner}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $newOwner = trim($args['newOwner']);
    $action = trim($request->getParam('action'));
    $user = User::findById($this->db, $this->session->userID);

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');

        return $response->withRedirect($route->pathFor('projects'));
    }

    // Check if user is the owner
    if ($project->ownerID !== $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    // Fetch new owner
    if (!$projectMember = ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $newOwner)) {
        $this->flash->addMessage('fail', 'There was an error processing your request');

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    if ($action === 'yes') {
        $project->ownerID = $projectMember->userID;
        $project->save();

        // Make new owner admin
        if ($projectMember->isAdmin == false) {
            $projectMember->isAdmin = true;
            $projectMember->save();
        }

        // Add notification for new owner
        $notification = new Notification($this->db);
        $notification->date = date('Y-m-d');
        $notification->userID = $projectMember->userID;
        $notification->notification = "{$project->ownerName} has transferred ownership of project ";
        $notification->notification .= " <a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
        $notification->notification .= 'to you';
        $notification->save();

        $this->flash->addMessage('success', "Project ownership has been transfered to {$projectMember->username}");

        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
    } elseif ($action !== 'no') {
        $this->flash->addMessage('fail', 'There was an error processing your request');
    }

        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
})->add($redirectToLoginMW)->setName('processTransferOwnership');

$app->get('/profile/{username}', function ($request, $response, $args) {
    $user = User::findById($this->db, $this->session->userID);
})->add($redirectToLoginMW)->setName('userProfile');

/*
 * Account Routes
 */

// Account route
$app->get('/account', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    return $this->view->render($response, 'account.twig', compact('user'));
})->add($redirectToLoginMW)->setName('account');

$app->get('/account/profile', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    $maxPhotoSize = Profile::getMaxPhotoAllowedFileSizeInBytes();

    // Fetch profile if exists

    return $this->view->render($response, 'modifyProfile.twig', compact("user", "maxPhotoSize"));
})->add($redirectToLoginMW)->setName('modifyProfile');

$app->post('/account/profile/', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    $action = trim($this->request->getParam("action"));
    $name = trim(ucwords($this->request->getParam("name")));
    $otherInfo = trim($this->request->getParam("otherInfo"));
    $removePhoto = $this->request->getParam("removePhoto");
    $photo = $request->getUploadedFiles()['photo'];

    // Fetch profile if it exists otherwise create a new profile
    if($profile = Profile::getProfileByUserID($this->db, $user->id)) {
        $newProfile = false;
    } else {
        $profile = new Profile($this->db);
        $newProfile = true;
    }

    $profile->name = (!empty($name)) ? $name : null;
    $profile->otherInfo = (!empty($otherInfo)) ? $otherInfo : null;

    if($removePhoto === "removePhoto") { 
        // Remove old photo
        if(!$newProfile) {
            $photoFile = $profile->photoPath . '/' . $profile->photoName;
            if(file_exists($photoFile)) {
                unlink($photoFile);
                $profile->photoName = null;
                $profile->photoPath = null;
            }
        }
    } elseif ($photo->getError() === 0) {
        // Handle uploaded photo
        $uploadFileName = $photo->getClientFilename();
        $uploadMediaType = $photo->getClientMediaType();
        $photoInfo = getimagesize($photo->file);

        // Check if uploaded file is a valid photo
        if($photoInfo === false) {
            echo "Unable to determine image type of uploaded file";
            exit;
        }
        if($photoInfo[2] !== IMAGETYPE_GIF && $photoInfo[2] !== IMAGETYPE_JPEG && $photoInfo[2] !== IMAGETYPE_PNG) {
            echo "NOT A VALID PHOTO TYPE";
            exit;
        }
        
        // Create directory if not exists
        $uploadDirectory = "uploads/";
        $uploadDirectory .= $user->username[0] . "/";
        if(isset($user->username[1])) {
            $uploadDirectory .= $user->username[1] . "/";
        }
        $uploadDirectory .= $user->username;
        if(!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        $photo->moveTo("{$uploadDirectory}/$uploadFileName");
        $profile->photoName = $uploadFileName;
        $profile->photoPath = $uploadDirectory;
    }

    $profile->save();
})->add($redirectToLoginMW)->setName('processModifyProfile');

$app->get('/account/password', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);
    return "CHANGE PASSWORD PAGE";
})->add($redirectToLoginMW)->setName('changePassword');

$app->post('/account/password', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);
    return "PROCESS CHANGE PASSWORD";
})->add($redirectToLoginMW)->setName('processChangePassword');
