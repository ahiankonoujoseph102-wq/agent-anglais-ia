<nav class="tabs" aria-label="Administration">
    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>Inscrits</a>
    <a href="{{ route('admin.usage.index') }}" @class(['active' => request()->routeIs('admin.usage.*')])>Consommation</a>
</nav>
