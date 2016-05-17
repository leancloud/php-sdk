<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanACL;
use LeanCloud\LeanUser;
use LeanCloud\LeanRole;
use LeanCloud\LeanRelation;

class LeanRoleTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
        LeanClient::useRegion(getenv("LC_API_REGION"));
    }

    public function testInitializeRole() {
        // It should not raise error
        $role = new LeanRole();
        $role = new LeanRole("_Role");

        $role = new LeanRole(null, "id123");
        $this->assertEquals("id123", $role->getObjectId());
    }

    public function testGetChildrenAsRelation() {
        $role = new LeanRole();
        $this->assertTrue($role->getUsers() instanceof LeanRelation);
        $this->assertTrue($role->getRoles() instanceof LeanRelation);
    }

    public function testSaveRole() {
        $role = new LeanRole();
        $role->setName("admin");

        $acl = new LeanACL();
        $acl->setPublicWriteAccess(true); // so it can be destroyed
        $role->setACL($acl);

        $role->save();
        $this->assertNotEmpty($role->getObjectId());
        $this->assertTrue($role->getUsers() instanceof LeanRelation);
        $this->assertTrue($role->getRoles() instanceof LeanRelation);

        $role->destroy();
    }

}

