<?php

use LeanCloud\LeanObject;
use LeanCloud\Relation;
use PHPUnit\Framework\TestCase;

/**
 * Test on Relation
 *
 * For all the test cases, suppose TestObject has a relation field
 * `likes`, where it keeps Users who liked the test object.
 *
 */
class RelationTest extends TestCase {
    public function testRelationEncode() {
        $obj = new LeanObject("TestObject");
        $rel = $obj->getRelation("likes");
        $out = $rel->encode();
        $this->assertEquals("Relation", $out["__type"]);
    }

    public function testRelationClassEncode() {
        $obj = new LeanObject("TestObject");
        $rel = $obj->getRelation("likes");
        $out = $rel->encode();
        $this->assertEquals("Relation", $out["__type"]);

        $child1 = new LeanObject("User", "abc101");
        $rel->add($child1);
        $out = $rel->encode();
        $this->assertEquals("User", $out["className"]);
    }

    public function testGetRelationOnTargetClass() {
        $obj = new LeanObject("TestObject", "id123");
        $rel = new Relation($obj, "likes", "User");
        $query = $rel->getQuery();
        $this->assertEquals("User", $query->getClassName());
    }

    public function testGetRelationQueryWithoutTargetClass() {
        $obj = new LeanObject("TestObject", "id123");
        $rel = new Relation($obj, "likes");
        $query = $rel->getQuery();

        // the query should be made against the parent class, with
        // redirect key being set, so it will be redirected to target
        // class.
        $this->assertEquals("TestObject", $query->getClassName());
        $out = $query->encode();
        $this->assertEquals("likes", $out["redirectClassNameForKey"]);
    }

    public function getReverseQueryOnChildObject() {
        $obj = new LeanObject("TestObject", "id123");
        $rel = new Relation($obj, "likes", "User");
        $child = new LeanObject("User", "id124");
        $query = $rel->getReverseQuery($child);
        $this->assertEquals("TestObject", $query->getClassName());
    }
}

