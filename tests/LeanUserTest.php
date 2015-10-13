<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanUser;

class LeanUserTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
        LeanClient::useRegion(getenv("LC_API_REGION"));
    }

    public function testSetGetFields() {
        $user = new LeanUser();
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
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $this->setExpectedException("LeanCloud\LeanException",
                                    "Cannot save new user, please signUp first.");
        $user->save();
    }

    public function testUserSignUp() {
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");

        $user->signUp();
        $this->assertNotEmpty($user->getObjectId());
        $this->assertNotEmpty($user->getSessionToken());

        $user->destroy();
    }

    public function testUserUpdate() {
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $user->signUp();

        $user->setEmail("alice@example.com");
        $user->set("age", 24);
        $user->save();
        $this->assertNotEmpty($user->getUpdatedAt());

        $user2 = LeanUser::become($user->getSessionToken());
        $this->assertEquals("alice@example.com", $user2->getEmail());
        $this->assertEquals(24, $user2->get("age"));

        $user->destroy();
    }

    public function testUserLogIn() {
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $user->signUp();
        $this->assertNotEmpty($user->getSessionToken());

        $user2 = LeanUser::logIn("alice", "blabla");
        $this->assertEquals($user->getObjectId(), $user2->getObjectId());
        $this->assertEquals($user2, LeanUser::getCurrentUser());

        $user->destroy();
    }

    public function testBecome() {
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $user->signUp();
        $this->assertNotEmpty($user->getSessionToken());

        $user2 = LeanUser::become($user->getSessionToken());
        $this->assertEquals($user->getObjectId(), $user2->getObjectId());
        $this->assertEquals($user2, LeanUser::getCurrentUser());

        $user->destroy();
    }

    public function testLogOut() {
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $user->signUp();
        $this->assertNotEmpty($user->getSessionToken());

        $user2 = LeanUser::logIn("alice", "blabla");
        $this->assertEquals($user2, LeanUser::getCurrentUser());
        LeanUser::logOut();
        $this->assertNull(LeanUser::getCurrentUser());

        // re-login so we can delete user
        LeanUser::become($user->getSessionToken());
        $user->destroy();
    }

    public function testUpdatePassword() {
        $user = new LeanUser();
        $user->setUsername("alice");
        $user->setPassword("blabla");
        $user->signUp();
        $this->assertNotEmpty($user->getObjectId());
        $this->assertNotEmpty($user->getSessionToken());
        $id = $user->getObjectId();
        $user->updatePassword("blabla", "yadayada");
        $user = LeanUser::logIn("alice", "yadayada");
        $this->assertEquals($id, $user->getObjectId());

        $user->destroy();
    }

    public function testVerifyMobilePhone() {
        // Ensure the post format is correct
        $this->setExpectedException("LeanCloud\LeanException", null, 603);
        LeanUser::verifyMobilePhone("000000");
    }

}

?>