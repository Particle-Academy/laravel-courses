<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion</title>
    <style>
        @page { margin: 0; }
        body {
            margin: 0;
            font-family: 'Times New Roman', serif;
            color: #1a1a1a;
            background: #fdfaf3;
        }
        .frame {
            width: 100%;
            height: 100vh;
            box-sizing: border-box;
            padding: 48px;
            border: 12px solid #b08d57;
            outline: 2px solid #b08d57;
            outline-offset: 8px;
            text-align: center;
        }
        .eyebrow {
            letter-spacing: 0.4em;
            font-size: 14px;
            text-transform: uppercase;
            color: #8a6d3b;
            margin-bottom: 28px;
        }
        .title {
            font-size: 48px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin: 0 0 24px;
        }
        .intro { font-size: 16px; color: #555; margin: 0 0 16px; }
        .recipient {
            font-size: 40px;
            font-style: italic;
            margin: 0 0 24px;
            border-bottom: 1px solid #c8b48a;
            padding-bottom: 16px;
            display: inline-block;
            min-width: 60%;
        }
        .body { font-size: 18px; line-height: 1.6; margin: 0 0 40px; }
        .program { font-weight: 700; }
        .meta {
            display: table;
            width: 100%;
            margin-top: 64px;
        }
        .meta-cell {
            display: table-cell;
            font-size: 14px;
            color: #555;
            width: 33%;
            vertical-align: top;
        }
        .meta-cell strong { display: block; color: #1a1a1a; font-size: 16px; margin-bottom: 6px; }
        .code { font-family: 'Courier New', monospace; letter-spacing: 0.1em; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="eyebrow">Certificate of Completion</div>
        <h1 class="title">{{ $title ?? 'Course Completion' }}</h1>
        <p class="intro">This is to certify that</p>
        <div class="recipient">{{ $recipientName }}</div>
        <p class="body">
            has successfully completed
            <span class="program">{{ $programName }}</span>{{ $programDetail ? ', ' . $programDetail : '' }}.
        </p>

        <div class="meta">
            <div class="meta-cell">
                <strong>Issued</strong>
                {{ $issuedAt }}
            </div>
            <div class="meta-cell">
                <strong>Issued by</strong>
                {{ $issuer ?? config('app.name') }}
            </div>
            <div class="meta-cell">
                <strong>Verification</strong>
                <span class="code">{{ $verificationCode }}</span>
            </div>
        </div>
    </div>
</body>
</html>
