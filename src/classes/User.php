<?php

class User extends DatabaseObject
{
    public $id;
    public $username;
    public $password;
    public $joinDate;
    public $rememberHash;
    public $expires;

    const USERNAME_MIN_LENGTH = 6;
    const USERNAME_MAX_LENGTH = 20;
    const PASSWORD_MIN_LENGTH = 6;
    const PASSWORD_MAX_LENGTH = 160;

    protected static $tableName = 'users';
    protected static $dbFields = array('id', 'username', 'password', 'joinDate', 'rememberHash', 'expires');

    /**
     * Returns whether a specified username is in a valid format
     */
    public static function isValidFormatUsername($username)
    {
        $username = strtolower(trim($username));

        // does username contain only letters and numbers and is between a specified range of characters
        if (preg_match('/^[a-zA-Z0-9]{'.self::USERNAME_MIN_LENGTH.','.self::USERNAME_MAX_LENGTH.'}$/', $username)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns a user from a specified username
     */
    public static function fetchUser($db, $username)
    {
        $sql = 'SELECT * FROM '.self::$tableName.' WHERE username = ? LIMIT 1';
        $paramArray = array($username);
        $result = self::findBySql($db, $sql, $paramArray);

        if ($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns whether a specified password is in a valid format
     */
    public static function isValidPassword($password)
    {
        if (strlen($password) >= self::PASSWORD_MIN_LENGTH && strlen($password) <= self::PASSWORD_MAX_LENGTH) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns a hashed password for a specified password value
     */
    public static function encryptPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Returns whether the two specified password values are identical
     */
    public static function doPasswordsMatch($password1, $password2)
    {
        return $password1 === $password2 ? true : false;
    }
}
