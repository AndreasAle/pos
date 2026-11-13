<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_switch_route_sets_a_year_long_cookie_and_goes_back(): void
    {
        $res = $this->from('/login')->get('/lang/en');

        $res->assertRedirect('/login');
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === 'yoka_locale');
        $this->assertNotNull($cookie);
        $this->assertGreaterThan(time() + 86400 * 360, $cookie->getExpiresTime());
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->get('/lang/xx')->assertNotFound();
    }

    public function test_default_language_is_indonesian_and_english_translates_the_page(): void
    {
        $this->get('/login')->assertSee('Masuk ke Akun Anda');

        $this->withCookie('yoka_locale', 'en')->get('/login')
            ->assertSee('Sign in to Your Account')
            ->assertDontSee('Masuk ke Akun Anda');
    }

    public function test_language_switch_is_shown_on_the_login_page(): void
    {
        $this->get('/login')->assertSee('hreflang="en"', false)->assertSee('hreflang="id"', false);
    }

    public function test_indonesian_validation_messages_are_used_by_default(): void
    {
        app()->setLocale('id');
        $v = validator(['name' => ''], ['name' => 'required']);

        $this->assertSame('nama wajib diisi.', $v->errors()->first('name'));
    }

    public function test_every_dictionary_value_is_a_non_empty_string(): void
    {
        $dict = json_decode(file_get_contents(lang_path('en.json')), true);

        $this->assertIsArray($dict);
        $this->assertGreaterThan(1000, count($dict));
        foreach ($dict as $from => $to) {
            $this->assertIsString($to);
            $this->assertNotSame('', trim($to), "Terjemahan kosong untuk: {$from}");
        }
    }
}
