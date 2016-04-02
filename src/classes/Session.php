<?php
class Session
{
    private $loggedIn = false;
    private $previousPage;
    public $adminID;

    /**
     * Sets up session and loads previousPage.
     */
    public function __construct($baseURL = '/')
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['url']['prevPage'])) {
            $this->previousPage = $baseURL;
        } else {
            $this->previousPage = $_SESSION['url']['prevPage'];
        }

        $this->checkLogin();
    }

    /**
     * Login an admin by setting session variable.
     */
    public function login($admin)
    {
        if ($admin) {
            $this->adminID = $_SESSION['url']['adminID'] = $admin->id;
            $this->loggedIn = true;
        }
    }

    /**
     * Logs out an admin by unsetting session variable.
     */
    public function logout()
    {
        $this->loggedIn = false;
        unset($this->adminID);
        unset($_SESSION['url']['adminID']);
    }

    /**
     * Returns whether an admin is logged in.
     */
    public function isLoggedIn()
    {
        return $this->loggedIn;
    }

    /**
     * Set up login data variables.
     */
    private function checkLogin()
    {
        if (isset($_SESSION['url']['adminID'])) {
            $this->loggedIn = true;
            $this->adminID = $_SESSION['url']['adminID'];
        } else {
            $this->loggedIn = false;
            unset($this->adminID);
        }
    }

    /**
     * Updates the previous page.
     */
    public function updatePage($page = 'home')
    {
        $this->previousPage = $_SESSION['url']['prevPage'] = $page;
    }

    /**
     * Fetch the previous page.
     */
    public function getPrevPage()
    {
        return $this->previousPage;
    }
}

?>

