<?php

namespace LeanCloud\Uploader;
use LeanCloud\Client;

/**
 * QCloud COS file uploader
 *
 * @link https://www.qcloud.com/doc/product/227/3377
 */

class QCloudUploader extends SimpleUploader {

    protected static function getFileFieldName() {
        return "filecontent";
    }

    public function upload($content, $mimeType, $key) {
        $boundary = md5(microtime(true));

        $body = $this->multipartEncode(array(
            "name"      => $key,
            "mimeType"  => $mimeType,
            "content"   => $content,
        ), array(
            "op"  => "upload",
            "sha" => hash("sha1", $content)
        ), $boundary);

        $headers[] = "User-Agent: " . Client::getVersionString();
        $headers[] = "Content-Type: multipart/form-data;" .
                     " boundary={$boundary}";
        // $headers[] = "Content-Length: " . strlen($body);
        $headers[] = "Authorization: {$this->getAuthToken()}";
        $url = $this->getUploadUrl();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp     = curl_exec($ch);
        $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error    = curl_error($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        /** type of error:
         *  - curl error
         *  - http status error 4xx, 5xx
         *  - rest api error
         */
        if ($errno > 0) {
            throw new \RuntimeException("CURL ($url) error: " .
                                        "{$errno} {$error}",
                                        $errno);
        }

        $data = json_decode($resp, true);
        if ($data["code"] != 0) {
            throw new \RuntimeException("Upload to Qcloud ({$url}) failed: ".
                                        "{$data['code']} {$data['message']}",
                                        $data["code"]);
        }
        return $data;
    }

}
