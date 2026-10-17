<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Plan;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Offre d'un accès gratuit et limité dans le temps (réservée aux administrateurs : route « admin »).
 */
class OfferAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $max = (int) config('platform.granted_access.max_days');

        return [
            'plan' => ['required', 'string', Rule::in(array_keys(Plan::all()))],
            'duration' => ['required', 'string', Rule::in([...array_map('strval', config('platform.granted_access.presets')), 'custom'])],
            'custom_days' => ['nullable', 'required_if:duration,custom', 'integer', 'min:1', 'max:'.$max],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => 'Choisissez une formule.',
            'plan.in' => 'Cette formule n\'existe pas.',
            'duration.required' => 'Choisissez une durée.',
            'duration.in' => 'Cette durée n\'est pas proposée.',
            'custom_days.required_if' => 'Indiquez le nombre de jours.',
            'custom_days.integer' => 'Le nombre de jours doit être un nombre entier.',
            'custom_days.min' => 'Offrez au moins 1 jour.',
            'custom_days.max' => 'Un accès offert dure :max jours au plus.',
            'note.max' => 'La note ne peut pas dépasser 200 caractères.',
        ];
    }

    /**
     * Pas de cumul : un accès payé (ou provisoire) en cours ou à venir, ou un accès déjà offert, bloque l'offre.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var User|null $learner */
            $learner = $this->route('user');

            if ($learner === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $message = static::blockingMessage($learner);

            if ($message !== null) {
                $validator->errors()->add('plan', $message);
            }
        }];
    }

    /** Nombre de jours choisi (valide quand la validation est passée). */
    public function days(): int
    {
        return $this->validated('duration') === 'custom'
            ? (int) $this->validated('custom_days')
            : (int) $this->validated('duration');
    }

    /**
     * Raison pour laquelle on ne peut pas offrir d'accès à ce compte, ou null.
     */
    public static function blockingMessage(User $learner): ?string
    {
        $paid = $learner->subscriptions()->paid()
            ->whereIn('status', ['active', 'provisional'])
            ->where('ends_at', '>', now())
            ->where(fn ($query) => $query->whereNull('provisional_until')->orWhere('provisional_until', '>', now()))
            ->exists();

        if ($paid) {
            return 'Cet apprenant a déjà un accès payé en cours : on ne cumule pas un accès offert avec un accès payé.';
        }

        $granted = $learner->subscriptions()->granted()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();

        return $granted
            ? 'Cet apprenant a déjà un accès offert en cours. Retirez-le d\'abord si vous voulez en offrir un autre.'
            : null;
    }
}
