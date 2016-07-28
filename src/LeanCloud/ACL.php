<?php
namespace LeanCloud;

/**
 * Access Control List representation on LeanCloud
 *
 * An ACL is a field that specifies who can or cannot read/write an
 * object. The read/write access can be speified for public, or any
 * users and roles. There can be as many users and roles as possible
 * in an ACL.
 *
 * @see Role
 */
class ACL {
    /**
     * Public access key in ACL field
     */
    const PUBLIC_KEY = "*";

    /**
     * ACL data
     *
     * @var array
     */
    private $data;

    /**
     * Initialize an ACL object
     *
     * It accepts user, or JSON encoded ACL data. In the former, the
     * user will be granted both read and write access. While the
     * latter will be interpretted as JSON encoded array.
     *
     * With empty param, it creates an ACL with no permission granted.
     *
     * @param mixed $val User or JSON encoded ACL array
     */
    public function __construct($val=array()) {
        $this->data = array();

        if ($val instanceof User) {
            $this->setReadAccess($val, true);
            $this->setWriteAccess($val, true);
        } else if (is_array($val)) {
            forEach($val as $id => $attr) {
                if (!is_string($id)) {
                    throw new \RuntimeException("Invalid ACL target");
                }
                if (isset($attr["read"]) || isset($attr["write"])) {
                    $this->data[$id] = $attr;
                } else {
                    throw new \RuntimeException("Invalid ACL access type");
                }
            }
        } else {
            throw new \RuntimeException("Invalid ACL data.");
        }
    }

    /**
     * Set access on a target
     *
     * @param string $target     The target label for user, role, or public
     * @param string $accessType Either read or write
     * @param bool   $flag       Enable or disable the access
     * @throws InvalidArgumentException
     */
    private function setAccess($target, $accessType, $flag) {
        if (empty($target)) {
            throw new \InvalidArgumentException("ACL target cannot be empty");
        }
        if (!in_array($accessType, array("read", "write"))) {
            throw new \InvalidArgumentException("ACL access type must be" .
                                                " either read or write.");
        }

        $access = array();
        if (isset($this->data[$target])) {
            $access = $this->data[$target];
        }
        $access[$accessType] = $flag;
        $this->data[$target] = $access;
    }

    /**
     * Get access flag for target
     *
     * It returns true only if it has been explicitly set to true.
     *
     * @param string $target     Target label for user, role, or public
     * @param string $accessType Either read or write
     * @return bool
     */
    private function getAccess($target, $accessType) {
        if (empty($target)) {
            throw new \InvalidArgumentException("Access target cannot be empty");
        }
        if (!in_array($accessType, array("read", "write"))) {
            throw new \InvalidArgumentException("ACL access type must be" .
                                                " either read or write.");
        }
        if (isset($this->data[$target][$accessType])) {
            return $this->data[$target][$accessType];
        }
        return false;
    }

    /**
     * Get whether public is allowed to read
     *
     * @return bool
     */
    public function getPublicReadAccess() {
        return $this->getAccess(self::PUBLIC_KEY, "read");
    }

    /**
     * Get whether public is allowed to write
     *
     * @return bool
     */
    public function getPublicWriteAccess() {
        return $this->getAccess(self::PUBLIC_KEY, "write");
    }

    /**
     * Set read access for public
     *
     * @param bool $flag Enable or disable public read
     * @return self
     */
    public function setPublicReadAccess($flag) {
        $this->setAccess(self::PUBLIC_KEY, "read", $flag);
        return $this;
    }

    /**
     * Set write access for public
     *
     * @param bool $flag Enable or disable public write
     * @return self
     */
    public function setPublicWriteAccess($flag) {
        $this->setAccess(self::PUBLIC_KEY, "write", $flag);
        return $this;
    }

    /**
     * Get explicit read access for role
     *
     * Even if it returns false, the group may still be able to access
     * object if object is accessible to public.
     *
     * @param string|Role Role object or name
     * @return bool
     */
    public function getRoleReadAccess($role) {
        if ($role instanceof Role) {
            $role = $role->getName();
        }
        return $this->getAccess("role:$role", "read");
    }

    /**
     * Get explicit write access for role
     *
     * Even if it returns false, the group may still be able to access
     * object if object is accessible to public.
     *
     * @param string|Role Role object or name
     * @return bool
     */
    public function getRoleWriteAccess($role) {
        if ($role instanceof Role) {
            $role = $role->getName();
        }
        return $this->getAccess("role:$role", "write");
    }

    /**
     * Set read access for role
     *
     * @param string|Role $role Role object or role name
     * @param bool            $flag
     * @return self
     */
    public function setRoleReadAccess($role, $flag) {
        if ($role instanceof Role) {
            $role = $role->getName();
        }
        if (!is_string($role)) {
            throw new \InvalidArgumentException("role must be either " .
                                                "Role or string.");
        }
        $this->setAccess("role:$role", "read", $flag);
        return $this;
    }

    /**
     * Set write access for role
     *
     * @param string|Role $role Role object or role name
     * @param bool            $flag
     * @return self
     */
    public function setRoleWriteAccess($role, $flag) {
        if ($role instanceof Role) {
            $role = $role->getName();
        }
        if (!is_string($role)) {
            throw new \InvalidArgumentException("role must be either " .
                                                "Role or string.");
        }
        $this->setAccess("role:$role", "write", $flag);
        return $this;
    }

    /**
     * Get explicit read access for user
     *
     * Even if it returns false, the user may still be able to access
     * object if object is accessible to public or a role the user
     * belongs to.
     *
     * @param string|User $user Target user or user id
     * @return bool
     */
    public function getReadAccess($user) {
        if ($user instanceof User) {
            $user = $user->getObjectId();
        }
        return $this->getAccess($user, "read");
    }

    /**
     * Get explicit write access for user
     *
     * Even if it returns false, the user may still be able to access
     * object if object is accessible to public or a role the user
     * belongs to.
     *
     * @param string|User $user Target user or user id
     * @return bool
     */
    public function getWriteAccess($user) {
        if ($user instanceof User) {
            $user = $user->getObjectId();
        }
        return $this->getAccess($user, "write");
    }

    /**
     * Set read access for user
     *
     * @param string|User $user Target user or user id
     * @param bool            $flag Enable or disable read for user
     * @return self
     */
    public function setReadAccess($user, $flag) {
        if ($user instanceof User) {
            if (!$user->getObjectId()) {
                throw new \RuntimeException("user must be saved before " .
                                            "being assigned in ACL.");
            }
            $user = $user->getObjectId();
        }
        if (!is_string($user)) {
            throw new \InvalidArgumentException("user must be either " .
                                                " User or objectId.");
        }
        $this->setAccess($user, "read", $flag);
        return $this;
    }

    /**
     * Set write access for user
     *
     * @param string|User $user Target user or user id
     * @param bool            $flag Enable or disable write for user
     * @return self
     */
    public function setWriteAccess($user, $flag) {
        if ($user instanceof User) {
            if (!$user->getObjectId()) {
                throw new \RuntimeException("user must be saved before " .
                                            "being assigned in ACL.");
            }
            $user = $user->getObjectId();
        }
        if (!is_string($user)) {
            throw new \InvalidArgumentException("user must be either " .
                                                " User or objectId.");
        }
        $this->setAccess($user, "write", $flag);
        return $this;
    }

    /**
     * Encode to JSON representation
     *
     * It returns an associative array, or an empty object if
     * empty. The latter is a workaround as we need to json encode
     * empty ACL as json object, instead of array.
     *
     * @return array|object
     */
    public function encode() {
        return empty($this->data) ? new \stdClass() : $this->data;
    }
}

