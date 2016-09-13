<?php

namespace LeanCloud;

/**
 * SMS
 */
class SMS {

    /**
     * Request SMS code
     *
     * Besides sending default message with sms code, you can also
     * send with a customized template, which though must be submitted
     * and approved before sending.
     *
     * The available of options are:
     *
     * - `$smsType` (string): "sms" or "voice"
     * - `$template` (string): Template name that has been approved
     * - `$name` (string): App name
     * - `$ttl` (int): Number of minutes before sms code expires
     * - `$op` (string): Operation name for the sms code
     *
     *
     * @param string $phoneNumber
     * @param array $options
     *
     * @link https://leancloud.cn/docs/rest_sms_api.html
     */
    public static function requestSmsCode($phoneNumber,
                                          $options=array()) {
        forEach ($options as $k => $v) {
            if (!isset($options[$k])) {
                unset($options[$k]);
            }
        }
        $options["mobilePhoneNumber"] = $phoneNumber;
        Client::post("/requestSmsCode", $options);
    }

    /**
     * Verify SMS code
     *
     * @param string $phoneNumber
     * @param string $smsCode
     */
    public static function verifySmsCode($phoneNumber, $smsCode) {
        Client::post("/verifySmsCode/{$smsCode}?mobilePhoneNumber={$phoneNumber}",
                     null);
    }

}
