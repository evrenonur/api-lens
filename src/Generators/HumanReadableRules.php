<?php

namespace ApiLens\Generators;

use ApiLens\Models\Endpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Converts Laravel validation rules into human-readable descriptions.
 * Example: "required|string|max:255" → "Required text field, maximum 255 characters"
 *
 * This is a feature that laravel-request-docs completely lacks.
 */
class HumanReadableRules
{
    /**
     * Known rule translations.
     * @var array<string, string>
     */
    private const RULE_MAP = [
        'required'       => 'Required',
        'nullable'       => 'Optional (can be null)',
        'sometimes'      => 'Optional',
        'string'         => 'Text',
        'integer'        => 'Integer number',
        'numeric'        => 'Number',
        'boolean'        => 'True or False',
        'array'          => 'Array/List',
        'email'          => 'Valid email address',
        'url'            => 'Valid URL',
        'date'           => 'Valid date',
        'date_format'    => 'Date with format',
        'file'           => 'File upload',
        'image'          => 'Image file (jpeg, png, bmp, gif, svg, webp)',
        'mimes'          => 'Allowed file types',
        'mimetypes'      => 'Allowed MIME types',
        'json'           => 'Valid JSON string',
        'uuid'           => 'Valid UUID',
        'ip'             => 'Valid IP address',
        'ipv4'           => 'Valid IPv4 address',
        'ipv6'           => 'Valid IPv6 address',
        'alpha'          => 'Letters only',
        'alpha_num'      => 'Letters and numbers only',
        'alpha_dash'     => 'Letters, numbers, dashes, underscores',
        'confirmed'      => 'Must be confirmed (with _confirmation field)',
        'unique'         => 'Must be unique in database',
        'exists'         => 'Must exist in database',
        'in'             => 'Must be one of',
        'not_in'         => 'Must not be one of',
        'between'        => 'Between',
        'digits'         => 'Must have exact digit count',
        'digits_between' => 'Digit count between',
        'regex'          => 'Must match pattern',
        'timezone'       => 'Valid timezone',
        'active_url'     => 'Must be a reachable URL',
        'after'          => 'Date must be after',
        'before'         => 'Date must be before',
        'accepted'       => 'Must be accepted (yes, on, 1, true)',
        'prohibited'     => 'Prohibited field',
    ];

    /**
     * Generate human-readable descriptions for all endpoint rules.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function generate(Collection $endpoints): Collection
    {
        foreach ($endpoints as $endpoint) {
            $readableRules = [];

            foreach ($endpoint->getRules() as $attribute => $rules) {
                $ruleStr = is_array($rules) ? implode('|', $rules) : $rules;
                $readableRules[$attribute] = $this->translateRules($attribute, $ruleStr);
            }

            $endpoint->setHumanReadableRules($readableRules);
        }

        return $endpoints;
    }

    /**
     * Translate a rule string into a human-readable description.
     */
    public function translateRules(string $attribute, string $rulesString): string
    {
        $parts = array_filter(explode('|', $rulesString), fn($s) => $s !== '');
        $descriptions = [];

        foreach ($parts as $rule) {
            $description = $this->translateSingleRule(trim($rule));
            if ($description) {
                $descriptions[] = $description;
            }
        }

        if (empty($descriptions)) {
            return 'Any value';
        }

        return implode('. ', $descriptions);
    }

    /**
     * Translate a single validation rule.
     */
    private function translateSingleRule(string $rule): ?string
    {
        // Handle rules with parameters (e.g., max:255, min:1)
        if (Str::contains($rule, ':')) {
            return $this->translateParameterizedRule($rule);
        }

        // Direct mapping
        if (isset(self::RULE_MAP[$rule])) {
            return self::RULE_MAP[$rule];
        }

        // Class-based rules - try to get readable name
        if (class_exists($rule)) {
            $shortName = class_basename($rule);
            return 'Custom rule: ' . Str::headline($shortName);
        }

        return null;
    }

    /**
     * Translate rules that have parameters (e.g., max:255, in:a,b,c).
     */
    private function translateParameterizedRule(string $rule): ?string
    {
        [$ruleName, $parameter] = explode(':', $rule, 2);

        return match ($ruleName) {
            'max'            => "Maximum {$parameter} characters/items",
            'min'            => "Minimum {$parameter} characters/items",
            'size'           => "Exactly {$parameter} characters/items",
            'between'        => $this->translateBetween($parameter),
            'in'             => "Must be one of: " . str_replace(',', ', ', $parameter),
            'not_in'         => "Must not be: " . str_replace(',', ', ', $parameter),
            'digits'         => "Exactly {$parameter} digits",
            'digits_between' => "Between " . str_replace(',', ' and ', $parameter) . " digits",
            'mimes'          => "Allowed types: " . str_replace(',', ', ', $parameter),
            'mimetypes'      => "Allowed MIME: " . str_replace(',', ', ', $parameter),
            'date_format'    => "Date format: {$parameter}",
            'after'          => "Date must be after {$parameter}",
            'after_or_equal' => "Date must be on or after {$parameter}",
            'before'         => "Date must be before {$parameter}",
            'before_or_equal' => "Date must be on or before {$parameter}",
            'regex'          => "Must match pattern: {$parameter}",
            'exists'         => $this->translateExists($parameter),
            'unique'         => $this->translateUnique($parameter),
            'required_if'    => $this->translateRequiredIf($parameter),
            'required_unless' => "Required unless specific condition",
            'required_with'  => "Required when " . str_replace(',', ', ', $parameter) . " is present",
            'required_without' => "Required when " . str_replace(',', ', ', $parameter) . " is absent",
            'gt'             => "Greater than {$parameter}",
            'gte'            => "Greater than or equal to {$parameter}",
            'lt'             => "Less than {$parameter}",
            'lte'            => "Less than or equal to {$parameter}",
            'same'           => "Must match {$parameter}",
            'different'      => "Must differ from {$parameter}",
            'starts_with'    => "Must start with: " . str_replace(',', ', ', $parameter),
            'ends_with'      => "Must end with: " . str_replace(',', ', ', $parameter),
            default          => null,
        };
    }

    private function translateBetween(string $parameter): string
    {
        $parts = explode(',', $parameter);
        return "Between {$parts[0]} and " . ($parts[1] ?? '?');
    }

    private function translateExists(string $parameter): string
    {
        $parts = explode(',', $parameter);
        $table = $parts[0] ?? 'table';
        $column = $parts[1] ?? 'id';
        return "Must exist in {$table}.{$column}";
    }

    private function translateUnique(string $parameter): string
    {
        $parts = explode(',', $parameter);
        $table = $parts[0] ?? 'table';
        return "Must be unique in {$table}";
    }

    private function translateRequiredIf(string $parameter): string
    {
        $parts = explode(',', $parameter);
        $field = $parts[0] ?? 'field';
        $values = array_slice($parts, 1);
        $valuesStr = implode(', ', $values);
        return "Required when {$field} is {$valuesStr}";
    }
}
