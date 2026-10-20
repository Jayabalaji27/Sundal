@extends('emails.layout')

@section('title', $reportTitle)

@section('content')
    <p>Hello,</p>
    <p>{{ $senderName }} sent you the <strong>{{ $reportTitle }}</strong> from {{ $workspaceName }}.</p>
    @if($note)
        <p style="padding: 10px 14px; background: #f9fafb; border-left: 3px solid #e5e7eb; white-space: pre-line;">{{ $note }}</p>
    @endif
    <p>The report is attached as <strong>{{ $filename }}</strong>.</p>
@endsection
