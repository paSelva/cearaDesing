@if (session('status'))
    <p class="mt-6 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</p>
@endif
