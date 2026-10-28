<?php

namespace App\Support\Ambassadors;

use RuntimeException;

/**
 * Action refusée sur un ambassadeur (plus de place, plus de mois gratuit, accès payé en cours…) : le message,
 * en français, est affiché tel quel à l'administrateur.
 */
final class AmbassadorRefused extends RuntimeException {}
