<x-layouts.store :title="__('My profile')">
    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-lg border border-stone-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Profile') }}</h2>
            @unless ($user->hasVerifiedEmail())
                <div class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {{ __('Your email address is not verified yet.') }}
                    <form method="POST" action="{{ route('verification.send') }}" class="inline">
                        @csrf
                        <button class="font-medium underline">{{ __('Resend link') }}</button>
                    </form>
                </div>
            @endunless
            <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-form.input name="name" :label="__('Full name')" :value="$user->name" required />
                <x-form.input name="email" type="email" :label="__('Email')" :value="$user->email" required :hint="__('Changing your email means verifying it again.')" />
                <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$user->phone" required inputmode="numeric" maxlength="10" />
                <x-form.button>{{ __('Save profile') }}</x-form.button>
            </form>
        </section>

        <section class="rounded-lg border border-stone-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Change password') }}</h2>
            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-form.input name="current_password" type="password" bag="password" :label="__('Current password')" required autocomplete="current-password" />
                <x-form.input name="password" type="password" bag="password" :label="__('New password')" required autocomplete="new-password" />
                <x-form.input name="password_confirmation" type="password" bag="password" :label="__('Confirm new password')" required autocomplete="new-password" />
                <x-form.button>{{ __('Update password') }}</x-form.button>
            </form>
        </section>
    </div>
</x-layouts.store>
