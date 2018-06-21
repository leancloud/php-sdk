<?php
namespace LeanCloud;

if (PHP_VERSION_ID >= 70200) {
    throw new \RuntimeException("'Object` was reserved by PHP 7.2, use 'LeanObject' instead, see https://url.leanapp.cn/php72-object-deprecated");
} else {
    $filename = sys_get_temp_dir() . "/php72-object-deprecated";

    if (!file_exists($filename)) {
        touch($filename);
        error_log("Warning: 'Object' was deprecated, use 'LeanObject' instead, see https://url.leanapp.cn/php72-object-deprecated");
    }

    class_alias('\LeanCloud\LeanObject', '\LeanCloud\Object');
}
