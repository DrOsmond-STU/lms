<?php

declare(strict_types=1);

use App\Support\Security\OutboundUrl;

it('rejects non-public destinations including cgnat, benchmark, nat64 and ipv4-mapped ranges', function (string $ip) {
    expect(OutboundUrl::isPublicIp($ip))->toBeFalse();
})->with(['127.0.0.1', '10.1.2.3', '192.168.0.9', '169.254.169.254', '100.64.3.4', '192.0.0.8', '198.18.5.5', '203.0.113.7', '224.0.0.1', '::1', 'fe80::1', 'fd00::5', '::ffff:10.0.0.1', '64:ff9b::a00:1', 'ff02::1', '2001:db8::1']);

it('accepts public addresses and pins the vetted ip to the connection', function () {
    expect(OutboundUrl::isPublicIp('8.8.8.8'))->toBeTrue()
        ->and(OutboundUrl::isPublicIp('2606:4700::1111'))->toBeTrue()
        ->and(OutboundUrl::resolve('https://93.184.216.34/push'))->toBe(['host' => '93.184.216.34', 'ip' => '93.184.216.34'])
        ->and(OutboundUrl::curlPin(['host' => 'example.test', 'ip' => '93.184.216.34']))->toBe([CURLOPT_RESOLVE => ['example.test:443:93.184.216.34']])
        ->and(OutboundUrl::curlPin(['host' => 'example.test', 'ip' => '2606:4700::1111']))->toBe([CURLOPT_RESOLVE => ['example.test:443:[2606:4700::1111]']])
        ->and(OutboundUrl::curlPin(['host' => 'fcm.googleapis.com', 'ip' => null]))->toBe([])
        ->and(OutboundUrl::resolve('http://93.184.216.34/'))->toBeNull()
        ->and(OutboundUrl::resolve('https://user@93.184.216.34/'))->toBeNull()
        ->and(OutboundUrl::resolve('https://93.184.216.34:8443/'))->toBeNull()
        ->and(OutboundUrl::resolve('https://10.0.0.1/'))->toBeNull();
});
