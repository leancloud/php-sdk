LeanCloud PHP SDK
====

[![Build Status](https://img.shields.io/travis/leancloud/php-sdk.svg)
](https://travis-ci.org/leancloud/php-sdk)
[![Latest Version](https://img.shields.io/packagist/v/leancloud/leancloud-sdk.svg)
](https://packagist.org/packages/leancloud/leancloud-sdk)
[![Coverage Status](https://img.shields.io/codecov/c/github/leancloud/php-sdk/master.svg)](https://codecov.io/github/leancloud/php-sdk)

LeanCloud 为应用提供了从数据存储，消息推送，实时通信到离线分析等全方位
的一站式云端服务，帮助应用开发者降低后端开发及维护成本，为应用开发加速。
PHP SDK 提供了对数据存储，用户管理等模块的 PHP 实现及接口，以方便 PHP
应用的开发。

安装
----

运行环境要求 PHP 5.6 及以上版本，以及
[cURL](http://php.net/manual/zh/book.curl.php)。

#### composer 安装

如果使用标准的包管理器 composer，你可以很容易的在项目中添加依赖并下载：

```bash
composer require leancloud/leancloud-sdk
```

#### 手动下载安装

你也可以前往[发布页面](https://github.com/leancloud/php-sdk/releases)
手动下载安装包。假设你的应用位于 `$APP_ROOT` 目录下：

```bash
cd $APP_ROOT
wget https://github.com/leancloud/php-sdk/archive/vX.X.X.zip

# 解压并置于 vendor 目录
unzip vX.X.X.zip
mv php-sdk-X.X.X vendor/leancloud
```

初始化
----

完成上述安装后，需要对 SDK 初始化。如果已经创建应用，可以在 LeanCloud
[**控制台** > **应用设置**]里找到应用的 ID 和 key。然后在项目中加载 SDK，
并初始化：

```php
// 如果是 composer 安装
// require_once("vendor/autoload.php");

// 如果是手动安装
require_once("vendor/leancloud/src/autoload.php");

// 参数依次为 app-id, app-key, master-key
LeanCloud\Client::initialize("app_id", "app_key", "master_key");
```

使用示例
----

#### 用户注册及管理

注册一个用户:

```php
use LeanCloud\User;
use LeanCloud\CloudException;

$user = new User();
$user->setUsername("alice");
$user->setEmail("alice@example.net");
$user->setPassword("passpass");
try {
    $user->signUp();
} catch (CloudException $ex) {
    // 如果 LeanCloud 返回错误，这里会抛出异常 CloudException
    // 如用户名已经被注册：202 Username has been taken
}

// 注册成功后，用户被自动登录。可以通过以下方法拿到当前登录用户和
// 授权码。
User::getCurrentUser();
User::getCurrentSessionToken();
```

登录一个用户:

```php
User::logIn("alice", "passpass");
$user = User::getCurrentUser();
$token = User::getCurrentSessionToken();

// 给定一个 token 可以很容易的拿到用户
User::become($token);

// 我们还支持短信验证码，及第三方授权码登录
User::logInWithSmsCode("phone number", "sms code");
User::logInWith("weibo", array("openid" => "..."));
```

#### 对象存储

```php
use LeanCloud\LeanObject;
use LeanCloud\CloudException;

$obj = new LeanObject("TestObject");
$obj->set("name", "alice");
$obj->set("height", 60.0);
$obj->set("weight", 4.5);
$obj->set("birthdate", new \DateTime());
try {
    $obj->save();
} catch (CloudException $ex) {
    // CloudException 会被抛出，如果保存失败
}

// 获取字段值
$obj->get("name");
$obj->get("height");
$obj->get("birthdate");

// 原子增加一个数
$obj->increment("age", 1);

// 在数组字段中添加，添加唯一，删除
// 注意: 由于API限制，不同数组操作之间必须保存，否则会报错
$obj->addIn("colors", "blue");
$obj->save();
$obj->addUniqueIn("colors", "orange");
$obj->save();
$obj->removeIn("colors", "blue");
$obj->save();

// 在云存储上删除数据
$obj->destroy();
```

我们同样支持子类继承，子类中需要定义静态变量 `$className` ，并注册到存储类:

```php
class TestObject extends LeanObject {
    protected static $className = "TestObject";
    public setName($name) {
        $this->set("name", $name);
        return $this;
    }
}
// register it as storage class
TestObject::registerClass();

$obj = new TestObject();
$obj->setName();
$obj->set("eyeColor", "blue");
...
```

#### 对象查询

给定一个 objectId，可以如下获取对象。

```php
use LeanCloud\Query;

$query = new Query("TestObject");
$obj = $query->get($objectId);
```

更为复杂的条件查询：

```php
$query = new Query("TestObject");
$query->lessThan("height", 100.0);           // 小于
$query->greaterThanOrEqualTo("weight", 5.0); // 大于等于
$query->addAscend("birthdate");              // 递增排序
$query->addDescend("name");                  // 递减排序
$query->count();
$query->first(); // 返回第一个对象

$query->skip(100);
$query->limit(20);
$objects = $query->find(); // 返回查询到的对象
```

#### 文件存储

直接创建文件:

```php
use LeanCloud\File;
$file = File::createWithData("hello.txt", "Hello LeanCloud!");
try {
    $file->save();
} catch (CloudException $ex) {
    // 云存储返回错误，保存失败
}

$file->getSize();
$file->getName();
$file->getUrl();
```

由本地文件创建：

```php
$file = File::createWithLocalFile("/tmp/myfile.png");
try {
    $file->save();
} catch (CloudException $ex) {
    // 云存储返回错误，保存失败
}

// 获取文件缩略图的链接
$url = $file->getThumbUrl();
```

由已知的 URL 创建文件:

```php
$file = File::createWithUrl("image.png", "http://example.net/image.png");
try {
    $file->save();
} catch (CloudException $ex) {
    // 云存储返回错误，保存失败
}
```

更多文档请参考
[PHP 数据存储开发指南](https://leancloud.cn/docs/leanstorage_guide-php.html)

贡献
----

See Hacking.md if you'd like to contribute.
