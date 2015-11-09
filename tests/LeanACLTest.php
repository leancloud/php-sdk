<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanACL;
use LeanCloud\LeanUser;
use LeanCloud\LeanRole;

class LeanACLTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
        LeanClient::useRegion(getenv("LC_API_REGION"));
    }

    public function testInitializeUserACL() {
        $user = new LeanUser(null, "id123");
        $acl  = new LeanACL($user);
        $out  = $acl->encode();
        $this->assertEquals(true, $out["id123"]["read"]);
        $this->assertEquals(true, $out["id123"]["write"]);
    }

    public function testSetPublicAccess() {
        $acl = new LeanACL();
        $acl->setPublicReadAccess(true);
        $out = $acl->encode();
        $this->assertEquals(true, $out[LeanACL::PUBLIC_KEY]["read"]);
        $this->assertEquals(true, $acl->getPublicReadAccess());

        $acl->setPublicWriteAccess(false);
        $out = $acl->encode();
        $this->assertEquals(false, $out[LeanACL::PUBLIC_KEY]["write"]);
        $this->assertEquals(false, $acl->getPublicWriteAccess());
    }

    public function testSetUserAccess() {
        $user = new LeanUser(null, "id123");
        $acl = new LeanACL();
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
        $role = new LeanRole("admin", new LeanACL());
        $acl = new LeanACL();
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
        $acl = new LeanACL();
        $acl->setRoleReadAccess("admin", true);
        $out = $acl->encode();
        $this->assertEquals(true, $out["role:admin"]["read"]);

        $acl->setRoleWriteAccess("admin", false);
        $out = $acl->encode();
        $this->assertEquals(false, $out["role:admin"]["write"]);
    }
}

