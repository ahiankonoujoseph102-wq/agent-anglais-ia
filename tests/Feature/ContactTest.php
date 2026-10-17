<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use ConfiguresPayments;
    use RefreshDatabase;

    public function test_contact_page_and_footer_show_the_details_entered_in_the_settings(): void
    {
        // Coordonnées FICTIVES, saisies comme dans la page « Réglages ».
        SiteSettings::save([
            'contact.address' => 'Rue Fictive, Lomé, Togo',
            'contact.phones' => "+228 90 00 00 01\n+228 90 00 00 02",
            'contact.email' => 'contact@example.test',
            'contact.whatsapp' => '+228 90 00 00 99',
        ]);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Rue Fictive, Lomé, Togo')
            ->assertSee('Du lundi au samedi, de 8 h à 20 h')
            ->assertSee('+228 90 00 00 01')
            ->assertSee('+228 90 00 00 02')
            ->assertSee('contact@example.test')
            ->assertSee('https://wa.me/22890000099', false);

        $this->get('/')->assertSee('Rue Fictive, Lomé, Togo')->assertSee(route('contact'));
    }

    public function test_without_settings_the_default_details_are_shown(): void
    {
        $contact = config('platform.contact');
        $page = $this->get('/contact')->assertOk()
            ->assertSee($contact['address'])
            ->assertSee($contact['email'])
            ->assertSee('Écrire sur WhatsApp')
            ->assertSee('https://wa.me/'.preg_replace('/\D+/', '', $contact['whatsapp']), false);

        foreach ($contact['phones'] as $phone) {
            $page->assertSee($phone);
        }

        $this->get('/')->assertSee($contact['address'])->assertSee('mailto:'.$contact['email'], false);
    }

    public function test_without_settings_nor_defaults_the_pages_hide_empty_details(): void
    {
        $this->withoutDefaults();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Du lundi au samedi, de 8 h à 20 h')
            ->assertDontSee('Bureau')
            ->assertDontSee('Écrire sur WhatsApp')
            ->assertDontSee('https://wa.me/', false);

        $this->get('/')->assertOk()->assertDontSee('mailto:', false);
    }

    public function test_admin_enters_payment_numbers_and_contact_details(): void
    {
        $admin = User::factory()->admin()->create();
        $default = config('payment.countries.TG.operators.mixx.number');

        $this->actingAs(User::factory()->create())->get('/admin/reglages')->assertForbidden();

        // Rien de saisi : chaque champ annonce la valeur par défaut utilisée.
        $this->actingAs($admin)->get('/admin/reglages')
            ->assertOk()
            ->assertSee('Mixx by Yas')
            ->assertSee('Flooz')
            ->assertSee('Orange Money')
            ->assertSee('Wave')
            ->assertSee('MTN')
            ->assertSee('Moov')
            ->assertSee('Celtiis')
            ->assertSee('Rien n\'est obligatoire', false)
            ->assertSee('Champ vide : la valeur par défaut est utilisée : '.$default)
            ->assertSee('Champ vide : la valeur par défaut est utilisée : '.config('payment.countries.TG.operators.mixx.holder'))
            ->assertSee('Champ vide : la valeur par défaut est utilisée : '.config('platform.contact.email'));
        $this->assertSame($default, SiteSettings::operatorNumber('TG', 'mixx'));

        $this->actingAs($admin)->put('/admin/reglages', [
            'operators' => [
                'TG' => ['mixx' => ['number' => '+228 90 00 00 11', 'holder' => 'Titulaire Fictif']],
                'BF' => ['wave' => ['number' => 'pas un numéro', 'holder' => '']],
            ],
            'contact' => ['whatsapp' => '+228 90 00 00 99'],
        ])->assertSessionHasErrors('operators.BF.wave.number');
        $this->assertSame($default, SiteSettings::operatorNumber('TG', 'mixx'));

        // Une valeur saisie remplace la valeur par défaut.
        $this->actingAs($admin)->put('/admin/reglages', [
            'operators' => ['TG' => ['mixx' => ['number' => '+228 90 00 00 11', 'holder' => 'Titulaire Fictif']]],
            'contact' => ['whatsapp' => '+228 90 00 00 99', 'phones' => "+228 90 00 00 01\n+228 90 00 00 02"],
        ])->assertRedirect()->assertSessionHas('status', 'Réglages enregistrés.');

        $this->assertSame('+228 90 00 00 11', SiteSettings::operatorNumber('TG', 'mixx'));
        $this->assertSame('Titulaire Fictif', SiteSettings::operatorHolder('TG', 'mixx'));
        $this->assertSame(['+228 90 00 00 01', '+228 90 00 00 02'], SiteSettings::contact()['phones']);
        $this->assertSame(config('platform.contact.email'), SiteSettings::contact()['email']);
        $this->actingAs($admin)->get('/admin/reglages')
            ->assertSee('value="+228 90 00 00 11"', false)
            ->assertSee('Valeur par défaut, utilisée si vous videz ce champ : '.$default);
        $this->get('/contact')->assertSee('+228 90 00 00 02')->assertSee('https://wa.me/22890000099', false);

        // Vider un champ revient à la valeur par défaut.
        $this->actingAs($admin)->put('/admin/reglages', [
            'operators' => ['TG' => ['mixx' => ['number' => '', 'holder' => '']]],
            'contact' => ['whatsapp' => ''],
        ])->assertRedirect();
        $this->assertNull(SiteSettings::saved(SiteSettings::operatorNumberKey('TG', 'mixx')));
        $this->assertSame($default, SiteSettings::operatorNumber('TG', 'mixx'));
        $this->assertSame(config('platform.contact.whatsapp'), SiteSettings::whatsapp());
        $this->assertSame(config('platform.contact.phones'), SiteSettings::contact()['phones']);
    }
}
