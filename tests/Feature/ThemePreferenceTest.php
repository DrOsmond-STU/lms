<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;

it('renders the saved theme preference on first paint', function () {
    $this->withUnencryptedCookie('stu_theme', 'dark')->get('/masuk')
        ->assertOk()->assertSee('data-theme="dark"', false)
        ->assertSee('data-theme-set="dark" aria-pressed="true"', false);
})->group('UI');

it('follows the system theme when no or an unknown preference is stored', function (?string $value) {
    $request = $value === null ? $this : $this->withUnencryptedCookie('stu_theme', $value);

    $request->get('/masuk')->assertOk()
        ->assertDontSee('<html lang="id" data-theme', false)
        ->assertSee('data-theme-set="system" aria-pressed="true"', false);
})->with([null, 'blue', '"><script>alert(1)</script>'])->group('UI');

it('renders the themed dashboard hero with KPI tiles', function () {
    signIn(RoleCode::Participant);

    $this->get(route('participant.dashboard'))->assertOk()
        ->assertSee('class="hero ', false)
        ->assertSee('class="card tile', false)
        ->assertSee('Sertifikat aktif');
})->group('UI');
