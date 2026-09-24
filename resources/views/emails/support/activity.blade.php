@extends('emails.layouts.brand')

@section('content')
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;color:#1B254B;">{{ $eventLabel }}</p>

    <p style="margin:0 0 8px;"><strong>Título:</strong> {{ $ticket->title }}</p>
    <p style="margin:0 0 8px;"><strong>Conta:</strong> {{ $familyName ?? '—' }}</p>
    <p style="margin:0 0 8px;"><strong>Autor:</strong> {{ $authorName ?? '—' }}@if($authorEmail) ({{ $authorEmail }})@endif</p>
    <p style="margin:0 0 8px;"><strong>Status:</strong> {{ $ticket->status }}</p>

    @if($detail)
        <p style="margin:16px 0 0;padding:12px 14px;background:#F4F7FE;border-radius:10px;white-space:pre-wrap;font-size:14px;color:#1B254B;">{{ $detail }}</p>
    @endif

    @include('emails.partials.button', [
        'url' => $adminUrl,
        'label' => 'Abrir no painel admin',
    ])
@endsection

@section('footer')
    @include('emails.partials.footer-transactional')
@endsection
