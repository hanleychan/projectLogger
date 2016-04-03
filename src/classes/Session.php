<?php
class Session
{
    private $loggedIn = false;
    private $previousPage;
    public $userID;
    const PROJECT_TITLE = "time";

    /**
     * Sets up session and loads previousPage.
     */
    public function __construct($baseURL = '/')
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::PROJECT_TITLE]['prevPage'])) {
            $this->previousPage = $baseURL;
        } else {
            $this->previousPage = $_SESSION[self::PROJECT_TITLE]['prevPage'];
        }

        $this->checkLogin();
    }

    /**
     * Login an user by setting session variable.
     */
    public function login($user)
    {
        if ($user) {
            $this->userID = $_SESSION[self::PROJECT_TITLE]['userID'] = $user->id;
            $this->loggedIn = true;
        }
    }

    /**
     * Logs out an user by unsetting session variable.
     */
    public function logout()
    {
        $this->loggedIn = false;
        unset($this->userID);
        unset($_SESSION[self::PROJECT_TITLE]['userID']);
    }

    /**
     * Returns whether an user is logged in.
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
        if (isset($_SESSION[self::PROJECT_TITLE]['userID'])) {
            $this->loggedIn = true;
            $this->userID = $_SESSION[self::PROJECT_TITLE]['userID'];
        } else {
            $this->loggedIn = false;
            unset($this->userID);
        }
    }

    /**
     * Updates the previous page.
     */
    public function updatePage($page = 'home')
    {
        $this->previousPage = $_SESSION[self::PROJECT_TITLE]['prevPage'] = $page;
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

