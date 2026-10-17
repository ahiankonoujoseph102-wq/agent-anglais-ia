<?php

namespace App\Http\Controllers;

use App\Support\SiteSettings;
use App\Support\WhatsApp;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(): View
    {
        return view('contact', [
            'contact' => SiteSettings::contact(),
            'whatsappUrl' => WhatsApp::url(SiteSettings::whatsapp(), 'Bonjour '.config('platform.name').', '),
        ]);
    }
}
