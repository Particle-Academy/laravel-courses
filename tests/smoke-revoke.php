<?php

declare(strict_types=1);

use ParticleAcademy\LaravelCourses\Models\Certificate;
use ParticleAcademy\LaravelCourses\Services\CertificateService;

/** @var CertificateService $svc */
$svc = app(CertificateService::class);
$cert = Certificate::first();
if (! $cert) {
    echo "No certificate yet — run smoke-flow.php first.\n";
    return;
}

echo "Before: number=" . ($cert->certificate_number ?? 'null')
    . " revoked=" . var_export($cert->isRevoked(), true) . "\n";

$revoked = $svc->revoke($cert, 'Test reason');
echo "After revoke: revoked_at={$revoked->revoked_at} reason={$revoked->revocation_reason}\n";

$verified = $svc->verify($cert->verification_code);
echo "Verify by code: found=" . ($verified ? 'yes' : 'no')
    . " isRevoked=" . var_export($verified?->isRevoked(), true) . "\n";

if ($cert->certificate_number) {
    $byNum = $svc->verify($cert->certificate_number);
    echo "Verify by cert#: found=" . ($byNum ? 'yes' : 'no') . "\n";
}
