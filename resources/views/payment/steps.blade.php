{{-- Fil d'étapes du paiement. $current : 1 à 4. --}}
<x-stepper :labels="['Formule', 'Pays et opérateur', 'Paiement', 'Vérification']" :current="$current" label="Étapes du paiement" />
