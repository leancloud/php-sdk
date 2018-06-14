<?php

use LeanCloud\LeanObject;
use LeanCloud\Client;
use LeanCloud\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));

    }

    public function testInitializeEmptyFileName() {
        $file = new File("");
        $this->assertEquals("", $file->getName());
    }

    public function testInitializeMimeType() {
        $file = new File("test.txt");
        $this->assertEquals("text/plain", $file->getMimeType());

        $file = new File("test.txt", null, "image/png");
        $this->assertEquals("image/png", $file->getMimeType());
    }

    public function testCreateWithURL() {
        $file = File::createWithUrl("blabla.png", "https://leancloud.cn/favicon.png");
        $this->assertEquals("blabla.png", $file->getName());
        $this->assertEquals("https://leancloud.cn/favicon.png", $file->getUrl());
        $this->assertEquals("image/png",   $file->getMimeType());
    }

    public function testCreateWithLocalFile() {
        $file = File::createWithLocalFile(__FILE__);
        $this->assertEquals("FileTest.php", $file->getName());
    }

    public function testSaveTextFile() {
        $file = File::createWithData("test.txt", "Hello World!");
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
        $file = File::createWithData("testChinese.txt", "你好，中国!");
        $file->save();
        $this->assertNotEmpty($file->getUrl());
        $this->assertEquals("text/plain", $file->getMimeType());
        $content = file_get_contents($file->getUrl());
        $this->assertEquals("你好，中国!", $content);

        $file->destroy();
    }

    public function testFetchFile() {
        $file = File::createWithData("testFetch.txt", "你好，中国!");
        $file->save();
        $file2 = File::fetch($file->getObjectId());
        // `uploadResult.getUrl() != fetchResult.getUrl()` is a feature
        // $this->assertEquals($file->getUrl(), $file2->getUrl());
        $this->assertEquals($file->getName(), $file2->getName());
        $this->assertEquals($file->getSize(), $file2->getSize());

        $file->destroy();
    }

    public function testGetCreatedAtAndUpdatedAt() {
        $file = File::createWithData("testTimestamp.txt", "你好，中国!");
        $file->save();
        $this->assertNotEmpty($file->getUrl());
        $this->assertNotEmpty($file->getCreatedAt());
        $this->assertTrue($file->getCreatedAt() instanceof \DateTime);

        $file->destroy();
    }

    public function testMetaData() {
        $file = File::createWithData("testMetadata.txt", "你好，中国!");
        $file->setMeta("language", "zh-CN");
        $file->setMeta("bool", false);
        $file->setMeta("downloads", 100);
        $file->save();
        $file2 = File::fetch($file->getObjectId());
        $this->assertEquals("zh-CN", $file2->getMeta("language"));
        $this->assertEquals(false, $file2->getMeta("bool"));
        $this->assertEquals(100, $file2->getMeta("downloads"));

        $file->destroy();
    }

    /*
     * leancloud/php-sdk#46
     */
    public function testSaveObjectWithFile() {
        $obj = new LeanObject("TestObject");
        $obj->set("name", "alice");

        $file = File::createWithData("test.txt", "你好，中国!");
        $obj->addIn("files", $file);
        $obj->save();

        $this->assertNotEmpty($obj->getObjectId());
        $this->assertNotEmpty($file->getObjectId());
        $this->assertNotEmpty($file->getUrl());

        $file->destroy();
        $obj->destroy();
    }

}

