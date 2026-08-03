@extends('emails.layouts.brand')

@section('content')
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#1B254B;">Redefinir senha</p>
    <p style="margin:0 0 8px;">Olá{{ isset($userName) && $userName ? ', '.$userName : '' }}!</p>
    <p style="margin:0;">
        Recebemos um pedido para redefinir a senha da sua conta no Levita.
        Clique no botão abaixo para escolher uma nova senha. O link expira em {{ $expireMinutes ?? 60 }} minutos.
    </p>

    @include('emails.partials.button', [
        'url' => $url,
        'label' => 'Redefinir senha',
    ])

    <p style="margin:0;font-size:13px;color:#707EAE;">
        Se você não solicitou a redefinição, ignore este e-mail. Sua senha permanecerá a mesma.
    </p>
@endsection

@section('footer')
    @include('emails.partials.footer-transactional')
@endsection
