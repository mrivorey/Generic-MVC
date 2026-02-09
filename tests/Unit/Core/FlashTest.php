<?php

namespace Tests\Unit\Core;

use App\Core\Flash;
use Tests\TestCase;

class FlashTest extends TestCase
{
    public function testSetAndGetSingleMessage(): void
    {
        Flash::set('success', 'Item saved.');
        $messages = Flash::get('success');

        $this->assertSame(['Item saved.'], $messages);
    }

    public function testSetMultipleMessagesSameType(): void
    {
        Flash::set('error', 'First error.');
        Flash::set('error', 'Second error.');
        $messages = Flash::get('error');

        $this->assertSame(['First error.', 'Second error.'], $messages);
    }

    public function testGetClearsMessages(): void
    {
        Flash::set('info', 'Hello');
        Flash::get('info');

        $this->assertSame([], Flash::get('info'));
    }

    public function testGetReturnsEmptyForUnsetType(): void
    {
        $this->assertSame([], Flash::get('warning'));
    }

    public function testAllReturnsAndClearsAllMessages(): void
    {
        Flash::set('success', 'OK');
        Flash::set('error', 'Fail');
        $all = Flash::all();

        $this->assertArrayHasKey('success', $all);
        $this->assertArrayHasKey('error', $all);
        $this->assertSame(['OK'], $all['success']);
        $this->assertSame(['Fail'], $all['error']);

        // Should be cleared now
        $this->assertSame([], Flash::all());
    }

    public function testHasReturnsTrueWhenSet(): void
    {
        Flash::set('success', 'Yep');
        $this->assertTrue(Flash::has('success'));
    }

    public function testHasReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(Flash::has('success'));
    }

    public function testHasReturnsFalseAfterGet(): void
    {
        Flash::set('info', 'Gone soon');
        Flash::get('info');

        $this->assertFalse(Flash::has('info'));
    }

    public function testSetOldInputAndOld(): void
    {
        Flash::setOldInput(['username' => 'john', 'email' => 'j@x.com']);

        $this->assertSame('john', Flash::old('username'));
        $this->assertSame('j@x.com', Flash::old('email'));
    }

    public function testOldReturnsDefaultWhenNotSet(): void
    {
        $this->assertSame('', Flash::old('missing'));
        $this->assertSame('fallback', Flash::old('missing', 'fallback'));
    }

    public function testClearOldInput(): void
    {
        Flash::setOldInput(['name' => 'Test']);
        Flash::clearOldInput();

        $this->assertSame('', Flash::old('name'));
    }

    public function testMultipleTypesIndependent(): void
    {
        Flash::set('success', 'S1');
        Flash::set('error', 'E1');

        // Getting success shouldn't affect error
        Flash::get('success');

        $this->assertFalse(Flash::has('success'));
        $this->assertTrue(Flash::has('error'));
    }
}
