<?php
// Add middleware
$app->add(function ($request, $response, $next) {
    $this->view->offsetSet('flash', $this->flash);

    return $next($request, $response);
});

// Redirect to login page if not logged in
$redirectToLoginMW = function ($request, $response, $next) {
    // Check if not from AJAX request
    if (!(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
        // Redirect to login page if not logged in
        if (!$this->session->isLoggedIn()) {
            $router = $this->router;

            return $response->withRedirect($router->pathFor('login'));
        }
    }

    $response = $next($request, $response);

    return $response;
};

// Redirect to main page if already logged in
$redirectToMainPageMW = function ($request, $response, $next) {
    $router = $this->router;

    // login user if remember me feature is enabled 
    if(!$this->session->isLoggedIn() && isset($_COOKIE['projectLoggerRememberLogin'])) {
        parse_str($_COOKIE['projectLoggerRememberLogin']);

        if(!isset($username) || !isset($hash)) {
            // remove cookie
            setcookie('projectLoggerRememberLogin', '', time() - 3600);
            
            return $response->withRedirect($router->pathFor('login'));
        } else {
            $username = trim($username);
            $hash = hash('sha256',trim($hash));

            // fetch user from database
            $user = User::fetchUser($this->db, $username);

            $expired = (strtotime($user->expires) > time()) ? false : true;

            // if hash exists for and has not expired
            if($user->rememberHash && !$expired) {
                // Check if the hash in the cookie matches the database
                if(hash_equals($user->rememberHash, $hash)) {//$hash === $user->rememberHash) {
                    // generate a new hash and expire
                    $randomString = bin2hex(random_bytes(20)); // generate random string
                    $rememberMeHash = hash(sha256, $randomString);
                    $expires = new DateTime('+1 year'); // expire in 1 year
                    $user->rememberHash = $rememberMeHash;
                    $user->expires = $expires->format('Y-m-d H:i:s'); 
                    $user->save();

                    // update the cookie
                    setcookie("projectLoggerRememberLogin", "username={$username}&hash={$randomString}", strtotime($expires->format('Y-m-d H:i:s')));
                    
                    // login the user
                    $this->session->login($user);
                } else {
                    // Hash value for user is different.  Remove hash from database
                    $user->hash = null;
                    $user->expires = null;
                    $user->save();
                }
            } else {
                $user->rememberHash = null;
                $user->expires = null;
                $user->save();
            }
        }
    }

    // redirect to main page if already logged in
    if ($this->session->isLoggedIn()) {
        return $response->withRedirect($router->pathFor('home'));
    }

    $response = $next($request, $response);

    return $response;
};
