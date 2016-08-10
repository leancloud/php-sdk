<?php

namespace LeanCloud\Uploader;

abstract class SimpleUploader {
    protected $uploadUrl;
    protected $authToken;

    /**
     * Create uploader by provider
     *
     * @param string $provider File provider: qiniu, s3, etc
     * @return SimpleUploader
     */
    public static function createUploader($provider) {
        if ($provider === "qiniu") {
            return new QiniuUploader();
        } else if ($provider === "s3") {
            return new S3Uploader();
        } else if ($provider === "qcloud") {
            return new QCloudUploader();
        }
        throw new \RuntimeException("File provider not supported: {$provider}");
    }

    /**
     * The form field name of file content in multipart encoded data
     *
     * @return string
     */
    protected static function getFileFieldName() {
        return "file";
    }

    /**
     * Encode file with params in multipart format
     *
     * @param array  $file     File content, name, and mimeType
     * @param array  $params   Additional form params for provider
     * @param string $boundary Boundary string used for frontier
     * @return string          Multipart encoded string
     */
    public function multipartEncode($file, $params, $boundary) {
        $body = "\r\n";

        forEach($params as $key => $val) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= "{$val}\r\n";
        }

        if (!empty($file)) {
            $mimeType = "application/octet-stream";
            if (isset($file["mimeType"])) {
                $mimeType = $file["mimeType"];
            }
            $fieldname = static::getFileFieldName();
            // escape quotes in file name
            $filename = filter_var($file["name"],
                                   FILTER_SANITIZE_MAGIC_QUOTES);

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$fieldname}\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: {$mimeType}\r\n\r\n";
            $body .= "{$file['content']}\r\n";
        }

        // append end frontier
        $body .= "--{$boundary}--\r\n";

        return $body;
    }

    /**
     * Initialize uploader with url and auth token
     *
     * @param string $uploadUrl File provider url
     * @param string $authToken Auth token for file provider
     */
    public function initialize($uploadUrl, $authToken) {
        $this->uploadUrl = $uploadUrl;
        $this->authToken = $authToken;
    }

    public function getUploadUrl() {
        return $this->uploadUrl;
    }

    public function getAuthToken() {
        return $this->authToken;
    }

    abstract public function upload($content, $mimeType, $key);
}
