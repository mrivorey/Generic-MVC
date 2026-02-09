<?php

namespace Tests\Unit\Core;

use App\Core\Flash;
use App\Core\FormBuilder;
use App\Middleware\CsrfMiddleware;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure FormBuilder loads fresh validation errors
        FormBuilder::resetState();
    }

    public function testOpenGeneratesFormTag(): void
    {
        $html = FormBuilder::open(['action' => '/submit', 'method' => 'POST', 'class' => 'my-form']);

        $this->assertStringContainsString('<form method="POST"', $html);
        $this->assertStringContainsString('action="/submit"', $html);
        $this->assertStringContainsString('class="my-form"', $html);
    }

    public function testOpenIncludesCsrfForPost(): void
    {
        $html = FormBuilder::open(['method' => 'POST']);

        $this->assertStringContainsString('name="_csrf_token"', $html);
    }

    public function testOpenNoCsrfForGet(): void
    {
        $html = FormBuilder::open(['method' => 'GET']);

        $this->assertStringNotContainsString('_csrf_token', $html);
    }

    public function testOpenMethodSpoofingForPut(): void
    {
        $html = FormBuilder::open(['method' => 'PUT']);

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
    }

    public function testOpenMethodSpoofingForDelete(): void
    {
        $html = FormBuilder::open(['method' => 'DELETE']);

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
    }

    public function testCloseReturnsClosingTag(): void
    {
        $this->assertSame('</form>', FormBuilder::close());
    }

    public function testTextFieldGeneratesCorrectHtml(): void
    {
        $html = FormBuilder::text('username', ['label' => 'Username']);

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('Username', $html);
        $this->assertStringContainsString('form-control', $html);
    }

    public function testEmailFieldGeneratesCorrectHtml(): void
    {
        $html = FormBuilder::email('email');

        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('name="email"', $html);
    }

    public function testPasswordFieldGeneratesCorrectHtml(): void
    {
        $html = FormBuilder::password('password');

        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('name="password"', $html);
    }

    public function testPasswordNeverRepopulatesOldInput(): void
    {
        Flash::setOldInput(['password' => 'secret123']);
        FormBuilder::resetState();

        $html = FormBuilder::password('password');

        $this->assertStringContainsString('value=""', $html);
    }

    public function testHiddenField(): void
    {
        $html = FormBuilder::hidden('user_id', '42');

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="user_id"', $html);
        $this->assertStringContainsString('value="42"', $html);
    }

    public function testTextareaField(): void
    {
        $html = FormBuilder::textarea('bio', ['value' => 'Hello world', 'rows' => 5]);

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('name="bio"', $html);
        $this->assertStringContainsString('rows="5"', $html);
        $this->assertStringContainsString('Hello world', $html);
    }

    public function testSelectField(): void
    {
        $html = FormBuilder::select('role', ['admin' => 'Admin', 'editor' => 'Editor'], ['value' => 'editor']);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="role"', $html);
        $this->assertStringContainsString('value="editor" selected', $html);
    }

    public function testCheckboxField(): void
    {
        $html = FormBuilder::checkbox('agree', ['label' => 'I agree']);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="agree"', $html);
        $this->assertStringContainsString('I agree', $html);
    }

    public function testRadioField(): void
    {
        $html = FormBuilder::radio('color', 'red', ['label' => 'Red']);

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('name="color"', $html);
        $this->assertStringContainsString('value="red"', $html);
        $this->assertStringContainsString('Red', $html);
    }

    public function testSubmitButton(): void
    {
        $html = FormBuilder::submit('Save');

        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
    }

    public function testSubmitButtonCustomClass(): void
    {
        $html = FormBuilder::submit('Delete', ['class' => 'btn btn-danger']);

        $this->assertStringContainsString('btn btn-danger', $html);
    }

    public function testValuePrecedenceOldOverExplicit(): void
    {
        Flash::setOldInput(['name' => 'old-value']);
        FormBuilder::resetState();

        $html = FormBuilder::text('name', ['value' => 'explicit-value']);

        $this->assertStringContainsString('value="old-value"', $html);
    }

    public function testValidationErrorAddsInvalidClass(): void
    {
        $_SESSION['_validation_errors'] = ['email' => ['Email is required.']];
        FormBuilder::resetState();

        // open() triggers loading of validation errors from session
        FormBuilder::open();
        $html = FormBuilder::email('email');

        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('invalid-feedback', $html);
        $this->assertStringContainsString('Email is required.', $html);
    }

    public function testHasErrorReturnsCorrectBool(): void
    {
        $_SESSION['_validation_errors'] = ['name' => ['Name is required.']];
        FormBuilder::resetState();

        // Trigger loading of validation errors by opening a form
        FormBuilder::open();

        $this->assertTrue(FormBuilder::hasError('name'));
        $this->assertFalse(FormBuilder::hasError('email'));
    }

    public function testErrorReturnsMessage(): void
    {
        $_SESSION['_validation_errors'] = ['name' => ['Name is required.', 'Name too short.']];
        FormBuilder::resetState();

        FormBuilder::open();

        $this->assertSame('Name is required. Name too short.', FormBuilder::error('name'));
    }
}
