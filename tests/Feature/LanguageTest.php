<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Support\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $locale): User
    {
        return User::create(['name' => 'Test Buyer', 'email' => $locale.'@example.test', 'password' => 'secret-Pass-123', 'role' => 'buyer', 'status' => 'active', 'locale' => $locale, 'email_verified_at' => now()])->fresh();
    }

    public function test_a_page_is_shown_in_the_language_the_user_chose(): void
    {
        $this->actingAs($this->person('az'))->get(route('settings'))
            ->assertOk()->assertSee('Profili yadda saxla')->assertDontSee('Save profile');

        $this->actingAs($this->person('ru'))->get(route('settings'))
            ->assertOk()->assertSee('Сохранить профиль')->assertDontSee('Save profile');

        $this->actingAs($this->person('en'))->get(route('settings'))
            ->assertOk()->assertSee('Save profile');
    }

    public function test_what_a_person_typed_is_never_translated(): void
    {
        // A supplier whose name happens to be an interface word, inside a text area and a script.
        $html = '<p>Close</p><textarea placeholder="Reason">Close</textarea><script>var label = "<b>Close</b>";</script><input value="Close">';
        $out = Translator::html($html, 'az');

        $this->assertStringContainsString('<p>Bağla</p>', $out);
        $this->assertStringContainsString('placeholder="Səbəb">Close</textarea>', $out);
        $this->assertStringContainsString('var label = "<b>Close</b>";', $out);
        $this->assertStringContainsString('<input value="Close">', $out);

        Supplier::create(['name' => 'Northwind Trading 77', 'status' => 'active']);
        $this->actingAs($this->person('az'))->get(route('suppliers.index'))->assertOk()->assertSee('Northwind Trading 77');
    }

    public function test_sentences_with_changing_parts_and_marks_are_translated(): void
    {
        $this->assertSame('12 təchizatçı', Translator::text('12 suppliers', 'az'));
        $this->assertSame('Son tarix üzrə sırala', Translator::text('Sort by deadline', 'az'));
        $this->assertSame('1-6 из 6', Translator::text('1-6 of 6', 'ru'));
        $this->assertSame('3 дек', Translator::text('3 Dec', 'ru'));
        $this->assertSame('Səbəb:', Translator::text('Reason:', 'az'));
        $this->assertSame('RFQ-2026-0001', Translator::text('RFQ-2026-0001', 'az'));
        $this->assertSame('Save profile', Translator::text('Save profile', 'en'));
        $this->assertSame('<b> Dəvətlər və təkliflər </b>', Translator::html('<b> Invitations &amp; quotations </b>', 'az'));
    }

    public function test_livewire_updates_stay_in_the_chosen_language(): void
    {
        app()->setLocale('az');
        $payload = ['components' => [['snapshot' => '{"data":{"title":"Status guide"}}', 'effects' => ['html' => '<div wire:id="x"><button title="Close">Status guide</button></div>', 'returns' => []]]]];

        $response = (new \App\Http\Middleware\TranslatePage)->handle(\Illuminate\Http\Request::create('/livewire/update', 'POST'), fn () => response()->json($payload));
        $data = json_decode($response->getContent(), true);

        $this->assertSame('<div wire:id="x"><button title="Bağla">Status bələdçisi</button></div>', $data['components'][0]['effects']['html']);
        // The component state is passed back untouched, so its checksum stays valid.
        $this->assertSame($payload['components'][0]['snapshot'], $data['components'][0]['snapshot']);
    }

    public function test_a_visitor_can_read_the_landing_page_in_another_language(): void
    {
        $this->get(route('landing'))->assertOk()->assertSee('every purchase on one accountable record.');
        $this->get(route('landing', ['lang' => 'ru']))->assertOk()->assertSee('каждая закупка в одной подотчётной записи.');
        $this->get(route('landing'))->assertOk()->assertSee('Войти');
        $this->get(route('landing', ['lang' => 'en']))->assertOk()->assertSee('Sign in');
    }

    public function test_every_dictionary_entry_has_both_languages(): void
    {
        $az = require lang_path('ui/az.php');
        $ru = require lang_path('ui/ru.php');

        $this->assertGreaterThan(1300, count($az));
        $this->assertSame(array_keys($az), array_keys($ru));
        foreach ([$az, $ru] as $words) {
            foreach ($words as $key => $value) {
                $this->assertNotSame('', trim($value), 'Empty translation for: '.$key);
                $this->assertStringNotContainsString("\u{2013}", $value);
            }
        }
    }
}
