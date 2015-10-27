LeanCloud PHP SDK
====

[![Build Status](https://travis-ci.org/leancloud/php-sdk.svg?
branch=master)](https://travis-ci.org/leancloud/php-sdk)

LeanCloud 为应用提供了从数据存储，消息推送，实时通信到离线分析等全方位
的一站式云端服务，帮助应用开发者降低后端开发及维护成本，为应用开发加速。
PHP SDK 提供了对数据存储，用户管理等模块的 PHP 实现及接口，以方便 PHP
应用的开发。

安装
----

运行环境要求 PHP 5.3 及以上版本，以及
[cURL](http://php.net/manual/zh/book.curl.php)。

#### composer 安装

如果你的项目使用 composer, 那么安装 LeanCloud PHP SDK 将非常容易：

    composer require leancloud/leancloud-sdk

#### 手动下载安装

* 前往发布页面下载最新版本: https://github.com/leancloud/php-sdk/releases

```bash
$ cd $APP_ROOT
$ wget https://github.com/leancloud/php-sdk/archive/vX.X.X.zip
```

* 将压缩文件解压并置于项目文件夹下，如 $APP_ROOT/vendor/leancloud

```bash
$ unzip vX.X.X.zip
$ mv php-sdk-X.X.X $APP_ROOT/vendor/leancloud
```

#### 初始化

完成上述安装后，请加载库(在项目的一开始就需要加载，且只需加载一次)：

```php
require_once("vendor/autoload.php");               // if installed via composer
require_once("vendor/leancloud/src/autoload.php"); // if installed manually
```

初始化应用的 ID 及 Key（在 LeanCloud 控制台应用的设置页面可获得 app id, app key,
master key）:

```php
LeanCloud\LeanClient::initialize("app_id", "app_key", "master_key");

// 我们目前支持 CN 和 US 区域，默认使用 CN 区域，可以切换为 US 区域
LeanCloud\LeanClient::useRegion("US");
```

测试应用已经正确初始化：

```php
LeanCloud\LeanClient::get("/date"); // 获取服务器时间
// => {"__type": "Date", "iso": "2015-10-01T09:45:45.123Z"}
```


使用示例
----

#### 用户注册及管理

注册一个用户:

```php
use LeanCloud\LeanUser;
use LeanCloud\CloudException;

$user = new LeanUser();
$user->setUsername("alice"):
$user->setEmail("alice@example.net");
$user->setPassword("passpass");
try {
    $user->signUp();
} catch (CloudException $ex) {
    // it'll throw CloudException if signUp failed, e.g.
    // the alice username has been taken.
}

// After signUp it will become current user, which you can get by:
LeanUser::getCurrentUser();
// You can also get current user sessionToken
LeanUser::getCurrentSessionToken();
```

登录一个用户:

```php
use LeanCloud\LeanUser;
use LeanCloud\CloudException;

LeanUser::logIn("alice", "passpass");
// it will become current user then
$user = LeanUser::getCurrentUser();
$token = LeanUser::getCurrentSessionToken();

// By default we cache current sessionToken in LeanClinet::getStorage(),
// which might be `CookieStorage` or `SessionStorage`. You can cache it
// elsewhere, it is easy to get user by sessionToken:
LeanUser::become($token);

// we also support login with sms code, and login with 3rd party
// auth data, e.g. weibo, weixin. Please see our doc for details.
LeanUser::logInWithSmsCode("phone number", "sms code");
LeanUser::logInWith("weibo", array("openid" => ...));
```

#### 对象存储

```php
use LeanCloud\LeanObject;
use LeanCloud\CloudException;

$obj = new LeanObject("Human");
$obj->set("name", "alice");
$obj->set("height", 60.0);
$obj->set("weight", 4.5);
$obj->set("birthdate", new DateTime());
try {
    $obj->save();
} catch (CloudException $ex) {
    // it throws CloudException if save to cloud failed
}

// get fields
$obj->get("name");
$obj->get("height");
$obj->get("birthdate");

// atomatically increment field
$obj->increment("age", 1);
// add values to array field
$obj->add("colors", array("blue", "magenta" ...));
// add values uniquely
$obj->addUnique("colors", ...);
// remove values from array field
$obj->remove("colors", ...);

// save changes to cloud
try {
    $obj->save();
} catche (CloudException $ex) {
    // ...
}

// destroy it on cloud
$obj->destroy();
```

我们同样支持子类继承，子类中需要定义静态变量 `$className` ，并注册到存储类:

```php
class Human extends LeanObject {
    protected static $className = "Human";
    public setName($name) {
        $this->set("name", $name);
        return $this;
    }
}
// register it as storage class
Human::registerClass();

$human = new Human();
$human->setName();
$human->set("eyeColor", "blue");
...
```

#### 对象查询

给定一个 objectId，可以如下获取对象。

```php
use LeanCloud\LeanQuery;

$query = new LeanQuery("Human");
$obj = $query->get($objectId);
```

更为复杂的条件查询：

```php
$query = new LeanQuery("Human");
$query->lessThan("height", 100.0);
$query->greaterThanOrEqualTo("weight", 5.0);
$query->addAscend("birthdate");
$query->addDescend("name");
$query->count(); // return count of result
$query->first(); // return first object

$query->skip(100); // skip number of rows
$query->limit(20); // limit number of rows to return
$objects = $query->find(); // return all objects
```

#### 文件存储

直接创建文件:

```php
use LeanCloud\LeanFile;
$file = LeanFile::createWithData("hello.txt", "Hello LeanCloud!");
try {
    $file->save();
} catch (CloudException $ex) {
    // file save failed...
}

$file->getSize();
$file->getName();
$file->getUrl();
```

由本地文件创建：

```php
$file = LeanFile::createWithLocalFile("/tmp/myfile.png");
try {
    $file->save();
} catch (CloudException $ex) {
    // file save failed...
}

// Get url to display a thumb view of file
$url = $file->getThumbUrl();
```

由已知的 URL 创建文件:

```php
$file = LeanFile::createWithUrl("image.png", "http://example.net/image.png");
try {
    $file->save();
} catch (CloudException $ex) {
    // file save failed...
}
```

更加细节的行为请参考 API 文档。

贡献
----

See Hacking.md if you'd like to contribute.

