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
        $acl = new LeanACL();
        $acl->setPublicWriteAccess(true); // so it can be destroyed
        $role = new LeanRole("guest", $acl);
        $role->save();
        $this->assertNotEmpty($role->getObjectId());

        $childrenUsers = $role->getUsers();
        $this->assertTrue($childrenUsers instanceof LeanRelation);
        $childrenRoles = $role->getRoles();
        $this->assertTrue($childrenRoles instanceof LeanRelation);

        $role->destroy();
    }

}

