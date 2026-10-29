{{-- Voix qui a dit la vidéo (fournisseur et voix, gardés à la préparation de la voix). --}}
@if ($video->voice_name)
    <p class="muted small">Voix : {{ $video->voice_name }} ({{ ['gemini' => 'Gemini', 'openai' => 'OpenAI'][$video->voice_provider] ?? $video->voice_provider }})</p>
@endif
