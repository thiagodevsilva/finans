@extends('emails.layouts.brand')

@section('content')
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#1B254B;">Confirme seu e-mail</p>
    <p style="margin:0 0 8px;">Olá{{ isset($userName) && $userName ? ', '.$userName : '' }}!</p>
    <p style="margin:0;">
        Para manter sua conta Levita segura, confirme que este endereço de e-mail é seu.
        Você já pode usar o app normalmente; a confirmação é recomendada.
    </p>

    @include('emails.partials.button', [
        'url' => $url,
        'label' => 'Confirmar e-mail',
    ])

    <p style="margin:0;font-size:13px;color:#707EAE;">
        Se você não criou uma conta no Levita, ignore este e-mail.
    </p>
@endsection

@section('footer')
    @include('emails.partials.footer-transactional')
@endsection
