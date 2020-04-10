<?php

use LeanCloud\Client;
use LeanCloud\User;
use LeanCloud\Role;
use LeanCloud\ACL;
use LeanCloud\File;
use LeanCloud\Query;
use LeanCloud\CloudException;
use LeanCloud\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

        Client::setStorage(new SessionStorage());

        // Try to make a default user so we can login
        $user = new User();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $user->setEmail("alice@example.com");
        try {
            $user->signUp();
        } catch (CloudException $ex) {
            // skip
        }
    }

    public static function tearDownAfterClass() {
        // destroy default user if present
        try {
            $user = User::logIn("alice", "blabla");
            $user->destroy();
        } catch (CloudException $ex) {
            // skip
        }
    }

    public function setUp() {
        // logout current user if any
        User::logOut();
        $this->openToken = array();
        $this->openToken["openid"]       = "0395BA18A";
        $this->openToken["expires_in"]   = "36000";
        $this->openToken["access_token"] = "QaQF4C0j5Th5ed331b56ddMwm8WC";
    }

    public function testSetGetFields() {
        $user = new User();
        $user->setUsername("alice");
        $user->setEmail("alice@example.com");
        $user->setMobilePhoneNumber("18612340000");

        $this->assertEquals("alice",             $user->getUsername());
        $this->assertEquals("alice@example.com", $user->getEmail());
        $this->assertEquals("18612340000",       $user->getMobilePhoneNumber());

        // additional fields could be set too
        $user->set("age", 24);
        $this->assertEquals(24, $user->get("age"));
    }

    public function testSaveNewUser() {
        $user = new User();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $this->setExpectedException("LeanCloud\CloudException",
                                    "Cannot save new user, please signUp first.");
        $user->save();
    }

    public function testUserSignUp() {
        $user = new User();
        $user->setUsername("alice2");
        $user->setPassword("blabla");

        $user->signUp();
        $this->assertNotEmpty($user->getObjectId());
        $this->assertNotEmpty($user->getSessionToken());

        $user->destroy();
    }

    public function testUserUpdate() {
        $user = User::logIn("alice", "blabla");

        $user->setEmail("alice@example.com");
        $user->set("age", 24);
        $user->save();
        $this->assertNotEmpty($user->getUpdatedAt());

        $user2 = User::become($user->getSessionToken());
        $this->assertEquals("alice@example.com", $user2->getEmail());
        $this->assertEquals(24, $user2->get("age"));
    }

    public function testUserLogIn() {
        $user = User::logIn("alice", "blabla");

        $this->assertNotEmpty($user->getObjectId());
        $this->assertEquals($user, User::getCurrentUser());
    }

    public function testUserLogInWithEmail() {
        $user = User::logInWithEmail("alice@example.com", "blabla");

        $this->assertNotEmpty($user->getObjectId());
        $this->assertEquals($user, User::getCurrentUser());
    }

    public function testLoginWithMobilePhoneNumber() {
        $user = User::logIn("alice", "blabla");
        $user->setMobilePhoneNumber("18612340000");
        $user->save();
        $user->logOut();
        $this->assertNull(User::getCurrentUser());

        User::logInWithMobilePhoneNumber("18612340000", "blabla");
        $user2 = User::getCurrentUser();
        $this->assertEquals("alice", $user2->getUsername());
    }

    public function testBecome() {
        $user = User::logIn("alice", "blabla");

        $user2 = User::become($user->getSessionToken());
        $this->assertNotEmpty($user2->getObjectId());
        $this->assertEquals($user2, User::getCurrentUser());
    }

    public function testRefreshSessionToken() {
        $user = new User();
        $user->setUsername("alice4");
        $user->setPassword("blabla");
        $user->signUp();

        $token = $user->getSessionToken();
        $user->refreshSessionToken();
        $this->assertNotEmpty($user->getSessionToken());
        $this->assertNotEquals($token, $user->getSessionToken());
        $user->destroy();
    }

    public function testLogOut() {
        $user = User::logIn("alice", "blabla");
        $this->assertEquals($user, User::getCurrentUser());
        User::logOut();
        $this->assertNull(User::getCurrentUser());
    }

    public function testUpdatePassword() {
        $user = new User();
        $user->setUsername("alice3");
        $user->setPassword("blabla");
        $user->signUp();
        $this->assertNotEmpty($user->getObjectId());
        $this->assertNotEmpty($user->getSessionToken());
        $id = $user->getObjectId();
        $token = $user->getSessionToken();
        $user->updatePassword("blabla", "yadayada");
        // session token should be refreshed
        $this->assertNotEquals($token, $user->getSessionToken());

        $user->destroy();
    }

    public function testVerifyMobilePhone() {
        // Ensure the post format is correct
        $this->setExpectedException("LeanCloud\CloudException", null, 603);
        User::verifyMobilePhone("000000");
    }

    public function testSignUpOrLoginByMobilePhone() {
        // Ensure the post format is correct
        $this->setExpectedException("LeanCloud\CloudException", null, 603);
        User::signUpOrLoginByMobilePhone("18612340000", "000000");
    }

    public function testLogInWithLinkedService() {
        $user = User::logIn("alice", "blabla");

        $user->linkWith("weixin", $this->openToken);
        $auth = $user->get("authData");
        $this->assertEquals($this->openToken, $auth["weixin"]);

        $user2 = User::logInWith("weixin", $this->openToken);
        $this->assertEquals($user->getUsername(),
                            $user2->getUsername());
        $this->assertEquals($user->getSessionToken(),
                            $user2->getSessionToken());

        $user2->unlinkWith("weixin");
    }

    public function testSignUpWithLinkedService() {
        $user = User::logInWith("weixin", $this->openToken);
        $this->assertNotEmpty($user->getSessionToken());
        $this->assertNotEmpty($user->getObjectId());
        $this->assertEquals($user, User::getCurrentUser());

        $user->destroy();
    }

    public function testUnlinkService() {
        $user = User::logInWith("weixin", $this->openToken);
        $token = $user->getSessionToken();
        $authData = $user->get("authData");
        $this->assertEquals($this->openToken, $authData["weixin"]);
        $user->unlinkWith("weixin");

        // re-login with user session token
        $user2    = User::become($token);
        $authData = $user2->get("authData");
        $this->assertTrue(!isset($authData["weixin"]));

        $user2->destroy();
    }

    public function testGetRoles() {
        $user = new User();
        $user->setUsername("alice3");
        $user->setPassword("blabla");
        $user->signUp();

        $role = new Role();
        $role->setName("test_role");
        $acl = new ACL();
        $acl->setPublicWriteAccess(true);
        $acl->setPublicReadAccess(true);

        $role->setACL($acl);
        $rel = $role->getUsers();
        $rel->add($user);
        $role->save();
        $this->assertNotEmpty($role->getObjectId());

        $roles = $user->getRoles();
        $this->assertEquals("test_role", $roles[0]->getName());

        $user->destroy();
        $role->destroy();
    }

    public function testIsAuthenticated() {
        $user = User::logIn("alice", "blabla");
        $this->assertTrue($user->isAuthenticated());

        $user->mergeAfterFetch(array("sessionToken" => "invalid-token"));
        $this->assertFalse($user->isAuthenticated());

        $user = new User();
        $this->assertFalse($user->isAuthenticated());
    }

    /*
     * Get current user with file attribute shall not
     * circularly invoke getCurrentUser.
     *
     * @link github.com/leancloud/php-sdk#48
     */
    public function testCircularGetCurrentUser() {
        // ensure getCurrentUser neither run indefinetely, nor throw maximum
        // function call error
        $avatar = File::createWithUrl("alice.png", "https://leancloud.cn/favicon.png");
        $user = User::logIn("alice", "blabla");
        $user->set("avatar", $avatar);
        $user->save();
        $token = User::getCurrentSessionToken();
        $user->logOut();
        User::setCurrentSessionToken($token);

        $user2 = User::getCurrentUser();
        $this->assertEquals($user2->getUsername(), "alice");
    }

    /*
     * To test this case, it is necessary to set "find" permission
     * to be session user, i.e. allow current logged in user to query only.
     *
     * @link https://github.com/leancloud/php-sdk/issues/62
     */
    public function testFindUserWithSession() {
        $user = User::logIn("alice", "blabla");
        $query = new Query("_User");
        // it should not raise: 1 Forbidden to find by class permission.
        $query->first();
    }

}

