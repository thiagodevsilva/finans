<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? config('app.name', 'Levita') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F7FE;font-family:'DM Sans',Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F7FE;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td>
                            @include('emails.partials.header')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px 24px;color:#1B254B;font-size:16px;line-height:1.55;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px;">
                            @hasSection('footer')
                                @yield('footer')
                            @else
                                @include('emails.partials.footer-transactional')
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
