<?php

namespace LeanCloud;

abstract class Region {
    const CN_N1 = 0;
    const US    = 1;
    const CN_E1 = 2;
    const CN    = Region::CN_N1;

    public static function fromName($name) {
        return constant(self::class . "::" . $name);
    }
}

