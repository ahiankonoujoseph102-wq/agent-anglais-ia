{{-- Une phrase anglaise et sa traduction (guides, pages d'arrivée) : anglais en orange foncé, lu en anglais américain. --}}
@props(['en', 'fr' => null])
<li class="phrase"><span class="phrase-en" lang="en-US">{{ $en }}</span>@if ($fr)<span class="phrase-fr">{{ $fr }}</span>@endif</li>
