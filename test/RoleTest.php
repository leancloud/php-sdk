<?php

use LeanCloud\Client;
use LeanCloud\ACL;
use LeanCloud\User;
use LeanCloud\Role;
use LeanCloud\Relation;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

    }

    public function testInitializeRole() {
        // It should not raise error
        $role = new Role();
        $role = new Role("_Role");

        $role = new Role(null, "id123");
        $this->assertEquals("id123", $role->getObjectId());
    }

    public function testGetChildrenAsRelation() {
        $role = new Role();
        $this->assertTrue($role->getUsers() instanceof Relation);
        $this->assertTrue($role->getRoles() instanceof Relation);
    }

    public function testSaveRole() {
        $role = new Role();
        $role->setName("admin");

        $acl = new ACL();
        $acl->setPublicWriteAccess(true); // so it can be destroyed
        $role->setACL($acl);

        $role->save();
        $this->assertNotEmpty($role->getObjectId());
        $this->assertTrue($role->getUsers() instanceof Relation);
        $this->assertTrue($role->getRoles() instanceof Relation);

        $role->destroy();
    }

}

