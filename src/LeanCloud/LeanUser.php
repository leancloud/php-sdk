<?php
namespace LeanCloud;

use LeanCloud\LeanClient;
use LeanCloud\LeanObject;
use LeanCloud\LeanException;

/**
 * User representation for LeanCloud User
 *
 * LeanCloud provides a default user model to facilitate user
 * management at application level. Users can be managed by email,
 * mobile phone number, or simply a username.
 *
 * Upon sign-up a session token is issued to user, which shall be used
 * to authenticate the user in subsequent requests. The session token
 * of logged-in user is available at
 *
 *     LeanUser::getCurrentSessionToken()
 *
 * and current user at
 *
 *     LeanUser::getCurrentUser()
 *
 * Providing a token, the user can be conveniently authenticated and
 * fetched by
 *
 *     LeanUser::become($token)
 *
 */

class LeanUser extends LeanObject {

    /**
     * className on LeanCloud
     *
     * @var string
     */
    protected static $className = "_User";

    /**
     * Current logged-in user
     *
     * @var LeanUser
     */
    protected static $currentUser = null;

    /**
     * Set username
     *
     * @param string $username
     * @return LeanUser
     */
    public function setUsername($username) {
        $this->set("username", $username);
        return $this;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return LeanUser
     */
    public function setEmail($email) {
        $this->set("email", $email);
        return $this;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return LeanUser
     */
    public function setPassword($password) {
        $this->set("password", $password);
        return $this;
    }

    /**
     * Set mobile phone number
     *
     * @param string $number
     * @return $this
     */
    public function setMobilePhoneNumber($number) {
        $this->set("mobilePhoneNumber", $number);
        return $this;
    }

    /**
     * Sign-up user
     *
     * It will also auto-login and set current user.
     *
     * @return null
     * @throws LeanException if invalid
     */
    public function signUp() {
        if ($this->getObjectId()) {
            throw new LeanException("User has already signed up.");
        }
        parent::save();
        static::saveCurrentUser($this);
    }

    /**
     * Save a signed-up user
     *
     * @return null
     * @throws LeanException
     */
    public function save() {
        if ($this->getObjectId()) {
            parent::save();
        } else {
            throw new LeanException("Cannot save new user, please signUp ".
                                    "first.");
        }
    }

    /**
     * Update password with old password
     *
     * @param string $old Old password
     * @param string $new New password
     * @return null
     * @throws LeanException
     */
    public function updatePassword($old, $new) {
        if ($this->getObjectId()) {
            $path = "/users/{$this->getObjectId()}/updatePassword";
            $resp = LeanClient::put(path, array("old_password" => $old,
                                                "new_password" => $new));
            $this->mergeAfterFetch($resp);
        } else {
            throw new LeanException("Cannot update password on new user.");
        }
    }

    /**
     * @return string
     */
    public function getUsername() {
        return $this->get("username");
    }

    /**
     * @return string
     */
    public function getEmail() {
        return $this->get("email");
    }

    /**
     * @return string
     */
    public function getMobilePhoneNumber() {
        return $this->get("mobilePhoneNumber");
    }

    /**
     * Get session token of user
     *
     * @return string
     */
    public function getSessionToken() {
        return $this->get("sessionToken");
    }


    // Static methods

    /**
     * Set session token as of logged-in user
     *
     * Save session token after a user logs in. It will clear session token
     * if given token is null.
     *
     * @param string $token Session token of logged-in user
     * @return null
     */
    protected static function setCurrentSessionToken($token) {
        $_SESSION["LC_SessionToken"] = $token;
    }

    /**
     * Get (persisted) session token
     *
     * @return string
     */
    public static function getCurrentSessionToken() {
        if (isset($_SESSION["LC_SessionToken"])) {
            return $_SESSION["LC_SessionToken"];
        }
        return null;
    }

    /**
     * Get currently logged-in user
     *
     * @return LeanUser
     */
    public static function getCurrentUser() {
        if (self::$currentUser instanceof LeanUser) {
            return self::$currentUser;
        }
        $token = static::getCurrentSessionToken();
        if ($token) {
            return static::become($token);
        }
    }

    /**
     * Save logged-in user and session token
     *
     * @param LeanUser
     * @return null
     */
    private static function saveCurrentUser($user) {
        self::$currentUser = $user;
        self::setCurrentSessionToken($user->getSessionToken());
    }

    /**
     * Clear logged-in user and session token.
     *
     * @return null
     */
    private static function clearCurrentUser() {
        self::$currentUser = null;
        self::setCurrentSessionToken(null);
    }

    /**
     * Log-in user by session token
     *
     * And set current user.
     *
     * @param string $token Session token
     * @return LeanUser
     * @throws LeanException
     */
    public static function become($token) {
        $resp = LeanClient::get("/users/me",
                                array("session_token" => $token));
        $user = new static();
        $user->mergeAfterFetch($resp);
        static::saveCurrentUser($user);

        return $user;
    }

    /**
     * Log-in user by username and password
     *
     * And set current user.
     *
     * @param string $username
     * @param string $password
     * @return LeanUser
     * @throws LeanException
     */
    public static function logIn($username, $password) {
        $resp = LeanClient::post("/login", array("username" => $username,
                                                 "password" => $password));
        $user = new static();
        $user->mergeAfterFetch($resp);
        static::saveCurrentUser($user);
        return $user;
    }

    /**
     * Log-out current user
     *
     * @return null
     */
    public static function logOut() {
        $user = static::getCurrentUser();
        if ($user) {
            try {
                LeanClient::post("/logout", null, $user->getSessionToken());
            } catch (LeanException $exp) {
                // skip
            }
            static::clearCurrentUser($user);
        }
    }
}

// register it as sub-class of LeanObject
LeanUser::registerClass();

?>