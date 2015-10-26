<?php

use LeanCloud\LeanClient;
use LeanCloud\LeanFile;

class LeanFileTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        LeanClient::initialize(
            getenv("LC_APP_ID"),
            getenv("LC_APP_KEY"),
            getenv("LC_APP_MASTER_KEY"));
        LeanClient::useRegion(getenv("LC_API_REGION"));
    }

    public function testInitializeEmptyFileName() {
        $file = new LeanFile("");
        $this->assertEquals("", $file->getName());
    }

    public function testInitializeMimeType() {
        $file = new LeanFile("test.txt");
        $this->assertEquals("text/plain", $file->getMimeType());

        $file = new LeanFile("test.txt", null, "image/png");
        $this->assertEquals("image/png", $file->getMimeType());
    }

    public function testCreateWithURL() {
        $file = LeanFile::createWithUrl("blabla.png", "https://leancloud.cn/favicon.png");
        $this->assertEquals("blabla.png", $file->getName());
        $this->assertEquals("https://leancloud.cn/favicon.png", $file->getUrl());
        $this->assertEquals("image/png",   $file->getMimeType());
    }

    public function testSaveTextFile() {
        $file = LeanFile::createWithData("test.txt", "Hello World!");
        $file->save();
        $this->assertNotEmpty($file->getObjectId());
        $this->assertNotEmpty($file->getUrl());
        $this->assertNotEmpty($file->getName());
        $this->assertEquals("text/plain", $file->getMimeType());
        $content = file_get_contents($file->getUrl());
        $this->assertEquals("Hello World!", $content);

        $file->destroy();
    }

    public function testSaveUTF8TextFile() {
        $file = LeanFile::createWithData("test.txt", "你好，中国!");
        $file->save();
        $this->assertNotEmpty($file->getUrl());
        $this->assertEquals("text/plain", $file->getMimeType());
        $content = file_get_contents($file->getUrl());
        $this->assertEquals("你好，中国!", $content);

        $file->destroy();
    }

    public function testFetchFile() {
        $file = LeanFile::createWithData("test.txt", "你好，中国!");
        $file->save();
        $file2 = LeanFile::fetch($file->getObjectId());
        $this->assertEquals($file->getUrl(), $file2->getUrl());
        $this->assertEquals($file->getName(), $file2->getName());
        $this->assertEquals($file->getSize(), $file2->getSize());

        $file->destroy();
    }

    public function testGetCreatedAtAndUpdatedAt() {
        $file = LeanFile::createWithData("test.txt", "你好，中国!");
        $file->save();
        $this->assertNotEmpty($file->getUrl());
        $this->assertNotEmpty($file->getCreatedAt());
        $this->assertTrue($file->getCreatedAt() instanceof \DateTime);

        $file->destroy();
    }

    public function testMetaData() {
        $file = LeanFile::createWithData("test.txt", "你好，中国!");
        $file->setMeta("language", "zh-CN");
        $file->setMeta("bool", false);
        $file->setMeta("downloads", 100);
        $file->save();
        $file2 = LeanFile::fetch($file->getObjectId());
        $this->assertEquals("zh-CN", $file2->getMeta("language"));
        $this->assertEquals(false, $file2->getMeta("bool"));
        $this->assertEquals(100, $file2->getMeta("downloads"));

        $file->destroy();
    }
}

