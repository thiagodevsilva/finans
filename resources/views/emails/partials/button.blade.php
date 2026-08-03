@php
    $url = $url ?? '#';
    $label = $label ?? 'Continuar';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
    <tr>
        <td align="center" bgcolor="#2563eb" style="border-radius:10px;">
            <a href="{{ $url }}"
               style="display:inline-block;padding:12px 24px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;background-color:#2563eb;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>
