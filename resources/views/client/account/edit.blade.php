<x-client-account-layout>
    <!-- Account Information -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="max-w-xl">
                @include('client.account.partials.update-account-information-form')
            </div>
        </div>
    </div>

    <!-- Password Update -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="max-w-xl">
                @include('client.account.partials.update-password-form')
            </div>
        </div>
    </div>
</x-client-account-layout>

