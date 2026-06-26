<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contact — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-[#FDFDFC] text-[#1b1b18] flex min-h-screen items-center justify-center p-6">
        <main class="w-full max-w-md">
            <h1 class="mb-4 text-lg font-semibold">Contact us</h1>

            @if (session('status'))
                <p class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </p>
            @endif

            <form method="POST" action="{{ route('contact.send') }}" class="flex flex-col gap-4" novalidate>
                @csrf

                {{-- honeypot: hidden from real users, left empty by them; bots tend to fill every field --}}
                <div class="absolute left-[-9999px]" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        aria-describedby="name-error"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none focus:ring">
                    @error('name')
                        <p id="name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                        aria-describedby="email-error"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none focus:ring">
                    @error('email')
                        <p id="email-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="message" class="mb-1 block text-sm font-medium">Message</label>
                    <textarea id="message" name="message" rows="5" required
                        aria-describedby="message-error"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none focus:ring">{{ old('message') }}</textarea>
                    @error('message')
                        <p id="message-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="rounded-sm bg-[#1b1b18] px-5 py-2 text-sm text-white hover:bg-black">
                    Send message
                </button>
            </form>
        </main>
    </body>
</html>
