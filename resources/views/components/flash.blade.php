@if (session('status'))
    <p class="mt-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</p>
@endif
