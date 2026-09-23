<?php

declare(strict_types=1);
use Illuminate\Support\Facades\File;

// Aturan arsitektur & secure coding yang ditegakkan otomatis (docs/09, keamanan/14 SEC-SDLC-02).

it('declares strict types in every PHP file under app/', function () {
    $offenders = collect(File::allFiles(app_path()))
        ->filter(fn ($file) => $file->getExtension() === 'php' && ! str_contains($file->getContents(), 'declare(strict_types=1);'))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

arch('no debugging helpers left in code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('no dangerous functions')
    ->expect(['eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'unserialize', 'rand', 'mt_rand', 'uniqid'])
    ->not->toBeUsed();

arch('env() is only read in configuration files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('domain models are final')
    ->expect('App\Modules')
    ->classes()
    ->toBeFinal()
    ->ignoring(['App\Modules\Access\RoleCode']);

arch('modules only depend on other modules through services, models and contracts')
    ->expect('App\Modules\Audit')
    ->not->toUse(['App\Modules\Identity\Http', 'App\Modules\Access\Http']);

it('does not use unescaped Blade output', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_contains($file->getContents(), '{!!'))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
})->group('SEC-INPUT-01');

it('does not load scripts or styles from third-party CDNs', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => preg_match('#<(script|link)[^>]+(src|href)="https?://#i', $file->getContents()) === 1)
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
})->group('PROTO-20');
