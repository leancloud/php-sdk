<?php

use LeanCloud\Client;
use LeanCloud\ACL;
use LeanCloud\User;
use LeanCloud\Role;
use PHPUnit\Framework\TestCase;

class ACLTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

    }

    public function testInitializeUserACL() {
        $user = new User(null, "id123");
        $acl  = new ACL($user);
        $out  = $acl->encode();
        $this->assertEquals(true, $out["id123"]["read"]);
        $this->assertEquals(true, $out["id123"]["write"]);
    }

    /**
     * Empty ACL should be encoded as object, instead of array.
     *
     * @link https://github.com/leancloud/php-sdk/issues/84
     */
    public function testEmptyACL() {
        $acl = new ACL();
        $out = $acl->encode();
        $this->assertEquals("{}", json_encode($out));
    }

    public function testSetPublicAccess() {
        $acl = new ACL();
        $acl->setPublicReadAccess(true);
        $out = $acl->encode();
        $this->assertEquals(true, $out[ACL::PUBLIC_KEY]["read"]);
        $this->assertEquals(true, $acl->getPublicReadAccess());

        $acl->setPublicWriteAccess(false);
        $out = $acl->encode();
        $this->assertEquals(false, $out[ACL::PUBLIC_KEY]["write"]);
        $this->assertEquals(false, $acl->getPublicWriteAccess());
    }

    public function testSetUserAccess() {
        $user = new User(null, "id123");
        $acl = new ACL();
        $acl->setReadAccess($user, true);
        $out = $acl->encode();
        $this->assertEquals(true, $out[$user->getObjectId()]["read"]);
        $this->assertEquals(true, $acl->getReadAccess($user));

        $acl->setWriteAccess($user, false);
        $out = $acl->encode();
        $this->assertEquals(false, $out[$user->getObjectId()]["write"]);
        $this->assertEquals(false, $acl->getWriteAccess($user));
    }

    public function testSetRoleAccess() {
        $role = new Role();
        $role->setName("admin");
        $role->setACL(new ACL());
        $acl = new ACL();
        $acl->setRoleReadAccess($role, true);
        $out = $acl->encode();
        $this->assertEquals(true, $out["role:admin"]["read"]);
        $this->assertEquals(true, $acl->getRoleReadAccess($role));

        $acl->setRoleWriteAccess($role, false);
        $out = $acl->encode();
        $this->assertEquals(false, $out["role:admin"]["write"]);
        $this->assertEquals(false, $acl->getRoleWriteAccess($role));
    }

    public function testSetRoleAccessWithRoleName() {
        $acl = new ACL();
        $acl->setRoleReadAccess("admin", true);
        $out = $acl->encode();
        $this->assertEquals(true, $out["role:admin"]["read"]);

        $acl->setRoleWriteAccess("admin", false);
        $out = $acl->encode();
        $this->assertEquals(false, $out["role:admin"]["write"]);
    }
}

