<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait EncryptsAttributes
{
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->encrypted ?? [], true) && $value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encrypted ?? [], true) && $value !== null) {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }
}
