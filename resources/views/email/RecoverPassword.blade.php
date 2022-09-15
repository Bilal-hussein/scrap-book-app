@component('mail::message')
#Your OTP for new Password.

@component('mail::button',['url' => ''])
{{$code}}
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
