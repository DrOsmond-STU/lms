<?php

declare(strict_types=1);
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

it('sends a strict nonce-based CSP and hardening headers', function (string $path) {
    $response = $this->get($path);
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+' 'strict-dynamic'/")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'none'")
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
})->with(['/', '/masuk', '/lupa-kata-sandi', '/halaman-tidak-ada'])->group('SEC-INFRA-12', 'SEC-INPUT-04');

it('uses a __Host- session cookie that is Secure, HttpOnly and SameSite=Lax', function () {
    config(['session.driver' => 'array']);
    $cookie = collect($this->get('/masuk')->headers->getCookies())->first(fn ($c) => $c->getName() === '__Host-stu_session');

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getDomain())->toBeNull()
        ->and($cookie->getPath())->toBe('/');
})->group('SEC-AUTH-16');

it('marks authenticated pages as non-cacheable', function () {
    loginAs(makeUser());

    expect((string) $this->get('/peserta')->headers->get('Cache-Control'))->toContain('no-store');
})->group('SEC-AUTHZ-12');

it('never exposes a stack trace or debug details on errors', function () {
    config(['app.debug' => false]);

    $this->get('/tidak-ada-'.str_repeat('x', 5))->assertNotFound()->assertDontSee('Stack trace')->assertSee('Kode 404');
})->group('SEC-INPUT-27');

it('rejects state-changing requests without a valid CSRF token', function () {
    // Laravel melewati CSRF saat unit test; uji middleware secara langsung dengan flag itu dimatikan.
    $middleware = new class(app(), app('encrypter')) extends ValidateCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };

    $request = Request::create('/masuk', 'POST', ['email' => 'a@example.test', 'password' => 'x']);
    $request->setLaravelSession(app('session.store'));

    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(TokenMismatchException::class);
})->group('SEC-INPUT-07');

it('forbids caching and proxy rewriting of HTML pages, including guest pages', function () {
    $cacheControl = (string) $this->get('/masuk')->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')->toContain('no-transform');
})->group('SEC-AUTHZ-12', 'SEC-INPUT-04');

it('sends no-referrer on invitation links so the token never leaks', function () {
    expect($this->get('/undangan/'.str_repeat('a', 43))->headers->get('Referrer-Policy'))->toBe('no-referrer');
})->group('SEC-AUTH-22');
