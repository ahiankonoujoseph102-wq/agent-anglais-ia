<?php

namespace App\Http\Controllers;

use App\Support\WhatsApp;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(): View
    {
        return view('contact', [
            'contact' => config('platform.contact'),
            'whatsappUrl' => WhatsApp::url(config('platform.contact.whatsapp'), 'Bonjour '.config('platform.name').', '),
        ]);
    }
}
