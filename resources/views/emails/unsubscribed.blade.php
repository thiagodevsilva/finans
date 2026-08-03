<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Descadastro — {{ config('app.name', 'Levita') }}</title>
    <style>
        body { margin: 0; font-family: 'DM Sans', Helvetica, Arial, sans-serif; background: #F4F7FE; color: #1B254B; }
        .wrap { max-width: 480px; margin: 64px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 16px; padding: 32px 28px; box-shadow: 0 8px 24px rgba(27, 37, 75, 0.06); }
        .accent { height: 6px; background: #ffc107; border-radius: 16px 16px 0 0; margin: -32px -28px 24px; }
        h1 { margin: 0 0 12px; font-size: 22px; }
        p { margin: 0 0 12px; font-size: 15px; line-height: 1.55; color: #707EAE; }
        .email { color: #1B254B; font-weight: 600; }
        a { color: #2563eb; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,700&display=swap" rel="stylesheet" />
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="accent"></div>
            <h1>Descadastro concluído</h1>
            <p>
                O e-mail <span class="email">{{ $email }}</span> não receberá mais novidades e promoções do Levita.
            </p>
            <p>
                E-mails de segurança — como redefinição de senha e confirmação de e-mail — continuam sendo enviados.
            </p>
            <p style="margin-top: 24px;">
                <a href="{{ url('/') }}">Voltar ao Levita</a>
            </p>
        </div>
    </div>
</body>
</html>
