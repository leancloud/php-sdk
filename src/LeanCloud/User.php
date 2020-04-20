<?php
namespace LeanCloud;

use LeanCloud\Client;
use LeanCloud\LeanObject;
use LeanCloud\CloudException;

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
 * ```
 * User::getCurrentSessionToken()
 * ```
 *
 * and current user at
 *
 * ```
 * User::getCurrentUser()
 * ```
 *
 * Providing a token, the user can be conveniently authenticated and
 * fetched by
 *
 * ```
 * User::become($token)
 * ```
 *
 */

class User extends LeanObject {

    /**
     * className on LeanCloud
     *
     * @var string
     */
    protected static $className = "_User";

    /**
     * Current logged-in user
     *
     * @var User
     */
    public static $currentUser = null;

    /**
     * Set username
     *
     * @param string $username
     * @return self
     */
    public function setUsername($username) {
        $this->set("username", $username);
        return $this;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return self
     */
    public function setEmail($email) {
        $this->set("email", $email);
        return $this;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return self
     */
    public function setPassword($password) {
        $this->set("password", $password);
        return $this;
    }

    /**
     * Set mobile phone number
     *
     * @param string $number
     * @return self
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
     * @throws CloudException
     */
    public function signUp() {
        if ($this->getObjectId()) {
            throw new CloudException("User has already signed up.");
        }
        parent::save();
        static::saveCurrentUser($this);
    }

    /**
     * Save a signed-up user
     *
     * @param SaveOption $option
     * @throws CloudException
     */
    public function save($option=null) {
        if ($this->getObjectId()) {
            parent::save($option);
        } else {
            throw new CloudException("Cannot save new user, please signUp ".
                                    "first.");
        }
    }

    /**
     * Update password with old password
     *
     * @param string $old Old password
     * @param string $new New password
     * @throws CloudException
     */
    public function updatePassword($old, $new) {
        if ($this->getObjectId()) {
            $path = "/users/{$this->getObjectId()}/updatePassword";
            $resp = Client::put($path, array("old_password" => $old,
                                                 "new_password" => $new),
                                    $this->getSessionToken());
            $this->mergeAfterFetch($resp);
            static::saveCurrentUser($this);
        } else {
            throw new CloudException("Cannot update password on new user.");
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
     */
    public static function setCurrentSessionToken($token) {
        Client::getStorage()->set("LC_SessionToken", $token);
    }

    /**
     * Get (persisted) session token
     *
     * @return string
     */
    public static function getCurrentSessionToken() {
        return Client::getStorage()->get("LC_SessionToken");
    }

    /**
     * Get currently logged-in user
     *
     * @return User
     */
    public static function getCurrentUser() {
        if (self::$currentUser instanceof User) {
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
     * @param User
     */
    public static function saveCurrentUser($user) {
        self::$currentUser = $user;
        self::setCurrentSessionToken($user->getSessionToken());
    }

    /**
     * Clear logged-in user and session token.
     */
    public static function clearCurrentUser() {
        self::$currentUser = null;
        self::setCurrentSessionToken(null);
    }

    /**
     * Refresh session token
     */
    public function refreshSessionToken() {
        $resp = Client::put("/users/{$this->getObjectId()}/refreshSessionToken",
                            null);
        $this->mergeAfterFetch($resp);
        static::saveCurrentUser($this);
    }

    /**
     * Test if user logged in and session token is valid.
     *
     * @return bool
     */
    public function isAuthenticated() {
        $token = $this->getSessionToken();
        if (!$token) {
            return false;
        }
        try {
            $resp = Client::get("/users/me",
                                array("session_token" => $token));
        } catch(CloudException $ex) {
            if ($ex->getCode() === 211) {
                return false;
            }
            throw ex;
        }
        return true;
    }

    /**
     * Get roles the user belongs to
     *
     * @return array Array of Role
     */
    public function getRoles() {
        if (!$this->getObjectId()) {
            return array();
        }
        $query = new Query("_Role");
        $query->equalTo("users", $this);
        $roles = $query->find();
        return $roles;
    }

    /**
     * Log-in user by session token
     *
     * And set current user.
     *
     * @param string $token Session token
     * @return User
     * @throws CloudException
     */
    public static function become($token) {
        $resp = Client::get("/users/me",
                                array("session_token" => $token));
        $user = new static();
        $user->mergeAfterFetch($resp);
        static::saveCurrentUser($user);

        return $user;
    }


    private static function _login($userData) {
        $resp = Client::post("/login", $userData);
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
     * @return User
     * @throws CloudException
     */
    public static function logIn($username, $password) {
        $user = static::_login(array("username" => $username,
            "password" => $password));
        return $user;
    }
    /**
     * Log-in user by email and password
     *
     * And set current user.
     *
     * @param string $email
     * @param string $password
     * @return User
     * @throws CloudException
     */
    public static function logInWithEmail($email, $password) {
        $user = static::_login(array("email" => $email,
            "password" => $password));
        return $user;
    }


    /**
     * Log-out current user
     */
    public static function logOut() {
        $user = static::getCurrentUser();
        if ($user) {
            try {
                Client::post("/logout", null, $user->getSessionToken());
            } catch (CloudException $exp) {
                // skip
            }
            static::clearCurrentUser($user);
        }
    }

    /**
     * Log-in user by mobile phone and password
     *
     * @param string $phoneNumber
     * @param string $password
     * @return User
     */
    public static function logInWithMobilePhoneNumber($phoneNumber, $password) {
        $params = array("mobilePhoneNumber" => $phoneNumber,
                        "password" => $password);
        $resp = Client::post("/login", $params);
        $user = new static();
        $user->mergeAfterFetch($resp);
        static::saveCurrentUser($user);
        return $user;
    }

    /**
     * Log-in user by mobile phone and SMS code.
     *
     * Log-in user with SMS code, which can be requested by
     * `requestLoginSmsCode`. It will set current user.
     *
     * @param string $phoneNumber Registered mobile phone number
     * @param string $smsCode
     * @return User
     */
    public static function logInWithSmsCode($phoneNumber, $smsCode) {
        $params = array("mobilePhoneNumber" => $phoneNumber,
                        "smsCode" => $smsCode);
        $resp = Client::get("/login", $params);
        $user = new static();
        $user->mergeAfterFetch($resp);
        static::saveCurrentUser($user);
        return $user;
    }

    /**
     * Request login SMS code
     *
     * Send user mobile phone a message with SMS code, which can be used
     * for login then.
     *
     * @param string $phoneNumber Register mobile phone number
     */
    public static function requestLoginSmsCode($phoneNumber) {
        Client::post("/requestLoginSmsCode",
                         array("mobilePhoneNumber" => $phoneNumber));
    }

    /**
     * Request email verify
     *
     * Send user an email to verify email.
     *
     * @param string $email
     */
    public static function requestEmailVerify($email) {
        Client::post("/requestEmailVerify", array("email" => $email));
    }

    /**
     * Request password reset by email
     *
     * @param string $email Registered email
     */
    public static function requestPasswordReset($email) {
        Client::post("/requestPasswordReset", array("email" => $email));
    }

    /**
     * Request password reset by SMS
     *
     * Send user mobile phone a message with SMS code.
     *
     * @param string $phoneNumber Registered mobile phone number
     */
    public static function requestPasswordResetBySmsCode($phoneNumber) {
        Client::post("/requestPasswordResetBySmsCode",
                         array("mobilePhoneNumber" => $phoneNumber));
    }

    /**
     * Reset password by SMS code.
     *
     * @param string $smsCode
     * @param string $newPassword
     */
    public static function resetPasswordBySmsCode($smsCode, $newPassword) {
        Client::put("/resetPasswordBySmsCode/{$smsCode}",
                        array("password" => $newPassword));
    }

    /**
     * Request mobile phone verify.
     *
     * Send user mobile phone a message with SMS code.
     *
     * @param string $phoneNumber
     */
    public static function requestMobilePhoneVerify($phoneNumber) {
        Client::post("/requestMobilePhoneVerify",
                         array("mobilePhoneNumber" => $phoneNumber));
    }

    /**
     * Verify mobile phone by SMS code
     *
     * @param string $smsCode
     */
    public static function verifyMobilePhone($smsCode) {
        Client::post("/verifyMobilePhone/{$smsCode}", null);
    }

    /**
     * Sign up user by mobile phone and SMS code
     *
     * @param string  $phoneNumber
     * @param string  $smsCode
     */
    public static function signUpOrLoginByMobilePhone($phoneNumber, $smsCode) {
        $resp = Client::post("/usersByMobilePhone", array(
            "mobilePhoneNumber" => $phoneNumber,
            "smsCode" => $smsCode
        ));
        $user = new static();
        $user->mergeAfterFetch($resp);
        static::saveCurrentUser($user);
        return $user;
    }

    /*
     * Link and unlink with 3rd party auth provider
     *
     * The auth data we work with has following structure in general:
     *
     *     {"authData": {
     *             "provider-name": {
     *                 "uid":          "...",
     *                 "access_token": "...",
     *                 "expires_in":   "..."
     *             }
     *         }
     *     }
     */

    /**
     * Log-in with 3rd party auth data
     *
     * Log-in with 3rd party provider auth data. If the auth data has been
     * linked previously with user, it will login _as_ that user. Else a
     * new user will be created with generated username. It will set
     * current user.
     *
     * @param string $provider  Provider name
     * @param array  $authToken Auth token
     * @return User
     */
    public static function logInWith($provider, $authToken) {
        $user = new static();
        $user->linkWith($provider, $authToken);
        static::saveCurrentUser($user);
        return $user;
    }

    /**
     * Link user with 3rd party provider
     *
     * @param string $provider  Provider name e.g. "weibo", "weixin"
     * @param array  $authToken Array of id, token, and expiration info
     * @return self
     */
    public function linkWith($provider, $authToken) {
        if (!is_string($provider) || empty($provider)) {
            throw new \InvalidArgumentException("Provider name can only " .
                                                "be string.");
        }
        $data = $this->get("authData");
        if (!$data) {
            $data = array();
        }
        $data[$provider] = $authToken;
        $this->set("authData", $data);
        parent::save();

        return $this;
    }

    /**
     * Unlink user with a provider
     *
     * @param string $provider Provider name
     * @return self
     */
    public function unlinkWith($provider) {
        if (!is_string($provider) || empty($provider)) {
            throw new \InvalidArgumentException("Provider name can only " .
                                                "be string.");
        }
        if (!$this->getObjectId()) {
            throw new \RuntimeException("Cannot unlink with unsaved user.");
        }

        $data = $this->get("authData");
        if (isset($data[$provider])) {
            $data[$provider] = null;
            $this->set("authData", $data);
            $this->save();
        }
        return $this;
    }

}

