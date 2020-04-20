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
 * is an instance of Relation, where users can be added or
 * removed.
 *
 * Roles can belong to role as well, which can be got by
 * `$role->getRoles()`, where roles can be added or removed.
 *
 * @see ACL, Relation
 */
class Role extends LeanObject {
    /**
     * Table name on LeanCloud
     * @var string
     */
    protected static $className = "_Role";

    /**
     * Set name of role
     *
     * The name can contain only alphanumeric characters, _, -, and
     * space. It cannot be changed after being saved.
     *
     * @return Role
     */
    public function setName($name) {
        $this->set("name", $name);
        return $this;
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
     * @return Relation
     */
    public function getUsers() {
        return $this->getRelation("users");
    }

    /**
     * Get a relation of roles that belongs to this role
     *
     * @return Relation
     */
    public function getRoles() {
        return $this->getRelation("roles");
    }
}

