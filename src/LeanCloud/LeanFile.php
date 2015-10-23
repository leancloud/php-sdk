<?php
namespace LeanCloud;

use LeanCloud\LeanObject;
use LeanCloud\LeanClient;
use LeanCloud\LeanException;
use LeanCloud\MIMEType;

/**
 * LeanFile
 *
 */
class LeanFile {

    /**
     * File object data on LeanCloud
     *
     * @var array
     */
    private $_data;

    /**
     * Meta data of file
     *
     *     {
     *         "size":      128,
     *         "_checksum": "md5sum",
     *         "owner":     "upload user id",
     *         "__source":  "external"
     *     }
     *
     * @var array
     */
    private $_metaData;

    /**
     * File data
     *
     * @var string
     */
    private $_source;

    /**
     * Initialize file
     *
     * @param string $name     File base name
     * @param mixed  $data     (optional) File content
     * @param string $mimeType (optional) Mime type
     */
    public function __construct($name, $data=null, $mimeType=null) {
        $this->_data["name"] = $name;
        $this->_source       = $data;

        if (!$mimeType) {
            $ext      = pathinfo($name, PATHINFO_EXTENSION);
            $mimeType = MIMEType::getType($ext);
        }
        $this->_data["mime_type"] = $mimeType;

        $user = LeanUser::getCurrentUser();
        $this->_metaData["owner"] = $user ? $user->getObjectId() : "unknown";
        if ($this->_source) {
            $this->_metaData["size"] = strlen($this->_source);
        }
    }

    /**
     * Create file with public external URL
     *
     * @param string $name     File base name
     * @param string $url      Public URL
     * @param string $mimeType (optional)
     * @return LeanFile
     */
    public static function createWithUrl($name, $url, $mimeType=null) {
        $file = new LeanFile($name, null, $mimeType);
        $file->_data["url"]          = $url;
        $file->_metaData["__source"] = "external";
        return $file;
    }

    /**
     * Create file with raw data
     *
     * @param string $name File name
     * @param string $data File content
     * @param string $mimeType
     * @return LeanFile
     */
    public static function createWithData($name, $data, $mimeType=null) {
        $file = new LeanFile($name, $data, $mimeType);
        return $file;
    }

    /**
     * Create file from disk
     *
     * @param string $filepath Absolute file path
     * @param string $mimeType
     * @return LeanFile
     * @throws ErrorException When failed to read file.
     */
    public static function createWithLocalFile($filepath, $mimeType=null) {
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \ErrorException("Read file error at $filepath");
        }
        return static::createWithData(basename($filepath), $content, $mimeType);
    }

    /**
     * Get file attribute
     *
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        if (isset($this->_data[$key])) {
            return $this->_data[$key];
        }
        return null;
    }

    /**
     * Get name of file
     *
     * @return string
     */
    public function getName() {
        return $this->get("name");
    }

    /**
     * Get objectId of file
     *
     * @return string
     */
    public function getObjectId() {
        return $this->get("objectId");
    }

    /**
     * Get file MIME type
     *
     * @return string
     */
    public function getMimeType() {
        return $this->get("mime_type");
    }

    /**
     * Get file url
     *
     * @return string
     */
    public function getUrl() {
        return $this->get("url");
    }

    /**
     * Get thumbnail URL
     *
     * It returns a URL that could be used to display file thumbnail on
     * client. The thumbnail will be generated on-the-fly when request
     * arrives.
     *
     * @param float|int $width      Image width
     * @param float|int $height     Image height
     * @param float|int $quality    Image quality factor between 0 ~ 100
     * @param bool      $scaleToFit Scale to fit, or cut to fit
     * @param string    $format     Image format: gif, png, webp etc.
     * @return string
     */
    public function getThumbUrl($width, $height, $quality=100,
                                   $scaleToFit=true, $format="png") {
        if (!$this->getUrl()) {
            throw new \ErrorException("URL not available.");
        }
        if ($width < 0 || $height < 0) {
            throw new \IllegalArgumentException("Width or height must".
                                                " be positve.");
        }
        if ($quality > 100 || $quality < 0) {
            throw new \IllegalArgumentException("Quality must be between".
                                                " 0 and 100.");
        }
        $mode = $scaleToFit ? 2 : 1;
        return $this->getUrl() . "?imageView/{$mode}/w/{$width}/h/{$height}".
                               "/q/{$quality}/format/{$format}";
    }

    /**
     * Get file size
     *
     * @return int Number of bytes
     */
    public function getSize() {
        return $this->getMeta("size");
    }

    /**
     * Get id of uploaded user
     *
     * @return string|null
     */
    public function getOwnerId() {
        return $this->getMeta("owner");
    }

    /**
     * Set meta data
     *
     * @param string $key
     * @param miexed $val
     * @return $this
     */
    public function setMeta($key, $val) {
        $this->_metaData[$key] = $val;
        return $this;
    }

    /**
     * Get meta data
     *
     * It returns all metaData if key is null
     *
     * @param string $key
     * @return mixed
     */
    public function getMeta($key=null) {
        if (!$key) {
            return $this->_metaData;
        }

        if (isset($this->_metaData[$key])) {
            return $this->_metaData[$key];
        }
        return null;
    }

    /**
     * Generate pseudo-uuid key for filename
     *
     * @return string
     */
    private static function genFileKey() {
        $octets = array_map(function() {
            $num = floor((1 + LeanClient::randomFloat()) * 0x10000);
            return substr(dechex($num), 1);
        }, range(0, 4));
        return implode("", $octets);
    }

    /**
     * Is the file exteranl
     *
     * @return bool
     */
    private function isExternal() {
        return $this->getMeta("__source") == "external";
    }

    /**
     * Merge server response after save
     *
     * @param array $data JSON decoded response
     * @return null
     */
    public function mergeAfterSave($data) {
        if (isset($data["size"])) {
            if (isset($data["metaData"])) {
                $data["metaData"]["size"] = $data["size"];
            } else {
                $data["metaData"] = array("size" => $data["size"]);
            }
            unset($data["size"]);
        }
        $this->mergeAfterFetch($data);
    }

    /**
     * Merge server response after fetch
     *
     * @param array $data JSON decoded response
     * @return null
     */
    public function mergeAfterFetch($data) {
        $meta = array();
        if (isset($data["metaData"])) {
            $meta = $data["metaData"];
            unset($data["metaData"]);
        }
        $this->_metaData = array_merge($this->_metaData, $meta);
        $this->_data     = array_merge($this->_data,     $data);
    }

    /**
     * If there are unsaved changes.
     *
     * @return bool
     */
    public function isDirty() {
        $id = $this->getObjectId();
        return empty($id);
    }

    /**
     * Save file on the cloud
     *
     * @return null
     */
    public function save() {
        if (!$this->isDirty()) {
            return;
        }

        $data = array(
            "name" => $this->getName(),
            "ACL"  => $this->get("ACL"),
            "mime_type" => $this->getMimeType(),
            "metaData"  => $this->getMeta(),
        );

        if ($this->isExternal()) {
            $data["url"] = $this->getUrl();
            $resp = LeanClient::post("/files/{$this->getName()}", $data);
            $this->mergeAfterSave($resp);
        } else {
            $key = static::genFileKey();
            $key .= "." . pathinfo($this->getName(), PATHINFO_EXTENSION);
            $data["key"] = $key;
            $resp  = LeanClient::post("/qiniu", $data);
            $token = $resp["token"];
            unset($resp["token"]);
            $this->mergeAfterSave($resp);

            LeanClient::uploadToQiniu($token, $this->_source, $key,
                                      $this->getMimeType());
        }
    }

    /**
     * Fetch file object by id
     *
     * Note it fetches descriptive data from LeanCloud, but not file content.
     * The content should be fetched from file URL.
     *
     * @return LeanFile
     */
    public static function fetch($objectId) {
        $file = new LeanFile("");
        $resp = LeanClient::get("/files/{$objectId}");
        $file->mergeAfterFetch($resp);
        return $file;
    }

    /**
     * Delete file on cloud
     *
     * @return null
     */
    public function destroy() {
        if (!$this->getObjectId()) {
            return false;
        }
        LeanClient::delete("/files/{$this->getObjectId()}");
    }

    /**
     * Encode to JSON representation
     *
     * @return array
     */
    public function encode() {
        if (!$this->getObjectId()) {
            throw new \ErrorException("Cannot serialize unsaved file.");
        }
        return array(
            "__type" => "File",
            "id"     => $this->getObjectId(),
            "name"   => $this->getName(),
            "url"    => $this->getUrl()
        );
    }
}

