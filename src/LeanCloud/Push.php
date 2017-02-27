<?php

namespace LeanCloud;

/**
 * Send Push notification to mobile devices
 */
class Push {
    /**
     * Notification data
     *
     * @var array
     */
    private $data;

    /**
     * Notification options
     *
     * @var array
     */
    private $options;

    /**
     * Initialize a Push notification
     *
     * @param array $data    Notification data
     * @param array $options Notification options
     * @see self::setData, self::setOption
     */
    public function __construct($data=array(), $options=array()) {
        $this->data = $data;
        $this->options = $options;
        $this->options["prod"] = Client::$isProduction ? "prod": "dev";
    }

    /**
     * Set notification data attributes
     *
     * Available attributes please see
     * https://leancloud.cn/docs/push_guide.html#%E6%8E%A8%E9%80%81%E6%B6%88%E6%81%AF
     *
     * @param string $key Attribute key
     * @param mixed  $val Attribute value
     * @return self
     */
    public function setData($key, $val) {
        $this->data[$key] = $val;
    }

    /**
     * Set general option for notificiation
     *
     * Available options please see
     * https://leancloud.cn/docs/push_guide.html#%E6%8E%A8%E9%80%81%E6%B6%88%E6%81%AF
     *
     * There are helper methods for setting most of options, use those
     * if possible. Use this when no helper present, e.g. to enable
     * "dev" environment in iOS:
     *
     * ```php
     * $push->setOption("prod", "dev");
     * ```
     * 
     * @param string $key Option key
     * @param mixed  $val Option value
     * @return self
     * @see self::setWhere, self::setChannels, self::setPushTime ...
     */
    public function setOption($key, $val) {
        $this->options[$key] = $val;
        return $this;
    }

    /**
     * Set target channels
     *
     * @param array $channels List of channel names
     * @return self
     * @see self::setOption()
     */
    public function setChannels($channels) {
        return $this->setOption("channels", $channels);
    }

    /**
     * Filter target devices by query
     *
     * The query must be over _Installation table.
     *
     * @param Query $query A query over _Installation
     * @return self
     * @see self::setOption()
     */
    public function setWhere(Query $query) {
        if ($query->getClassName() != "_Installation") {
            throw new \RuntimeException("Query must be over " .
                                        "_Installation table.");
        }
        return $this->setOption("where", $query);
    }

    /**
     * Schedule a time to send message
     *
     * @param DateTime $time Time to send message to clients
     * @return self
     * @see self::setOption()
     */
    public function setPushTime(\DateTime $time) {
        return $this->setOption("push_time", $time);
    }

    /**
     * Set expiration interval for message
     *
     * When client received message after the interval, it will not be
     * displayed to user.
     *
     * @param int $interval Number of seconds (from now) to expire message
     * @return self
     * @see self::setOption()
     */
    public function setExpirationInterval($interval) {
        return $this->setOption("expiration_interval", $interval);
    }

    /**
     * Set expiration time for message
     *
     * When client received message after the specified time, it will
     * not be displayed to user.
     *
     * @param DateTime $time Time to expire message
     * @return self
     * @see self::setOption()
     */
    public function setExpirationTime(\DateTime $time) {
        return $this->setOption("expiration_time", $time);
    }

    /**
     * Encode to JSON representation
     *
     * @return array
     */
    public function encode() {
        $out = $this->options;
        $out["data"] = $this->data;
        $expire = isset($this->options["expiration_time"]) ? $this->options["expiration_time"] : null;
        if (($expire instanceof \DateTime) ||
            ($expire instanceof \DateTimeImmutable)) {
            $out["expiration_time"] = Client::formatDate($expire);
        }
        $pushTime = isset($this->options["push_time"]) ? $this->options["push_time"] : null;
        if (($pushTime instanceof \DateTime) ||
            ($pushTime instanceof \DateTimeImmutable)){
            $out["push_time"] = Client::formatDate($pushTime);
        }
        if (isset($this->options["where"])) {
            $query = $this->options["where"]->encode();
            $out["where"] = json_decode($query["where"], true);
        }
        return $out;
    }

    /**
     * Send notification to LeanCloud
     *
     * @return array
     */
    public function send() {
        $out  = $this->encode();
        $resp = Client::post("/push", $out);
        return $resp;
    }
}
