@php
    use App\Actions\Account\RegisterParticipant;
    use App\Enums\ParticipantType;
    use App\Enums\PersonType;

    $personTypes = collect(PersonType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    $participantTypes = collect(ParticipantType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    $ufs = array_combine(RegisterParticipant::UFS, RegisterParticipant::UFS);
@endphp

<x-layouts.site title="Criar conta">
    <section class="mx-auto max-w-xl">
        <h1 class="text-3xl font-semibold tracking-tight">Criar conta de participante</h1>
        <p class="mt-2 text-stone-400">
            Com a conta você poderá inscrever projetos no 1º Prêmio Ceará Tem Design.
            A conta pode ser de pessoa física ou jurídica.
        </p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
            @csrf

            <x-form.field name="name" label="Nome completo ou razão social" autocomplete="name" required autofocus />
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="person_type" label="Tipo de pessoa" :options="$personTypes" required />
                <x-form.field name="document" label="CPF ou CNPJ" hint="CPF para pessoa física, CNPJ para pessoa jurídica." required />
            </div>

            <x-form.select name="participant_type" label="Tipo de participante" :options="$participantTypes" required />

            <x-form.field name="phone" label="Telefone / WhatsApp" type="tel" autocomplete="tel" hint="Com DDD." required />

            <div class="grid gap-5 sm:grid-cols-[1fr_8rem]">
                <x-form.field name="city" label="Cidade" autocomplete="address-level2" required />
                <x-form.select name="state" label="UF" :options="$ufs" required />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="password" label="Senha" type="password" autocomplete="new-password" hint="Mínimo de 8 caracteres." required />
                <x-form.field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
            </div>

            <x-form.button>Criar conta</x-form.button>
        </form>

        <p class="mt-6 text-sm text-stone-400">
            Já tem conta? <a href="{{ route('login') }}" class="text-amber-300 hover:underline">Entrar</a>
        </p>
    </section>
</x-layouts.site>
