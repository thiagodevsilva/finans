@php
    $unsubscribeUrl = $unsubscribeUrl ?? null;
@endphp
<p style="margin:0;padding-top:16px;border-top:1px solid #E0E5F2;font-size:12px;line-height:1.5;color:#A3AED0;">
    Você recebeu este e-mail porque optou por novidades do Levita.
    @if ($unsubscribeUrl)
        <a href="{{ $unsubscribeUrl }}" style="color:#2563eb;text-decoration:underline;">Descadastrar de e-mails promocionais</a>.
    @endif
    E-mails de segurança (senha, confirmação) continuam sendo enviados.
</p>
