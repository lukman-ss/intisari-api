<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\RequestValidator;
use App\Exceptions\ApiValidationException;

class RequestValidatorTest extends TestCase
{
    public function test_validates_successfully_and_returns_only_validated_data(): void
    {
        $input = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'extra_field' => 'should be ignored'
        ];
        
        $rules = [
            'name' => ['required', 'min:3', 'max:50'],
            'email' => ['required', 'email'],
            'age' => ['required'] // Assuming basic rule for age
        ];
        
        $validated = RequestValidator::validate($input, $rules);
        
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayHasKey('age', $validated);
        $this->assertArrayNotHasKey('extra_field', $validated);
        
        $this->assertSame('John Doe', $validated['name']);
    }

    public function test_throws_exception_on_missing_required_field(): void
    {
        $this->expectException(ApiValidationException::class);
        
        RequestValidator::validate(
            ['email' => 'john@example.com'],
            ['name' => ['required']]
        );
    }

    public function test_throws_exception_on_invalid_email(): void
    {
        $this->expectException(ApiValidationException::class);
        
        RequestValidator::validate(
            ['email' => 'not-an-email'],
            ['email' => ['required', 'email']]
        );
    }

    public function test_throws_exception_on_min_length_violation(): void
    {
        $this->expectException(ApiValidationException::class);
        
        RequestValidator::validate(
            ['name' => 'Jo'],
            ['name' => ['required', 'min:3']]
        );
    }

    public function test_throws_exception_on_max_length_violation(): void
    {
        $this->expectException(ApiValidationException::class);
        
        RequestValidator::validate(
            ['name' => 'This name is way too long to be valid according to our rules'],
            ['name' => ['required', 'max:10']]
        );
    }
    
    public function test_exception_contains_error_messages(): void
    {
        try {
            RequestValidator::validate(
                [],
                ['name' => ['required']]
            );
            $this->fail('Should have thrown ApiValidationException');
        } catch (ApiValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertIsArray($errors['name']); // Validation component usually returns array of messages per field
        }
    }
}
