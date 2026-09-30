WWork

{!! $heading !!}

@foreach ($lines as $line)
{!! $line !!}

@endforeach
@foreach ($details as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
@if ($details !== [])

@endif
@if ($button)
{!! $button['label'] !!}: {!! $button['url'] !!}

@endif
@if ($note)
{!! $note !!}

@endif
{!! __('mail.common.footer') !!}
