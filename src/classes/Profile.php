<?php

class Profile extends DatabaseObject
{
    public $id;
    public $userID;
    public $name;
    public $photoName;
    public $photoPath;
    public $otherInfo;
    public $username;
    public $joinDate;

    const NAME_MAX_LENGTH = 100;
    const OTHERINFO_MAX_LENGTH = 255;
    protected static $tableName = 'profiles';
    protected static $dbFields = array('id', 'userID', 'name', 'photoName', 'photoPath', 'otherInfo');

    /**
     * Returns whether a profile name field is a valid value.
     */
    public static function isValidName($name)
    {
        if (strlen($name) <= self::NAME_MAX_LENGTH) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return whether a profile other info field is valid.
     */
    public static function isValidOtherInfo($otherInfo)
    {
        if (strlen($otherInfo) <= self::OTHERINFO_MAX_LENGTH) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return whether a file is an image file.
     */
    public static function isValidImageFile($photo)
    {
        $photoInfo = getimagesize($photo->file);
        if ($photoInfo === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether an image file is a valid specified format.
     */
    public static function isValidImageFormat($photo)
    {
        $photoInfo = getimagesize($photo->file);
        if ($photoInfo[2] !== IMAGETYPE_GIF && $photoInfo[2] !== IMAGETYPE_JPEG && $photoInfo[2] !== IMAGETYPE_PNG) {
            return false;
        }

        return true;
    }

    /**
     * Resizes a photo.
     */
    public static function resizePhoto($photo)
    {
        $resizedPhoto = new Imagick($photo);
        $resizedPhoto->resizeImage(300, 300, Imagick::FILTER_UNDEFINED, 1, true);
        $resizedPhoto->writeImage($photo);
        $resizedPhoto->destroy();
    }

    /**
     * Get the value of the maximum allowed upload file size in string format.
     */
    public static function getMaxPhotoAllowedFileSizeString()
    {
        $postMaxSize = self::convertPHPSizeToBytes(ini_get('post_max_size'));
        $uploadMaxFileSize = self::convertPHPSizeToBytes(ini_get('upload_max_filesize'));

        return ($postMaxSize < $uploadMaxFileSize) ? ini_get('post_max_size') : ini_get('upload_max_filesize');
    }

    /**
     * Get the value of the maximum allowed upload file size in bytes.
     */
    public static function getMaxPhotoAllowedFileSizeInBytes()
    {
        $postMaxSize = self::convertPHPSizeToBytes(ini_get('post_max_size'));
        $uploadMaxFileSize = self::convertPHPSizeToBytes(ini_get('upload_max_filesize'));

        return ($postMaxSize < $uploadMaxFileSize) ? $postMaxSize : $uploadMaxFileSize;
    }

    /**
     * Convert a PHP formatted file size to its value in bytes.
     */
    private static function convertPHPSizeToBytes($size)
    {
        if (is_numeric($size)) {
            return $size;
        }

        $result = substr($size, 0, -1);

        if (!is_numeric($result)) {
            return false;
        }

        switch (strtoupper(substr($size, -1))) {
            case 'P':
                $result *= 1125899906842782;
                break;
            case 'T':
                $result *= 1099511627775;
                break;
            case 'G':
                $result *= 1073741824;
                break;
            case 'M':
                $result *= 1048576;
                break;
            case 'K':
                $result *= 1024;
                break;
        }

        return $result;
    }

    /**
     * Returns a user profile from a specified user id.
     */
    public static function getProfileByUserID($db, $userID)
    {
        $sql = 'SELECT * FROM profiles WHERE userID = '.(int) $userID.' LIMIT 1';
        $result = self::findBySQL($db, $sql);

        if ($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns a user profile from a specified username.
     */
    public static function getProfileByUsername($db, $username)
    {
        $sql = 'SELECT profiles.id, userID, name, username, photoName, photoPath, otherInfo, joinDate FROM profiles ';
        $sql .= 'INNER JOIN users ON userID = users.id ';
        $sql .= 'WHERE username = ? LIMIT 1';
        $paramArray = array($username);

        $result = self::findBySQL($db, $sql, $paramArray);

        if ($result) {
            return $result[0];
        } else {
            return false;
        }
    }
}
