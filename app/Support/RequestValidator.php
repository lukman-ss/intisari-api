<?php

declare(strict_types=1);

namespace App\Support;

use Lukman\Validation\ValidatorFactory;
use App\Exceptions\ApiValidationException;

class RequestValidator
{
    public static function validate(array $input, array $rules): array
    {
        $factory = new ValidatorFactory();
        $validator = $factory->make($input, $rules);

        if ($validator->fails()) {
            throw new ApiValidationException($validator->errors()->toArray());
        }

        $validated = [];
        foreach ($rules as $field => $rule) {
            if (array_key_exists($field, $input)) {
                $validated[$field] = $input[$field];
            }
        }
        
        return $validated;
    }
}
