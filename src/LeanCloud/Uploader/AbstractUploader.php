<?php

namespace LeanCloud\Uploader;

abstract class AbstractUploader {
    protected $uploadUrl;
    protected $authToken;

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
        $body = "";

        forEach($params as $key => $val) {
            $body .= <<<EOT
--{$boundary}
Content-Disposition: form-data; name="{$key}"

{$val}

EOT;
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

            $body .= <<<EOT
--{$boundary}
Content-Disposition: form-data; name="{$fieldname}"; filename="{$filename}"
Content-Type: {$mimeType}

{$file['content']}

EOT;
        }

        // append end frontier
        $body .=<<<EOT
--{$boundary}--

EOT;

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