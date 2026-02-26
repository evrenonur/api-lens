<?php

namespace ApiLens\Generators;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExampleGenerator
{
    /**
     * Generate example request body from validation rules.
     */
    public function fromRules(array $rules): array
    {
        $example = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $example[$field] = $this->generateValue($field, $ruleList);
        }

        return $example;
    }

    /**
     * Generate example from a Factory if available.
     */
    public function fromFactory(string $modelClass): ?array
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        try {
            if (method_exists($modelClass, 'factory')) {
                $factory = $modelClass::factory();
                if ($factory instanceof Factory) {
                    return $factory->definition();
                }
            }
        } catch (\Throwable) {
            // Factory not configured
        }

        return null;
    }

    /**
     * Generate a sample value based on field name and rules.
     */
    protected function generateValue(string $field, array $rules): mixed
    {
        $ruleString = implode('|', array_map('strval', $rules));
        $fieldLower = strtolower($field);

        // Smart guesses based on field name
        $nameMap = [
            'email' => 'user@example.com',
            'password' => 'SecureP@ss123',
            'password_confirmation' => 'SecureP@ss123',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'address' => '123 Main Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'country' => 'US',
            'url' => 'https://example.com',
            'website' => 'https://example.com',
            'title' => 'Sample Title',
            'description' => 'This is a sample description.',
            'content' => 'Lorem ipsum dolor sit amet.',
            'body' => 'Lorem ipsum dolor sit amet.',
            'status' => 'active',
            'type' => 'default',
            'age' => 25,
            'price' => 29.99,
            'amount' => 100.00,
            'quantity' => 1,
            'avatar' => 'https://example.com/avatar.jpg',
            'image' => 'https://example.com/image.jpg',
            'token' => 'abc123def456',
            'date' => '2025-01-15',
            'start_date' => '2025-01-15',
            'end_date' => '2025-12-31',
        ];

        foreach ($nameMap as $key => $value) {
            if (str_contains($fieldLower, $key)) {
                return $value;
            }
        }

        // Rule-based value generation
        if (str_contains($ruleString, 'boolean')) {
            return true;
        }

        if (str_contains($ruleString, 'integer') || str_contains($ruleString, 'numeric')) {
            // Check for min/max
            if (preg_match('/min:(\d+)/', $ruleString, $matches)) {
                return (int)$matches[1];
            }
            return 1;
        }

        if (str_contains($ruleString, 'array')) {
            return [];
        }

        if (str_contains($ruleString, 'date')) {
            return '2025-01-15';
        }

        if (str_contains($ruleString, 'email')) {
            return 'user@example.com';
        }

        if (str_contains($ruleString, 'url')) {
            return 'https://example.com';
        }

        if (str_contains($ruleString, 'uuid')) {
            return '550e8400-e29b-41d4-a716-446655440000';
        }

        if (str_contains($ruleString, 'ip')) {
            return '127.0.0.1';
        }

        if (str_contains($ruleString, 'json')) {
            return '{}';
        }

        // Check for 'in:' rule
        if (preg_match('/in:([^|]+)/', $ruleString, $matches)) {
            $options = explode(',', $matches[1]);
            return $options[0] ?? 'value';
        }

        // Default string
        if (str_contains($ruleString, 'string') || str_contains($ruleString, 'max:') || str_contains($ruleString, 'min:')) {
            return 'sample_value';
        }

        // File type
        if (str_contains($ruleString, 'file') || str_contains($ruleString, 'image') || str_contains($ruleString, 'mimes:')) {
            return '(binary file)';
        }

        return 'value';
    }
}
