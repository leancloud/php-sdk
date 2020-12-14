<?php

use LeanCloud\LeanObject;
use LeanCloud\Client;
use LeanCloud\File;
use PHPUnit\Framework\TestCase;

class MasterTest extends TestCase {
    public static function setUpBeforeClass() {
        Client::initialize(
            getenv("LEANCLOUD_APP_ID"),
            getenv("LEANCLOUD_APP_KEY"),
            getenv("LEANCLOUD_APP_MASTER_KEY"));
        Client::useMasterKey(true);
    }

    public function testSaveWithSpecifiedKey() {
        $file = File::createWithData("test.txt", "Hello World!");
        $file->setKey("abc");
        $this->assertEquals("abc", $file->getKey());
        $file->save();
        $this->assertNotEmpty($file->getObjectId());
        $this->assertNotEmpty($file->getName());

        $this->assertEquals("abc", $file->getKey());
        $url = $file->getUrl();
        $parsedUrl = parse_url($url);
        $path = $parsedUrl["path"];
        $oldSchoolAwsS3FileUrlPrefix = "/avos-cloud-";
        $newAwsS3GluttonyUrlPrefix = "https://lc-gluttony";
        if (substr($path, 0, strlen($oldSchoolAwsS3FileUrlPrefix)) === $oldSchoolAwsS3FileUrlPrefix) {
            $splitPath = explode("/", $path, 3);
            $this->assertEquals("abc", $splitPath[2]);
        } else if (substr($url, 0, strlen($newAwsS3GluttonyUrlPrefix)) === $newAwsS3GluttonyUrlPrefix) {
            $gluttonyPath = "/" + substr(getenv("LEANCLOUD_APP_KEY"), 0, 12) + "/abc";
            $this->assertEquals($gluttonyPath, $path);
        } else {
            $this->assertEquals("/abc", $path);
        }

        $this->assertEquals("text/plain", $file->getMimeType());
        $content = file_get_contents($url);
        $this->assertEquals("Hello World!", $content);

        $file->destroy();
    }
}