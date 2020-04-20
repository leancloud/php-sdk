<?php
namespace LeanCloud;


/**
 * LeanObject save option builder
 */
class SaveOption {

    /**
     * Fetch object when save if set to true
     *
     * @var bool
     */
    public $fetchWhenSave;

    /**
     * Update object only when where query matches
     *
     * @var Query
     */
    public $where;

    /**
     * Encode a save option as array
     *
     * @return array
     */
    public function encode() {
        $params = array();
        if (!is_null($this->fetchWhenSave)) {
            $params["fetchWhenSave"] = $this->fetchWhenSave ? true : false;
        }
        if (!is_null($this->where)) {
            if ($this->where instanceof Query) {
                $out = $this->where->encode();
                $params["where"] = $out["where"];
            } else {
                throw new \RuntimeException("where of SaveOption must be Query object.");
            }
        }
        return $params;
    }
}
