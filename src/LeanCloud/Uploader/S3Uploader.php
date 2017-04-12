<?php

namespace LeanCloud\Uploader;
use LeanCloud\Client;

/**
 * Pre-signed URL Uploader for S3
 *
 * @link http://docs.aws.amazon.com/AmazonS3/latest/dev/PresignedUrlUploadObject.html
 */

class S3Uploader extends SimpleUploader {

    public function upload($content, $mimeType, $name=null) {
        if (!$this->getUploadUrl()) {
            throw new \RuntimeException("Please initialize with pre-signed url.");
        }
        $headers[] = "User-Agent: " . Client::getVersionString();
        $headers[] = "Content-Type: $mimeType";
        $url       = $this->getUploadUrl();
        $ch        = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        $resp     = curl_exec($ch);
        $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error    = curl_error($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno > 0) {
            throw new \RuntimeException("CURL ({$url}) error: " .
                                        "{$errno} {$error}",
                                        $errno);
        }

        if ($respCode >= "300") {
            $S3Error = simplexml_load_string($resp);
            throw new \RuntimeException("Upload to S3 ({$url}) failed: " .
                                        "{$S3Error->Code} {$S3Error->Message}");
        }
        return true;
    }
}
