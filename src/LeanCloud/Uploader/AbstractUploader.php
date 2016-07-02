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
     * @param array  $file     File data and attributes
     * @param array  $params   Additional form params for provider
     * @param string $boundary Boundary string used for frontier
     * @return string          Multipart encoded string
     */
    public static function multipartEncode($file, $params, $boundary) {
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
            // escape quotes in file name
            $filename = filter_var($file["name"],
                                   FILTER_SANITIZE_MAGIC_QUOTES);

            $body .= <<<EOT
--{$boundary}
Content-Disposition: form-data; name="{$this->getFileFieldName}"; filename="{$filename}"
Content-Type: {$mimeType}

{$file['content']}

EOT;
        }

        // append end frontier
        $body .=<<<EOT
--{$boundary}

EOT;

        return $body;
    }


    public function initialize($url, $token) {
        $this->uploadUrl = $url;
        $this->authToken = $token;
    }

    public function getUploadUrl() {
        return $this->uploadUrl;
    }

    abstract public function upload($content, $mimeType, $key);
}