<?php

namespace LeanCloud;

abstract class Region {
    const CN_N1 = 0;
    const US    = 1;
    const CN_E1 = 2;

    /**
     * Alias of `CN_N1`
     */
    const CN    = self::CN_N1;

    /**
     * Create region from name, such as `CN`, `CN_N1`.
     *
     * @param string $name
     */
    public static function fromName($name) {
        return constant(self::class . "::" . strtoupper($name));
    }
}

