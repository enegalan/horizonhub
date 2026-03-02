<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mailSubject ?? 'Horizon Hub Alert' }}</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2937; background-color: #f3f4f6;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6;">
    <tr>
        <td style="padding: 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <tr>
                    <td style="padding: 24px 28px; border-bottom: 1px solid #e5e7eb;">
                        <h1 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 600; color: #111827;">Horizon Hub Alert</h1>
                        <p style="margin: 0; font-size: 13px; color: #6b7280;">
                            {{ $alert->rule_type }}
                            @if($service)
                                · {{ $service->name }}
                            @endif
                            @if(count($enrichedEvents) > 1)
                                · {{ count($enrichedEvents) }} events
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 24px 28px;">
                        @foreach($enrichedEvents as $index => $ev)
                            <div style="margin-bottom: {{ $loop->last ? 0 : 20 }}px; padding: 16px; background-color: #f9fafb; border-radius: 6px; border-left: 4px solid #dc2626;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td>
                                            <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.02em;">
                                                @if(count($enrichedEvents) > 1)
                                                    Event {{ $index + 1 }}
                                                @else
                                                    Failed job
                                                @endif
                                            </p>
                                            @if(!empty($ev['job_class']))
                                                <p style="margin: 0 0 6px 0; font-size: 15px; font-weight: 600; color: #111827; font-family: ui-monospace, monospace;">{{ $ev['job_class'] }}</p>
                                            @endif
                                            <table role="presentation" cellspacing="0" cellpadding="0" style="font-size: 13px; color: #4b5563;">
                                                @if(!empty($ev['queue']))
                                                    <tr><td style="padding: 2px 0;"><strong>Queue:</strong></td><td style="padding: 2px 0; padding-left: 8px;">{{ $ev['queue'] }}</td></tr>
                                                @endif
                                                @if(!empty($ev['failed_at']))
                                                    <tr><td style="padding: 2px 0;"><strong>Failed at:</strong></td><td style="padding: 2px 0; padding-left: 8px;">{{ $ev['failed_at'] }}</td></tr>
                                                @endif
                                                @if(isset($ev['attempts']) && $ev['attempts'] !== null)
                                                    <tr><td style="padding: 2px 0;"><strong>Attempts:</strong></td><td style="padding: 2px 0; padding-left: 8px;">{{ $ev['attempts'] }}</td></tr>
                                                @endif
                                                @if(isset($ev['job_id']) && $ev['job_id'])
                                                    <tr><td style="padding: 2px 0;"><strong>Job ID:</strong></td><td style="padding: 2px 0; padding-left: 8px;">{{ $ev['job_id'] }}</td></tr>
                                                @endif
                                            </table>
                                            @if(!empty($ev['exception']))
                                                <div style="margin-top: 12px; padding: 12px; background-color: #fef2f2; border-radius: 4px; border: 1px solid #fecaca;">
                                                    <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 600; color: #991b1b;">Exception</p>
                                                    <pre style="margin: 0; font-size: 12px; font-family: ui-monospace, monospace; color: #7f1d1d; white-space: pre-wrap; word-break: break-word;">{{ $ev['exception'] }}</pre>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        @endforeach
                        <p style="margin: 16px 0 0 0; font-size: 12px; color: #9ca3af;">Sent at {{ now()->format('Y-m-d H:i:s T') }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
