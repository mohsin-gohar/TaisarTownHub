<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New matches for {{ $searchName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ea;font-family:Arial,Helvetica,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ea;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #e2d9c6;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="background:#101c2c;padding:22px 26px;">
                        <div style="font-size:18px;font-weight:bold;color:#f9e7ba;letter-spacing:.3px;">TaisarTownHub</div>
                        <div style="font-size:12px;color:#c5a46d;margin-top:4px;">Taiser Town · Scheme 45 · Karachi</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px;">
                        <p style="margin:0 0 8px;font-size:12px;color:#8a6d3b;text-transform:uppercase;letter-spacing:1.2px;font-weight:bold;">Saved search alert</p>
                        <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#101c2c;">New matches for “{{ $searchName }}”</h1>
                        <p style="margin:0 0 18px;font-size:13px;line-height:1.6;color:#546070;">Search criteria: {{ $summary }}</p>

                        @foreach ($items as $item)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e7dfcf;border-radius:10px;margin:0 0 12px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <div style="font-size:15px;font-weight:bold;color:#101c2c;margin-bottom:4px;">{{ $item['title'] }}</div>
                                        <div style="font-size:12px;color:#546070;margin-bottom:8px;">{{ $item['location'] }} · {{ $item['type'] }} · {{ $item['size'] }} Sq. Yd.</div>
                                        <div style="font-size:14px;font-weight:bold;color:#8a6d3b;margin-bottom:10px;">{{ $item['price'] }}</div>
                                        <a href="{{ $item['url'] }}" style="display:inline-block;background:#c5a46d;color:#101c2c;text-decoration:none;font-size:12px;font-weight:bold;padding:8px 14px;border-radius:8px;">View property</a>
                                    </td>
                                </tr>
                            </table>
                        @endforeach

                        <p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:#7a8494;">
                            You received this alert because you saved a search on TaisarTownHub. You can change the alert frequency or delete the search from the
                            <a href="{{ $manageUrl }}" style="color:#8a6d3b;">property listings page</a>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f5ef;padding:14px 26px;font-size:11px;color:#8a93a3;">
                        TaisarTownHub · Verified Taiser Town property marketplace
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
