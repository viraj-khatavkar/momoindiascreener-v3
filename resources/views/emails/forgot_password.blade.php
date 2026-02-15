{{--@formatter:off--}}
<x-mail::message>
# Hello {{ $user->name }}!

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="$url">
Reset Password
</x-mail::button>

If you did not request a password reset, no further action is required.

Best,<br>
Team {{ config('app.name') }}<br>
</x-mail::message>
