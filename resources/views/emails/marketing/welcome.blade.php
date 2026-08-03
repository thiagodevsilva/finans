@extends('emails.layouts.brand')

@section('content')
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#1B254B;">Bem-vindo ao Levita</p>
    <p style="margin:0 0 8px;">Olá{{ isset($userName) && $userName ? ', '.$userName : '' }}!</p>
    <p style="margin:0;">
        Sua conta está pronta. Organize transações, cartões e contas fixas em um só lugar —
        e acompanhe o saldo com clareza.
    </p>

    @include('emails.partials.button', [
        'url' => $url,
        'label' => 'Abrir o Levita',
    ])

    <p style="margin:0;font-size:13px;color:#707EAE;">
        De vez em quando enviamos dicas e novidades. Você pode descadastrar a qualquer momento pelo link abaixo.
    </p>
@endsection

@section('footer')
    @include('emails.partials.footer-marketing', ['unsubscribeUrl' => $unsubscribeUrl ?? null])
@endsection
