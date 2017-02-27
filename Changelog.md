
0.5.4 发布日期：2017-02-27
----

* 修复推送请求中的格式不兼容问题

0.5.3 发布日期：2017-02-13
----

* 支持小写的 region

0.5.2 发布日期：2017-01-22
----

* 修复 pointer 对象序列化为 object
* 创建本地文件时支持传入文件名

0.5.1 发布日期：2016-11-25
----

* 修复 PHP 5.6 一下版本不能定义 array 常量的 bug

0.5.0 发布日期：2016-11-18
----

* 添加 User#getRoles 方法获取角色
* 添加 User#isAuthenticated 方法检测用户是否登录
* 添加 User#refreshSessionToken 方法重置 token

0.4.2 发布日期：2016-11-03
----

* 修复毫秒丢失的问题 #114
* 修复 Relation 不能编码的异常 #110
* Push 设置默认的 prod 参数 #111
* 增加 `Client::setDebug(true)` 支持调试模式 #108
* 添加 OptionSave 类支持 fetchWhenSave 以及 where #49 #83

0.4.1 发布日期：2016-09-13
----

* 支持实时通信的相关 hook 及校验
* 支持通用短信发送接口

0.4.0 发布日期：2016-08-10
----

**不兼容改动**

为了与其它语言 SDK 类型名保持一致，将主要类型名称的 Lean 前缀去掉。如
果升级，请注意同步修改代码。

以下是去掉 `Lean` 前缀的类型列表：

```
LeanACL LeanBytes LeanClient LeanFile LeanObject LeanPush
LeanQuery LeanRelation LeanRole LeanUser
```

0.3.0 发布日期：2016-06-30
----

* 支持云引擎，及 Slim 框架的中间件

0.2.6 发布日期：2016-05-16
----

* LeanPush 支持同时向多平台发送推送
* LeanObject::save, fetch, destroy 不再返回批量查询错误
* 修复 LeanACL 为空时被编码为 array 的问题
  - LeanACL::encode 将返回 object (不兼容)
* 修复 LeanRole 查询不能正常初识化
  - LeanRole 构造函数接收两个可选参数 className, objectId (不兼容)

0.2.5 发布日期：2016-02-01
----
* 支持手机号码和密码登录
* 修复查询 `_User` 未传递 sessionToken 导致查询失败

0.2.4 发布日期：2016-01-26
----

* 修复短信验证码登录后 current user 为空的问题

0.2.3 发布日期：2016-01-12
----

* 修复 getCurrentUser 循环调用问题 close #48

0.2.2 发布日期：2016-01-06
----

* 修复保存关联文件的对象时的语法错误 close #46

0.2.1 发布日期：2015-12-31
----

* 修复类型不安全的字符串比较 close #43

0.2.0 发布日期：2015-11-13
----
* 支持 CQL 查询：LeanQuery::doCloudQuery
* 支持发送 Push 推送消息 (#23)
* 支持 GeoPoint 类型及地理位置查询 (#25)
* 支持 Role 和 ACL 权限管理 (#19)
* 修复: `LeanClient::useMasterKey()` 没有生效的问题 #21
* `LeanClient::decode()` 添加第二个参数以识别 ACL
  (**与上一版本不兼容**)

0.1.0 发布日期: 2015-10-30
----

