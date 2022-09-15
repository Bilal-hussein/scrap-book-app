@component('mail::message')
#Your OTP to Verify Account.

@component('mail::button', ['url' => ''])
{{$code}}
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
