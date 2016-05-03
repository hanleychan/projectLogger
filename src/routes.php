<?php

// Home route
$app->get('/', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    // Fetch notifications
    $notifications = Notification::getNotifications($this->db, $user->id);

    // Fetch pending actions
    $pendingProjectActions = RequestJoinProject::getProjectsWithPendingRequestsForOwner($this->db, $user->id);

    // Fetch pending project requests
    $pendingProjects = RequestJoinProject::getAllRequestsForUser($this->db, $user->id);

    return $this->view->render($response, 'index.twig', compact('user', 'notifications',
                                                                'pendingProjectActions', 'pendingProjects'));
})->add($redirectToLoginMW)->setName('home');

// Process Deleting all notifications for a user
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

// Process Deleting a single notification
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

// Register page 
$app->get('/register', function ($request, $response) {
    // Get form data if available
    $postData = $this->session->getPostData();
    $maxPhotoSizeString = Profile::getMaxPhotoAllowedFileSizeString();

    return $this->view->render($response, 'register.twig', compact('postData', 'maxPhotoSizeString'));
})->add($redirectToMainPageMW)->setName('register');

// Process register form 
$app->post('/register', function ($request, $response) {
    $router = $this->router;
    $username = strtolower(trim($request->getParam('username')));
    $password = $request->getParam('password');
    $password2 = $request->getParam('password2');

    $name = trim(ucwords($this->request->getParam('name')));
    $otherInfo = trim($this->request->getParam('otherInfo'));
    $removePhoto = $this->request->getParam('removePhoto');
    $photo = $request->getUploadedFiles()['photo'];

    $inputError = false;

    // Check if username already exists
    if (User::fetchUser($this->db, $username)) {
        $inputError = true;
        $this->flash->addMessage('fail', "Username {$username} already exists");
    }

    // Check if username is valid 
    if (!User::isValidFormatUsername($username)) {
        $inputError = true;
        $this->flash->addMessage('fail',
                                 'Username can contain only letters and numbers and be between '.
                                 User::USERNAME_MIN_LENGTH.' & '.User::USERNAME_MAX_LENGTH.
                                 ' characters long');
    }

    if (!User::isValidPassword($password)) {
        $inputError = true;
        $this->flash->addMessage('fail',
                                 'Passwords must be at between '.
                                 User::PASSWORD_MIN_LENGTH.' & '.User::PASSWORD_MAX_LENGTH.
                                 ' characters long');
    }

    if (!User::doPasswordsMatch($password, $password2)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Passwords must match');
    }

    if (!Profile::isValidName($name)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Name field must be '.Profile::NAME_MAX_LENGTH.' characters or less');
    }
    if (!Profile::isValidOtherInfo($otherInfo)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Other info field must be '.Profile::OTHERINFO_MAX_LENGTH.' characters or less');
    }

    if ($photo->getError() !== 0 && $photo->getError() !== 4) {
        $inputError = true;
        if($photo->getError() === 1 || $photo->getError() === 2) {
            $this->flash->addMessage('fail', 'Image file is too large');
        } else {
            $this->flash->addMessage('fail', 'There was a problem processing the uploaded file');
        }
    }

    if ($photo->getError() === 0) {
        // Check if uploaded file is a valid photo
        if (!Profile::isValidImageFile($photo)) {
            $inputError = true;
            $this->flash->addMessage('fail', 'Unable to determine image type of the uploaded file');
        } elseif (!Profile::isValidImageFormat($photo)) {
            $inputError = true;
            $this->flash->addMessage('fail', 'Image is not a JPG, GIF, or PNG file');
        }
    }

    if ($inputError) {
        // Store form data in session variable
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('register'));
    }

    // Create a new user
    $user = new User($this->db);
    $user->username = $username;
    $user->password = User::encryptPassword($password);
    $user->joinDate = date('Y-m-d');

    // Create a new profile for the user
    $profile = new Profile($this->db);
    $profile->userID = $user->id;
    $profile->name = (!empty($name)) ? $name : null;
    $profile->otherInfo = (!empty($otherInfo)) ? $otherInfo : null;

    // Process photo upload
    if ($photo->getError() === 0) {
        $uploadFileName = $photo->getClientFilename();
        $uploadMediaType = $photo->getClientMediaType();

        // Create directory if not exists
        $uploadDirectory = 'uploads/';
        $uploadDirectory .= $user->username[0].'/';
        if (isset($user->username[1])) {
            $uploadDirectory .= $user->username[1].'/';
        }
        $uploadDirectory .= $user->username;
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        // Save photo to server
        $photo->moveTo("{$uploadDirectory}/{$uploadFileName}");

        // Resize photo
        Profile::resizePhoto("{$uploadDirectory}/{$uploadFileName}");

        $profile->photoName = $uploadFileName;
        $profile->photoPath = $uploadDirectory;
    }

    $user->save();

    $profile->userID = $user->id;
    $profile->save();

    // Log registration record
    $this->logger->addInfo('User Registration', ['username' => $user->username]);

    $this->flash->addMessage('success', 'You have successfully registered');
    return $response->withRedirect($router->pathFor('login'));
})->add($redirectToMainPageMW)->setName('processRegister');

/*
 * Login Routes
 */

// Login page
$app->get('/login', function ($request, $response) {
    return $this->view->render($response, 'login.twig');
})->add($redirectToMainPageMW)->setName('login');

// Process login page form
$app->post('/login', function ($request, $response) {
    $username = strtolower(trim($request->getParam('username')));
    $password = $request->getParam('password');
    $router = $this->router;

    if ($user = User::fetchUser($this->db, $username)) {
        if (password_verify($password, $user->password)) {
            // login user and redirect to main page
            $this->session->login($user);

            // Log login record
            $this->logger->addInfo('User Logged In', compact('username'));

            return $response->withRedirect($router->pathFor('home'));
        }
    } else {
        $this->flash->addMessage('fail', 'Invalid username/password combination');

        return $response->withRedirect($router->pathFor('login'));
    }
})->add($redirectToMainPageMW)->setName('processLogin');

// Process loggin out user 
$app->get('/logout', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);
    $this->session->logout();

    // Log logout record
    $this->logger->addInfo('User Logged Out',['username' => $user->username]); 

    $router = $this->router;
    return $response->withRedirect($router->pathFor('login'));
})->add($redirectToLoginMW)->setName('logout');

/*
 * Projects Routes
 */

// User projects page
$app->get('/projects', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    $numProjects = Project::getTotalProjectsForUser($this->db, $user->id);
    $pageNum = (int) $request->getParam('page');
    $numItemsPerPage = 20;
    $pagination = new Pagination($numProjects, $pageNum, $numItemsPerPage);
    $offset = $pagination->calculateOffset();

    $projects = Project::findProjectsByUser($this->db, $user->id, $numItemsPerPage, $offset);

    return $this->view->render($response, 'projects.twig', compact('user', 'projects', 'pagination'));
})->add($redirectToLoginMW)->setName('projects');

// All projects page
$app->get('/projects/all', function ($request, $response) {
    $isAJAX = false;
    $search = trim($request->getParam('search'));

    // Check if AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $isAJAX = true;
    }

    if ($this->session->isLoggedIn()) {
        $user = User::findById($this->db, $this->session->userID);
    }

    $numProjects = Project::getTotalProjectsBySearch($this->db, $search);
    $pageNum = (int) $request->getParam('page');
    $numItemsPerPage = 20;
    $pagination = new Pagination($numProjects, $pageNum, $numItemsPerPage);
    $offset = $pagination->calculateOffset();

    $projects = Project::findProjectsBySearch($this->db, $search, $numItemsPerPage, $offset);

    if ($isAJAX) {
        return $this->view->render($response, 'fetchAllProjectsListByFilter.twig', compact('projects', 'pagination', 'search'));
    } else {
        return $this->view->render($response, 'allProjects.twig', compact('user', 'projects', 'search', 'pagination'));
    }
})->add($redirectToLoginMW)->setName('allProjects');

// Add new project page 
$app->get('/projects/add', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);
    $postData = $this->session->getPostData();

    return $this->view->render($response, 'addProject.twig', compact('user', 'postData'));
})->add($redirectToLoginMW)->setName('addProject');

// Process add new project form 
$app->post('/projects/add', function ($request, $response) {
    $router = $this->router;

    $user = User::findById($this->db, $this->session->userID);
    $projectName = trim($request->getParam('projectName'));
    $action = trim(strtolower($request->getParam('action')));

    if ($action === 'cancel' || $action !== 'create') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('projects'));
    }

    $inputError = false;

    if (Project::doesProjectExist($this->db, $projectName)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Project name already exists');
    }

    if (!Project::isValidProjectName($projectName)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Project name has an invalid format');
    }

    if ($inputError) {
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('addProject'));
    }

    $project = new Project($this->db);
    $project->projectName = $projectName;
    $project->ownerID = $user->id;
    $project->dateAdded = date('Y-m-d');
    $project->save();

    $projectMember = new ProjectMember($this->db);
    $projectMember->userID = $user->id;
    $projectMember->projectID = $project->id;
    $projectMember->isAdmin = true;
    $projectMember->save();

    $this->flash->addMessage('success', 'Project successfully added');

    return $response->withRedirect($router->pathFor('projects'));
})->add($redirectToLoginMW)->setName('processAddProject');

/*
 * Project Routes
 */

// Load page for a specific project
$app->get('/project/{name}', function ($request, $response, $args) {
    $name = trim($args['name']);

    $router = $this->router;

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('project');

//  View project logs page
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

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Fetch project members
    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name, false);

    // Get number of logs
    $numLogEntries = ProjectLog::getNumLogsByProjectName($this->db, $name, $displayLogsUsername);

    // Setup pagination
    $pageNum = (int) $request->getParam('page');
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

// List project members page
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

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

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

// Process accepting or declining project membership requests
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

// Confirm remove user from project page
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
        $this->flash->addMessage('fail', 'You do not have permission to perform this action');

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMember', 'totalMinutes',
                                                                  'totalMinutesByMe', 'isAdmin'));
})->add($redirectToLoginMW)->setName('confirmRemoveMember');

// Process remove member from a project
$app->post('/project/{name}/remove/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $username = trim($args['username']);
    $user = User::findById($this->db, $this->session->userID);
    $action = $request->getParam('action');

    if ($action === 'no' || $action !== 'yes') {
        if ($action !== 'no') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
    }

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

    // Remove user from project database
    $projectMember->delete();

    // Add notification for removed user 
    $notification = new Notification($this->db);
    $notification->date = date('Y-m-d');
    $notification->userID = $projectMember->userID;
    $notification->notification = 'You were removed from project ';
    $notification->notification .= "<a href=\"{$router->pathFor('fetchProjectLogs', compact('name'))}\">{$name}</a> ";
    $notification->notification .= "by {$user->username}";
    $notification->save();

    $this->flash->addMessage('success', "{$projectMember->username} has been removed");

    return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
})->add($redirectToLoginMW)->setName('processRemoveMember');

// Process toggle admin status of a user
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

            $this->flash->addMessage('success', "{$username} is now an admin");
        } else {
            $this->flash->addMessage('fail', "{$username} is already an admin");
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
            $this->flash->addMessage('success', "{$username} is no longer an admin");
        } else {
            $this->flash->addMessage('fail', "{$username} is already a regular member");
        }
    } else {
        $this->flash->addMessage('fail', 'There was an error processing this request');
    }

    return $response->withRedirect($router->pathFor('fetchProjectMembers', compact('name')));
})->add($redirectToLoginMW)->setName('processToggleAdmin');

// Add new log page
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
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);

    // Fetch post data
    $postData = $this->session->getPostData();

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectMember', 'page',
                                                                  'totalMinutes', 'totalMinutesByMe', 'postData',
                                                                  'isAdmin'));
})->add($redirectToLoginMW)->setName('fetchAddNewLog');

// Process add new log form
$app->post('/project/{name}/newLog', function ($request, $response, $args) {
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
    $action = trim(strtolower($request->getParam('action')));
    $inputError = false;

    if ($action === 'cancel' || $action !== 'save') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('project', compact('name')));
    }

    if (!$date = ProjectLog::formatDateToSQL($date)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Invalid date entered');
    }

    if (!ProjectLog::isValidTime($hours, $minutes)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Time must be between 1 minute to 24 hours');
    }

    if ($inputError) {
        $this->session->setPostData($_POST);

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

    return $response->withRedirect($router->pathFor('fetchAddNewLog', compact('name')));
})->add($redirectToLoginMW)->setName('addProjectLog');

// Edit existing log page
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

    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

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

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectLog', 'page',
                                                                  'projectMember', 'totalMinutes', 'totalMinutesByMe',
                                                                  'postData', 'isAdmin'));
})->add($redirectToLoginMW)->setName('fetchEditLog');

// Process delete log entry form
$app->post('/project{name}/deleteLog/{logID}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $logID = (int) ($args['logID']);
    $action = $request->getParam('action');
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'cancel' || $action !== 'delete') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

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

    // Delete log in database
    $projectLog->delete();

    $this->flash->addMessage('success', 'Log entry successfully deleted');

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('deleteLog');

// Process edit log entry form
$app->post('/project/{name}/edit/{logID}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $logID = (int) ($args['logID']);
    $action = $request->getParam('action');
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'cancel' || $action !== 'save') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was a problem processing your request');
        }

        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

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

    // Update log in database
    $projectLog->date = $date;
    $projectLog->minutes = ProjectLog::calculateTotalMinutes($hours, $minutes);
    $projectLog->comment = $comment;
    $projectLog->save();
    $this->flash->addMessage('success', 'Log entry successfully updated');

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('editLog');

// Process request to join a project form
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

    $this->flash->addMessage('success', 'Request to join project has been sent');

    if (isset($_SERVER['HTTP_REFERER'])) {
        return $response->withRedirect($_SERVER['HTTP_REFERER']);
    } else {
        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }
})->add($redirectToLoginMW)->setName('requestJoin');

// Process cancellation of a request to join a project
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

    if (isset($_SERVER['HTTP_REFERER'])) {
        return $response->withRedirect($_SERVER['HTTP_REFERER']);
    } else {
        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }
})->add($redirectToLoginMW)->setName('cancelRequestJoin');

// Project actions page
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

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);
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

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'isOwner', 'page', 'projectMember',
                                                                  'isAdmin', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('projectActions');

// Leave project page
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

    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);
    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

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

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMember',
                                                                  'totalMinutes', 'totalMinutesByMe', 'isAdmin'));
})->add($redirectToLoginMW)->setName('confirmLeaveProject');

// Process leave project form
$app->post('/project/{name}/leave/{username}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $username = trim($args['username']);
    $action = trim(strtolower($request->getParam('action')));
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'no' || $action !== 'yes') {
        if ($action !== 'no') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
    }

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

    // Remove project member from database
    $projectMember->delete();

    $this->flash->addMessage('success', 'You have left this project');

    return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
})->add($redirectToLoginMW)->setName('processLeaveProject');

// Delete project page
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
    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

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

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMember',
                                                                  'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('confirmDeleteProject');

$app->post('/project/{name}/delete', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $action = trim(strtolower($request->getParam('action')));
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'no' || $action !== 'yes') {
        if ($action !== 'no') {
            $this->flash->addMessage('fail', ' There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
    }

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

    return $response->withRedirect($router->pathFor('projects'));
})->add($redirectToLoginMW)->setName('processDeleteProject');

// Rename project page
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
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);

    // Get form session data if available
    $postData = $this->session->getPostData();

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectMember', 'page',
                                                                  'projectMember', 'totalMinutes', 'totalMinutesByMe',
                                                                  'postData', 'isAdmin'));
})->add($redirectToLoginMW)->setName('renameProject');

// Process renaming a project
$app->post('/project/{name}/rename', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $newName = trim($request->getParam('projectName'));
    $action = trim($request->getParam('action'));
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'cancel' || $action !== 'rename') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
    }

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

    $inputError = false;
    if (!Project::isValidProjectName($newName)) {
        $this->flash->addMessage('fail', 'New project name was entered in an invalid format');
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('renameProject', compact('name')));
    }

    // Check if new name is the same as the existing name
    if ($name === $newName) {
        $this->flash->addMessage('fail', 'New project name is the same as the existing one');
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('renameProject', compact('name')));
    }

    // Check if project already exists
    if (Project::doesProjectExist($this->db, $newName) && strtolower($name) !== strtolower($newName)) {
        $this->flash->addMessage('fail', "Project {$newName} already exists");
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('renameProject', compact('name')));
    }

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

    return $response->withRedirect($router->pathFor('projectActions', ['name' => $newName]));
})->add($redirectToLoginMW)->setName('processRenameProject');

// Transfer project ownership page
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

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Fetch project members
    $projectMembers = ProjectMember::findProjectMembersByProjectName($this->db, $name);
    $projectMember = true;

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'page', 'projectMembers',
                                                                  'projectMember', 'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('transferOwnership');

// Transfer project ownership confirmation page
$app->post('/project/{name}/transferOwnership/confirm', function ($request, $response, $args) {
    $router = $this->router;

    $page = 'confirmTransferOwnership';
    $name = trim($args['name']);
    $newOwner = trim($request->getParam('newOwner'));
    $action = trim(strtolower($request->getParam('action')));
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'cancel' || $action !== 'transfer') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('projectActions', compact('name')));
    }

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
        $this->flash->addMessage('fail', 'Invalid project member was selected');

        return $response->withRedirect($router->pathFor('transferOwnership', compact('name')));
    }

    $projectMember = true;
    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'page', 'project', 'owner', 'projectMember',
                                                                  'totalMinutes', 'totalMinutesByMe'));
})->add($redirectToLoginMW)->setName('confirmTransferOwnership');

// Process confirm transfer ownership
$app->post('/project/{name}/transferOwnership/{newOwner}', function ($request, $response, $args) {
    $router = $this->router;

    $name = trim($args['name']);
    $newOwner = trim($args['newOwner']);
    $action = trim(strtolower($request->getParam('action')));
    $user = User::findById($this->db, $this->session->userID);

    if ($action === 'no' || $action !== 'yes') {
        if ($action !== 'no') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('transferOwnership', compact('name')));
    }

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
        $this->flash->addMessage('fail', 'There was an error fetching the project member');

        return $response->withRedirect($router->pathFor('transferOwnership', compact('name')));
    }

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
})->add($redirectToLoginMW)->setName('processTransferOwnership');

// Add user to project page
$app->get('/project/{name}/addUser', function($request, $response, $args) {
    $router = $this->router;

    $page = 'addUser';
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
    $isAdmin = ProjectMember::isProjectAdmin($this->db, $name, $user->id);

    // Get form session data if available
    $postData = $this->session->getPostData();

    if(isset($postData["prevPage"])) {
        $prevPage = $postData["prevPage"];
    } else {
        $prevPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    }

    $project->dateAdded = ProjectLog::formatDateFromSQL($project->dateAdded);

    // Get combined minutes of all users for this project
    $totalMinutes = ProjectLog::getTotalTimeByProjectName($this->db, $name);
    $totalMinutes = ProjectLog::formatTimeOutput($totalMinutes);

    // Get combined minutes of me for this project
    $totalMinutesByMe = ProjectLog::getTotalTimeByProjectNameAndUser($this->db, $name, $user->id);
    $totalMinutesByMe = ProjectLog::formatTimeOutput($totalMinutesByMe);

    return $this->view->render($response, 'project.twig', compact('user', 'project', 'projectMember', 'page',
                                                                  'projectMember', 'totalMinutes', 'totalMinutesByMe',
                                                                  'postData', 'isAdmin', 'prevPage'));
})->add($redirectToLoginMW)->setName('addUserToProject');

// Process adding user to project
$app->post('/project/{name}/addUser', function($request, $response, $args) {
    $router = $this->router;

    $user = User::findById($this->db, $this->session->userID);
    $name = trim($args['name']);
    $username = $this->request->getParam('username');
    $action = $this->request->getParam('action');
    $prevPage = $this->request->getParam('prevPage');

    if($action === 'cancel' || $action !== 'add') {
        if($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }
        if(!empty($prevPage)) {
            return $response->withRedirect($prevPage);
        } else {
            return $response->withRedirect($router->pathFor('project', compact('name')));
        }
    }

    // Fetch project
    if (!$project = Project::findProjectByName($this->db, $name)) {
        $this->flash->addMessage('fail', 'Project does not exist');
        return $response->withRedirect($router->pathFor('projects'));
    }

    // Check if user has permission
    if (!ProjectMember::isProjectAdmin($this->db, $name, $user->id) || !$project->ownerID === $user->id) {
        $this->flash->addMessage('fail', 'You do not have permission to view this page');
        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }

    $inputError = false;
    
    if (!$newMember = User::fetchUser($this->db, $username)) {
        $inputError = true;
        $this->flash->addMessage('fail', "Could not find user {$username}");
    } elseif ($user->username === $username) {
        $inputError = true;
        $this->flash->addMessage('fail', 'You cannot add yourself');
    } elseif (ProjectMember::findProjectMemberByProjectNameAndUsername($this->db, $name, $username)) {
        $inputError = true;
        $this->flash->addMessage('fail', "{$username} is already a member of this project");
    }

    if($inputError) {
        $this->session->setPostData($_POST);
        return $response->withRedirect($router->pathFor('addUserToProject', compact('name')));
    }

    // Add user to project
    $projectMember = new ProjectMember($this->db);
    $projectMember->userID = $newMember->id;
    $projectMember->projectID = $project->id;
    $projectMember->isAdmin = false;
    $projectMember->save();

    // Send notification to new member
    $message = 'You have been added to project ';
    $message .= '<a href="' . $router->pathFor('project', compact('name')) . '">' . $name . '</a> ';
    $message .= 'by ' . $user->username;

    $notification = new Notification($this->db);
    $notification->userID = $newMember->id;
    $notification->date = date('Y-m-d');
    $notification->notification =  $message;
    $notification->save();

    
    $this->flash->addMessage('success', "You have added {$username} to this project");
    if(!empty($prevPage)) {
        return $response->withRedirect($prevPage);
    } else {
        return $response->withRedirect($router->pathFor('fetchProjectLogs', compact('name')));
    }
})->add($redirectToLoginMW)->setName('processAddUserToProject');

/*
 * Profile Routes
 */

// User profile page
$app->get('/profile/{username}', function ($request, $response, $args) {
    $user = User::findById($this->db, $this->session->userID);
    $username = trim(strtolower($args['username']));
    $prevPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    $profileNotFound = false;

    // Fetch profile
    if ($profile = Profile::getProfileByUsername($this->db, $username)) {
        $profile->joinDate = ProjectLog::formatDateFromSQL($profile->joinDate);

        // Fetch projects for the user
        $projects = Project::findProjectsByUser($this->db, $profile->userID);
    }

    return $this->view->render($response, 'profile.twig', compact('user', 'profile', 'projects', 'prevPage'));
})->add($redirectToLoginMW)->setName('userProfile');

/*
 * Account Routes
 */

// Account menu page
$app->get('/account', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    return $this->view->render($response, 'account.twig', compact('user'));
})->add($redirectToLoginMW)->setName('account');

// Update profile page
$app->get('/account/profile', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);
    $maxPhotoSize = Profile::getMaxPhotoAllowedFileSizeInBytes();
    $maxPhotoSizeString = Profile::getMaxPhotoAllowedFileSizeString();
    $postData = $this->session->getPostData();

    // Fetch profile if exists
    $profile = Profile::getProfileByUserID($this->db, $user->id);

    return $this->view->render($response, 'modifyProfile.twig', compact('user', 'maxPhotoSize', 'profile', 'postData',
                                                                        'maxPhotoSizeString'));
})->add($redirectToLoginMW)->setName('modifyProfile');

// Process update profile form
$app->post('/account/profile/', function ($request, $response) {
    $router = $this->router;
    $user = User::findById($this->db, $this->session->userID);

    $action = trim(strtolower($this->request->getParam('action')));
    $name = trim(ucwords($this->request->getParam('name')));
    $otherInfo = trim($this->request->getParam('otherInfo'));
    $removePhoto = $this->request->getParam('removePhoto');
    $photo = $request->getUploadedFiles()['photo'];

    if ($action === 'cancel' || $action !== 'save') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('account'));
    }

    // Validate form data
    if (!Profile::isValidName($name)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Name field must be '.Profile::NAME_MAX_LENGTH.' characters or less');
    }
    if (!Profile::isValidOtherInfo($otherInfo)) {
        $inputError = true;
        $this->flash->addMessage('fail', 'Other info field must be '.Profile::OTHERINFO_MAX_LENGTH.' characters or less');
    }
    if ($photo->getError() !== 0 && $photo->getError() !== 4) {
        $inputError = true;
        if($photo->getError() === 1 || $photo->getError() === 3) {
            $this->flash->addMessage('fail', 'Image file is too large');
        } else {
            $this->flash->addMessage('fail', 'There was a problem processing the uploaded file');
        }
    }
    if ($photo->getError() === 0) {
        // Check if uploaded file is a valid photo
        if (!Profile::isValidImageFile($photo)) {
            $inputError = true;
            $this->flash->addMessage('fail', 'Unable to determine image type of the uploaded file');
        } elseif (!Profile::isValidImageFormat($photo)) {
            $inputError = true;
            $this->flash->addMessage('fail', 'Image is not a JPG, GIF, or PNG file');
        }
    }

    if ($inputError) {
        // Store form data in session variable
        $this->session->setPostData($_POST);

        return $response->withRedirect($router->pathFor('modifyProfile'));
    }

    // Fetch profile if it exists otherwise create a new profile
    if ($profile = Profile::getProfileByUserID($this->db, $user->id)) {
        $newProfile = false;
    } else {
        $profile = new Profile($this->db);
        $newProfile = true;
    }

    $profile->name = (!empty($name)) ? $name : null;
    $profile->otherInfo = (!empty($otherInfo)) ? $otherInfo : null;

    if ($removePhoto === 'removePhoto') {
        // Remove old photo
        if (!$newProfile) {
            $photoFile = $profile->photoPath.'/'.$profile->photoName;
            if (file_exists($photoFile)) {
                unlink($photoFile);
                $profile->photoName = null;
                $profile->photoPath = null;
            }
        }
    } elseif ($photo->getError() === 0) {
        $uploadFileName = $photo->getClientFilename();
        $uploadMediaType = $photo->getClientMediaType();

        // Create directory if not exists
        $uploadDirectory = 'uploads/';
        $uploadDirectory .= $user->username[0].'/';
        if (isset($user->username[1])) {
            $uploadDirectory .= $user->username[1].'/';
        }
        $uploadDirectory .= $user->username;
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        $photo->moveTo("{$uploadDirectory}/{$uploadFileName}");

        // Resize photo
        Profile::resizePhoto("{$uploadDirectory}/{$uploadFileName}");

        // Remove old photo if exists
        $photoFile = $profile->photoPath.'/'.$profile->photoName;
        if (file_exists($photoFile)) {
            unlink($photoFile);
        }

        $profile->photoName = $uploadFileName;
        $profile->photoPath = $uploadDirectory;
    }

    $profile->save();

    $this->flash->addMessage('success', 'Profile has been successfully updated');

    return $response->withRedirect($router->pathFor('account'));

})->add($redirectToLoginMW)->setName('processModifyProfile');

// Change password page
$app->get('/account/password', function ($request, $response) {
    $user = User::findById($this->db, $this->session->userID);

    return $this->view->render($response, 'changePassword.twig', compact('user'));
})->add($redirectToLoginMW)->setName('changePassword');

// Process change password form
$app->post('/account/password', function ($request, $response) {
    $router = $this->router;
    $user = User::findById($this->db, $this->session->userID);

    $password = $this->request->getParam('currentPassword');
    $newPassword = $this->request->getParam('newPassword');
    $newPassword2 = $this->request->getParam('newPassword2');
    $action = trim(strtolower($this->request->getParam('action')));

    if ($action === 'cancel' || $action !== 'save') {
        if ($action !== 'cancel') {
            $this->flash->addMessage('fail', 'There was an error processing your request');
        }

        return $response->withRedirect($router->pathFor('account'));
    }

    // Check if current password is correct
    if (!password_verify($password, $user->password)) {
        $this->flash->addMessage('fail', 'Current password was entered incorrectly');

        return $response->withRedirect($router->pathFor('changePassword'));
    }

    // Check if new password is valid
    if (!User::isValidPassword($newPassword)) {
        $this->flash->addMessage('fail', 'Invalid formatted password');

        return $response->withRedirect($router->pathFor('changePassword'));
    }

    // Check if new passwords match
    if (!User::doPasswordsMatch($newPassword, $newPassword2)) {
        $this->flash->addMessage('fail', 'Passwords do not match');

        return $response->withRedirect($router->pathFor('changePassword'));
    }

    $user->password = User::encryptPassword($newPassword);
    $user->save();

    $this->flash->addMessage('success', 'Password has been changed successfully');

    return $response->withRedirect($router->pathFor('account'));
})->add($redirectToLoginMW)->setName('processChangePassword');
