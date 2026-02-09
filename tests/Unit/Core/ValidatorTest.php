<?php

namespace Tests\Unit\Core;

use App\Core\Validator;
use Tests\TestCase;

class ValidatorTest extends TestCase
{
    public function testRequiredPasses(): void
    {
        $v = Validator::make(['name' => 'John'], ['name' => 'required'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testRequiredFailsNull(): void
    {
        $v = Validator::make([], ['name' => 'required'])->validate();
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testRequiredFailsEmptyString(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required'])->validate();
        $this->assertTrue($v->fails());
    }

    public function testRequiredFailsEmptyArray(): void
    {
        $v = Validator::make(['tags' => []], ['tags' => 'required'])->validate();
        $this->assertTrue($v->fails());
    }

    public function testStringPasses(): void
    {
        $v = Validator::make(['name' => 'hello'], ['name' => 'string'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testStringPassesEmpty(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'string'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testEmailValid(): void
    {
        $v = Validator::make(['email' => 'user@example.com'], ['email' => 'email'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testEmailInvalid(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email'])->validate();
        $this->assertTrue($v->fails());
    }

    public function testEmailSkipsEmpty(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'email'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testMinPasses(): void
    {
        $v = Validator::make(['pw' => 'abcdef'], ['pw' => 'min:6'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testMinFails(): void
    {
        $v = Validator::make(['pw' => 'abc'], ['pw' => 'min:6'])->validate();
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('at least 6', $v->errors()['pw'][0]);
    }

    public function testMaxPasses(): void
    {
        $v = Validator::make(['name' => 'hi'], ['name' => 'max:10'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testMaxFails(): void
    {
        $v = Validator::make(['name' => 'this is way too long'], ['name' => 'max:5'])->validate();
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('must not exceed 5', $v->errors()['name'][0]);
    }

    public function testNumericPasses(): void
    {
        $v = Validator::make(['age' => '42'], ['age' => 'numeric'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testNumericPassesFloat(): void
    {
        $v = Validator::make(['price' => '9.99'], ['price' => 'numeric'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testNumericFails(): void
    {
        $v = Validator::make(['age' => 'abc'], ['age' => 'numeric'])->validate();
        $this->assertTrue($v->fails());
    }

    public function testIntegerPasses(): void
    {
        $v = Validator::make(['count' => '10'], ['count' => 'integer'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testIntegerFails(): void
    {
        $v = Validator::make(['count' => '3.5'], ['count' => 'integer'])->validate();
        $this->assertTrue($v->fails());
    }

    public function testConfirmedMatch(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        )->validate();
        $this->assertFalse($v->fails());
    }

    public function testConfirmedMismatch(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'wrong'],
            ['password' => 'confirmed']
        )->validate();
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('confirmation does not match', $v->errors()['password'][0]);
    }

    public function testInValid(): void
    {
        $v = Validator::make(['status' => 'active'], ['status' => 'in:active,inactive'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testInInvalid(): void
    {
        $v = Validator::make(['status' => 'deleted'], ['status' => 'in:active,inactive'])->validate();
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('must be one of', $v->errors()['status'][0]);
    }

    public function testMultipleRulesOnOneField(): void
    {
        $v = Validator::make(['pw' => 'ab'], ['pw' => 'required|string|min:8'])->validate();
        $this->assertTrue($v->fails());
        $this->assertCount(1, $v->errors()['pw']); // only min fails
    }

    public function testMultipleFields(): void
    {
        $v = Validator::make(
            ['name' => '', 'email' => 'bad'],
            ['name' => 'required', 'email' => 'required|email']
        )->validate();

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('email', $v->errors());
    }

    public function testValidatedReturnsCleanData(): void
    {
        $v = Validator::make(
            ['name' => 'John', 'extra' => 'ignored'],
            ['name' => 'required|string']
        )->validate();

        $validated = $v->validated();
        $this->assertSame(['name' => 'John'], $validated);
    }

    public function testFailsReturnsFalseWhenValid(): void
    {
        $v = Validator::make(['name' => 'ok'], ['name' => 'required'])->validate();
        $this->assertFalse($v->fails());
    }

    public function testErrorsStructure(): void
    {
        $v = Validator::make([], ['name' => 'required'])->validate();
        $errors = $v->errors();

        $this->assertIsArray($errors);
        $this->assertIsArray($errors['name']);
        $this->assertIsString($errors['name'][0]);
    }

    public function testFieldLabelHumanizesUnderscores(): void
    {
        $v = Validator::make([], ['first_name' => 'required'])->validate();
        $this->assertStringContainsString('First name', $v->errors()['first_name'][0]);
    }

    public function testFieldLabelHumanizesDashes(): void
    {
        $v = Validator::make([], ['first-name' => 'required'])->validate();
        $this->assertStringContainsString('First name', $v->errors()['first-name'][0]);
    }
}
