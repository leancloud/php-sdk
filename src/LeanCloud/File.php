<?php
namespace LeanCloud;

use LeanCloud\Client;
use LeanCloud\CloudException;
use LeanCloud\MIMEType;
use LeanCloud\Uploader\SimpleUploader;

/**
 * File object on LeanCloud
 *
 */
class File {

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

        $this->_metaData["owner"] = "unknown";
        if (User::$currentUser) {
            $this->_metaData["owner"] = User::$currentUser->getObjectId();
        }
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
     * @return File
     */
    public static function createWithUrl($name, $url, $mimeType=null) {
        $file = new File($name, null, $mimeType);
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
     * @return File
     */
    public static function createWithData($name, $data, $mimeType=null) {
        $file = new File($name, $data, $mimeType);
        return $file;
    }

    /**
     * Create file from disk
     *
     * @param string $filepath Absolute file path
     * @param string $mimeType E.g. "image/png"
     * @param string $name Name of file
     * @return File
     * @throws RuntimeException
     */
    public static function createWithLocalFile($filepath, $mimeType=null, $name=null) {
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \RuntimeException("Read file error at $filepath");
        }
        if (!$name) {
            $name = basename($filepath);
        }
        return static::createWithData($name, $content, $mimeType);
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
     * @return DateTime
     */
    public function getCreatedAt() {
        return $this->get("createdAt");
    }

    /**
     * @return DateTime
     */
    public function getUpdatedAt() {
        return $this->get("updatedAt");
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
            throw new \RuntimeException("File resource not available.");
        }
        if ($width < 0 || $height < 0) {
            throw new \InvalidArgumentException("Width or height must".
                                                " be positve.");
        }
        if ($quality > 100 || $quality < 0) {
            throw new \InvalidArgumentException("Quality must be between".
                                                " 0 and 100.");
        }
        $mode = $scaleToFit ? 2 : 1;
        return $this->getUrl() . "?imageView/{$mode}/w/{$width}/h/{$height}".
                               "/q/{$quality}/format/{$format}";
    }

    /**
     * Get file size
     *
     * @return int
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
     * @return self
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
            $num = floor((1 + Client::randomFloat()) * 0x10000);
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
        return $this->getMeta("__source") === "external";
    }

    /**
     * Merge data and metaData from server response
     *
     * @param array $data
     * @param array $meta Optional meta data
     */
    private function _mergeData($data, $meta=array()) {
        // manually convert createdAt and updatedAt fields so they'll
        // be decoded as DateTime object.
        forEach(array("createdAt", "updatedAt") as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = array("__type" => "Date",
                                    "iso"    => $data[$key]);
            }
        }

        forEach($data as $key => $val) {
            $this->_data[$key] = Client::decode($val, $key);
        }

        forEach($meta as $key => $val) {
            $this->_metaData[$key] = Client::decode($val, $key);
        }
    }

    /**
     * Merge server response after save
     *
     * @param array $data JSON decoded response
     */
    public function mergeAfterSave($data) {
        $meta = array();
        if (isset($data["metaData"])) {
            $meta = $data["metaData"];
            unset($data["metaData"]);
        }
        if (isset($data["size"])) {
            $meta["size"] = $data["size"];
            unset($data["size"]);
        }
        $this->_mergeData($data, $meta);
    }

    /**
     * Merge server response after fetch
     *
     * @param array $data JSON decoded response
     */
    public function mergeAfterFetch($data) {
        $meta = array();
        if (isset($data["metaData"])) {
            $meta = $data["metaData"];
            unset($data["metaData"]);
        }
        $this->_mergeData($data, $meta);
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
     * @throws CloudException
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
            $resp = Client::post("/files/{$this->getName()}", $data);
            $this->mergeAfterSave($resp);
        } else {
            $key = static::genFileKey();
            $key = "{$key}." . pathinfo($this->getName(), PATHINFO_EXTENSION);
            $data["key"]    = $key;
            $data["__type"] = "File";
            $resp = Client::post("/fileTokens", $data);
            if (!isset($resp["token"])) {
                // adapt for S3, when there is no token
                $resp["token"] = null;
            }

            try {
                $uploader = SimpleUploader::createUploader($resp["provider"]);
                $uploader->initialize($resp["upload_url"], $resp["token"]);
                $uploader->upload($this->_source, $this->getMimeType(), $key);
            } catch (\Exception $ex) {
                $this->destroy();
                throw $ex;
            }
            forEach(array("upload_url", "token") as $k) {
                if (isset($resp[$k])) {
                    unset($resp[$k]);
                }
            }
            $this->mergeAfterSave($resp);
        }
    }

    /**
     * Fetch file object by id
     *
     * Note it fetches descriptive data from LeanCloud, but not file content.
     * The content should be fetched from file URL.
     *
     * @return File
     */
    public static function fetch($objectId) {
        $file = new File("");
        $resp = Client::get("/files/{$objectId}");
        $file->mergeAfterFetch($resp);
        return $file;
    }

    /**
     * Delete file on cloud
     *
     * @throws CloudException
     */
    public function destroy() {
        if (!$this->getObjectId()) {
            return false;
        }
        Client::delete("/files/{$this->getObjectId()}");
    }

    /**
     * Encode to JSON representation
     *
     * @return array
     */
    public function encode() {
        if (!$this->getObjectId()) {
            throw new \RuntimeException("Cannot serialize unsaved file.");
        }
        return array(
            "__type" => "File",
            "id"     => $this->getObjectId(),
            "name"   => $this->getName(),
            "url"    => $this->getUrl()
        );
    }
}

