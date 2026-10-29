{{--
    Visage simple (SVG) de Teacher Rose, affiché tant qu'elle n'a pas de personnage 3D (config/avatar.php → rose.file)
    ou en repli : personnage stylisé et original (longues tresses cuivrées, regard ouvert, léger sourire, gilet bleu nuit, foulard orange). Mêmes
    classes que le visage de Teacher Joe (yeux qui clignent, bouche #mouth qui suit la voix).
--}}
<svg viewBox="0 0 200 250" class="face face-rose" role="img">
    {{-- Tresses derrière les épaules. --}}
    <path d="M22 96 Q14 170 34 214 L58 214 Q44 160 50 104 Z M178 96 Q186 170 166 214 L142 214 Q156 160 150 104 Z" class="face-hair face-locs"/>
    {{-- Tenue : gilet bleu nuit, chemisier blanc, foulard orange. --}}
    <path d="M16 250 Q20 206 72 194 L128 194 Q180 206 184 250 Z" class="face-vest"/>
    <path d="M80 194 L100 230 L120 194 Z" class="face-shirt"/>
    <path d="M74 192 Q100 212 126 192 L120 204 Q100 222 80 204 Z" class="face-tie"/>
    <ellipse cx="100" cy="104" rx="80" ry="86" class="face-skin"/>
    {{-- Tresses sur le front et les côtés. --}}
    <path d="M24 100 Q26 22 100 16 Q174 22 176 100 Q166 60 136 50 Q118 62 100 56 Q82 62 64 50 Q34 60 24 100 Z" class="face-hair face-locs"/>
    <path d="M32 172 L36 210 M44 168 L48 210 M168 172 L164 210 M156 168 L152 210" class="face-loc-lines"/>
    <circle cx="22" cy="128" r="6" class="face-tie"/>
    <circle cx="178" cy="128" r="6" class="face-tie"/>
    {{-- Joues légèrement rosées. --}}
    <ellipse cx="54" cy="130" rx="13" ry="7" class="face-blush"/>
    <ellipse cx="146" cy="130" rx="13" ry="7" class="face-blush"/>
    {{-- Yeux grands ouverts, avec un reflet (le reflet cligne avec eux). --}}
    <g class="face-eyes">
        <ellipse cx="72" cy="100" rx="9" ry="12"/>
        <ellipse cx="128" cy="100" rx="9" ry="12"/>
        <circle cx="75" cy="95" r="3" class="face-shine"/>
        <circle cx="131" cy="95" r="3" class="face-shine"/>
    </g>
    {{-- Sourcils fins et hauts : regard ouvert. --}}
    <path d="M60 78 Q72 71 85 76 M115 76 Q128 71 140 78" class="face-brows face-brows-thin"/>
    {{-- Léger sourire au repos ; la bouche #mouth s'ouvre dedans quand elle parle. --}}
    <path d="M77 143 Q100 164 123 143" class="face-smile"/>
    <ellipse id="mouth" cx="100" cy="152" rx="12" ry="3" class="face-mouth"/>
</svg>
