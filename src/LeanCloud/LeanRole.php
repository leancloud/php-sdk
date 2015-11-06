<?php
namespace LeanCloud;

/**
 * Role representation on LeanCloud
 *
 * A role represents a group of users in ACL, where a role can be
 * assigned read/write permissions. If a role was granted write
 * permission, then users belongs to this role will all inherit the
 * write permission.
 *
 * All users of a role could be queried by `$role->getUsers()`, which
 * is an instance of LeanRelation, where users can be added or
 * removed.
 *
 * Roles can belong to role as well, which can be got by
 * `$role->getRoles()`, where roles can be added or removed.
 *
 * @see LeanACL, LeanRelation
 */
class LeanRole extends LeanObject {
    /**
     * Table name on LeanCloud
     * @var string
     */
    protected static $className = "_Role";

    /**
     * Initialize a role
     *
     * The name can contain only alphanumeric characters, _, -, and
     * space. It cannot be changed after being saved.
     *
     * @param string  $name The name of role
     * @param LeanACL $acl  The ACL specifies who can change **this role**
     */
    public function __construct($name, $acl) {
        parent::__construct();
        $this->set("name", $name);
        $this->setACL($acl);
    }

    /**
     * Get name of role
     *
     * @return string
     */
    public function getName() {
        return $this->get("name");
    }

    /**
     * Get a relation of users that belongs to this role
     *
     * @return LeanRelation
     */
    public function getUsers() {
        return $this->getRelation("users");
    }

    /**
     * Get a relation of roles that belongs to this role
     *
     * @return LeanRelation
     */
    public function getRoles() {
        return $this->getRelation("roles");
    }
}

