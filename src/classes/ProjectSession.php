<?php

class ProjectSession extends Session
{
    private $postData;

    const PROJECT_TITLE = 'time';

    public function __construct()
    {
        parent::__construct();

        if (isset($_SESSION[self::PROJECT_TITLE]['postData'])) {
            $this->postData = $_SESSION[self::PROJECT_TITLE]['postData'];
        }
    }

    public function setPostData($postData)
    {
        $this->postData = $_SESSION[self::PROJECT_TITLE]['postData'] = $postData;
    }

    public function getPostData()
    {
        $postData = $this->postData;

        // Unset session post data
        unset($_SESSION[self::PROJECT_TITLE]['postData']);
        unset($this->postData);

        return $postData;
    }
}
