<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\IconStyle;
use Illuminate\View\View;

/**
 * « Aperçu des icônes » : les principales icônes du site dans les trois styles de pastille,
 * pour choisir celui de config/platform.php (brand.icon_style).
 */
class IconPreviewController extends Controller
{
    /**
     * Icônes principales : nom => [titre, texte], comme dans les cartes du site.
     */
    private const SAMPLES = [
        'mic' => ['Parler avec le professeur', 'La séance est orale du début à la fin.'],
        'check-circle' => ['Corrections immédiates', 'Vos erreurs corrigées, puis revues.'],
        'pin' => ['Situations de chez nous', 'Grand Marché, zémidjan, frontière d\'Aflao.'],
        'calendar' => ['À votre rythme', 'Vous choisissez vos jours de séance.'],
        'user-plus' => ['Créer mon compte', 'Nom et numéro de téléphone.'],
        'wallet' => ['Mobile Money', 'Paiement depuis votre téléphone.'],
        'mobile' => ['Envoyer la capture', 'La confirmation de votre opérateur.'],
        'download' => ['Installer l\'application', 'Sur l\'écran d\'accueil du téléphone.'],
        'award' => ['Attestation de niveau', 'Après le bilan oral réussi.'],
        'chart' => ['Ma progression', 'Le niveau, unité après unité.'],
        'headphones' => ['Matériel', 'Un téléphone et des écouteurs.'],
        'bubble' => ['Aide', 'Une question ? WhatsApp.'],
    ];

    public function __invoke(): View
    {
        return view('admin.icons', [
            'styles' => IconStyle::STYLES,
            'current' => IconStyle::current(),
            'samples' => self::SAMPLES,
        ]);
    }
}
