<?php
// Add middleware
$app->add(function ($request, $response, $next) {
    $this->view->offsetSet('flash', $this->flash);

    return $next($request, $response);
});

$redirectToLoginMW = function ($request, $response, $next) {
    // Check if not from AJAX request
    if (!(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
        // Redirect to login page if not logged in
        if(!$this->session->isLoggedIn()) {
            $router = $this->router;
            return $response->withRedirect($router->pathFor('login'));
        }
    }

    $response = $next($request, $response);
    return $response;
};

$redirectToMainPageMW = function ($request, $response, $next) {
    // redirect to main page if already logged in
    if($this->session->isLoggedIn()) {
        $router = $this->router;
        return $response->withRedirect($router->pathFor('home'));
    }

    $response = $next($request, $response);
    return $response;
};
