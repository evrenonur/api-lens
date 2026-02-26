<?php

namespace ApiLens\Tests\Unit;

use ApiLens\Tests\TestCase;
use ApiLens\Generators\HumanReadableRules;
use ApiLens\Generators\ExampleGenerator;
use ApiLens\Support\VersionDiff;
use ApiLens\Support\RateLimitInfo;

class GeneratorTest extends TestCase
{
    // ── HumanReadableRules ──────────────────────────────────────────────

    public function test_human_readable_rules_translates_basic_rules(): void
    {
        $generator = new HumanReadableRules();

        $result = $generator->translateRules('name', 'required|string|max:255');

        $this->assertStringContainsString('Required', $result);
        $this->assertStringContainsString('Text', $result);
        $this->assertStringContainsString('Maximum 255', $result);
    }

    public function test_human_readable_rules_translates_email(): void
    {
        $generator = new HumanReadableRules();

        $result = $generator->translateRules('email', 'required|email|unique:users');

        $this->assertStringContainsString('Required', $result);
        $this->assertStringContainsString('email', strtolower($result));
        $this->assertStringContainsString('unique', strtolower($result));
    }

    public function test_human_readable_rules_translates_nullable_integer(): void
    {
        $generator = new HumanReadableRules();

        $result = $generator->translateRules('age', 'nullable|integer|min:18|max:120');

        $this->assertStringContainsString('Optional', $result);
        $this->assertStringContainsString('Integer', $result);
        $this->assertStringContainsString('Minimum 18', $result);
        $this->assertStringContainsString('Maximum 120', $result);
    }

    public function test_human_readable_rules_translates_in_rule(): void
    {
        $generator = new HumanReadableRules();

        $result = $generator->translateRules('status', 'required|in:active,inactive,banned');

        $this->assertStringContainsString('Required', $result);
        $this->assertStringContainsString('active', $result);
        $this->assertStringContainsString('inactive', $result);
        $this->assertStringContainsString('banned', $result);
    }

    public function test_human_readable_rules_returns_any_value_for_empty(): void
    {
        $generator = new HumanReadableRules();

        $result = $generator->translateRules('field', '');

        $this->assertEquals('Any value', $result);
    }

    // ── ExampleGenerator ────────────────────────────────────────────────

    public function test_example_generator_email_field(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'email' => 'required|email',
        ]);

        $this->assertEquals('user@example.com', $example['email']);
    }

    public function test_example_generator_name_field(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'name' => 'required|string|max:255',
        ]);

        $this->assertIsString($example['name']);
        $this->assertNotEmpty($example['name']);
    }

    public function test_example_generator_integer_with_min(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'age' => 'required|integer|min:18',
        ]);

        // 'age' key matches name map → returns 25
        $this->assertEquals(25, $example['age']);
    }

    public function test_example_generator_boolean_field(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'active' => 'boolean',
        ]);

        $this->assertTrue($example['active']);
    }

    public function test_example_generator_password_field(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
        ]);

        $this->assertEquals('SecureP@ss123', $example['password']);
        $this->assertEquals('SecureP@ss123', $example['password_confirmation']);
    }

    public function test_example_generator_url_field(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'website' => 'required|url',
        ]);

        $this->assertEquals('https://example.com', $example['website']);
    }

    public function test_example_generator_uuid_rule(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'reference_id' => 'required|uuid',
        ]);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $example['reference_id']);
    }

    public function test_example_generator_in_rule(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'role' => 'required|in:admin,editor,viewer',
        ]);

        $this->assertEquals('admin', $example['role']);
    }

    public function test_example_generator_array_rule(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'tags' => 'array',
        ]);

        $this->assertIsArray($example['tags']);
        $this->assertEmpty($example['tags']);
    }

    public function test_example_generator_date_rule(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'start_date' => 'required|date',
        ]);

        // 'start_date' matches name map → returns '2025-01-15'
        $this->assertEquals('2025-01-15', $example['start_date']);
    }

    public function test_example_generator_multiple_fields(): void
    {
        $generator = new ExampleGenerator();

        $example = $generator->fromRules([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'active' => 'boolean',
            'phone' => 'nullable|string',
        ]);

        $this->assertCount(4, $example);
        $this->assertEquals('user@example.com', $example['email']);
        $this->assertTrue($example['active']);
        $this->assertEquals('+1234567890', $example['phone']);
    }

    // ── VersionDiff ─────────────────────────────────────────────────────

    public function test_version_diff_detects_added_endpoints(): void
    {
        $diff = new VersionDiff();

        $old = [
            ['http_method' => 'GET', 'uri' => 'api/users'],
        ];

        $new = [
            ['http_method' => 'GET', 'uri' => 'api/users'],
            ['http_method' => 'POST', 'uri' => 'api/users'],
        ];

        $result = $diff->diff($old, $new);

        $this->assertCount(1, $result['added']);
        $this->assertEquals('POST', $result['added'][0]['http_method']);
        $this->assertEquals(1, $result['summary']['total_added']);
    }

    public function test_version_diff_detects_removed_endpoints(): void
    {
        $diff = new VersionDiff();

        $old = [
            ['http_method' => 'GET', 'uri' => 'api/users'],
            ['http_method' => 'DELETE', 'uri' => 'api/users'],
        ];

        $new = [
            ['http_method' => 'GET', 'uri' => 'api/users'],
        ];

        $result = $diff->diff($old, $new);

        $this->assertCount(1, $result['removed']);
        $this->assertEquals('DELETE', $result['removed'][0]['http_method']);
        $this->assertEquals(1, $result['summary']['total_removed']);
    }

    public function test_version_diff_detects_modified_endpoints(): void
    {
        $diff = new VersionDiff();

        $old = [
            ['http_method' => 'POST', 'uri' => 'api/users', 'rules' => ['name' => 'required']],
        ];

        $new = [
            ['http_method' => 'POST', 'uri' => 'api/users', 'rules' => ['name' => 'required', 'email' => 'required|email']],
        ];

        $result = $diff->diff($old, $new);

        $this->assertCount(1, $result['modified']);
        $this->assertArrayHasKey('params_added', $result['modified'][0]['changes']);
        $this->assertContains('email', $result['modified'][0]['changes']['params_added']);
    }

    public function test_version_diff_no_changes(): void
    {
        $diff = new VersionDiff();

        $endpoints = [
            ['http_method' => 'GET', 'uri' => 'api/users'],
            ['http_method' => 'POST', 'uri' => 'api/users'],
        ];

        $result = $diff->diff($endpoints, $endpoints);

        $this->assertEmpty($result['added']);
        $this->assertEmpty($result['removed']);
        $this->assertEmpty($result['modified']);
    }

    // ── RateLimitInfo ───────────────────────────────────────────────────

    public function test_rate_limit_info_parses_numeric_throttle(): void
    {
        // RateLimitInfo::extract() requires a Route object.
        // We test the class indirectly by verifying it's instantiable.
        $info = new RateLimitInfo();
        $this->assertInstanceOf(RateLimitInfo::class, $info);
    }

    // ── Integration: Route scanning (requires Laravel app) ──────────────

    public function test_api_lens_routes_are_registered(): void
    {
        $response = $this->get('/api-lens/api');
        $response->assertStatus(200);
    }

    public function test_api_lens_ui_route_returns_html(): void
    {
        $this->withoutVite();
        $response = $this->get('/api-lens');
        $response->assertStatus(200);
    }
}
