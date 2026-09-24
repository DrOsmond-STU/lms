<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Cms\Models\LandingPartner;
use App\Modules\Cms\Models\LandingSlide;
use App\Modules\Cms\Models\LandingTestimonial;
use App\Modules\Cms\Models\SiteProfile;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function pngUpload(int $width = 400, int $height = 200): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 90, 200));
    ob_start();
    imagepng($image);

    return UploadedFile::fake()->createWithContent('gambar.png', (string) ob_get_clean());
}

it('shows default slides, programs, and owner info on the landing page', function () {
    $course = makeCourse();

    $this->get('/')->assertOk()
        ->assertSee('images/landing/slide-1.webp', false)
        ->assertSee('data-slider', false)
        ->assertSee($course['program']->name)
        ->assertSee('Ikut Pelatihan')
        ->assertSee(SiteProfile::current()->company_name)
        ->assertDontSee('id="testimoni"', false)
        ->assertDontSee('id="mitra"', false);
})->group('LANDING');

it('filters programs server-side by competency type, price and duration', function () {
    $international = makeCourse(['category' => 'international']);
    $bnsp = makeCourse(['category' => 'bnsp', 'price' => 2_500_000]);

    $html = $this->get('/?jenis=bnsp&harga=1-5jt')->assertOk()->getContent();

    expect($html)->toMatch('/data-program-card[^>]*data-jenis="bnsp"[^>]*data-harga="1-5jt"(?![^>]*hidden)/s')
        ->and($html)->toMatch('/data-program-card[^>]*data-jenis="international"[^>]*hidden/s');
    // Nilai filter tak dikenal diabaikan (tidak ada injeksi ke tautan/atribut).
    $this->get('/?jenis=%22%3E%3Cscript%3E')->assertOk()->assertDontSee('"><script>', false);
})->group('LANDING');

it('shows published testimonials and active partners only', function () {
    (new LandingTestimonial)->forceFill(['name' => 'Tampil Satu', 'quote' => 'Pelatihannya sangat membantu pekerjaan saya.', 'rating' => 5, 'consent_confirmed' => true, 'is_published' => true])->save();
    (new LandingTestimonial)->forceFill(['name' => 'Draf Dua', 'quote' => 'Belum boleh tampil di beranda publik.', 'rating' => 4])->save();
    (new LandingPartner)->forceFill(['name' => 'Universitas Uji Aktif', 'type' => 'university', 'is_active' => true])->save();
    (new LandingPartner)->forceFill(['name' => 'PT Uji Tersembunyi', 'type' => 'company', 'is_active' => false])->save();

    $this->get('/')->assertOk()
        ->assertSee('id="testimoni"', false)->assertSee('Tampil Satu')->assertDontSee('Draf Dua')
        ->assertSee('id="mitra"', false)->assertSee('Universitas Uji Aktif')->assertDontSee('PT Uji Tersembunyi');
})->group('LANDING');

it('refuses to publish a testimonial without the person\'s consent, in the app and in the database', function () {
    signIn(RoleCode::SuperAdmin);

    $this->post('/admin/beranda/testimoni', ['name' => 'Tanpa Izin', 'quote' => 'Kutipan tanpa persetujuan publikasi.', 'rating' => 5, 'position' => 1, 'is_published' => '1'])
        ->assertSessionHasErrors('consent_confirmed');
    expect(LandingTestimonial::query()->count())->toBe(0);

    expect(fn () => DB::table('landing_testimonials')->insert(['id' => (string) Str::uuid(), 'name' => 'X', 'quote' => 'Kutipan', 'rating' => 5, 'is_published' => true, 'consent_confirmed' => false]))
        ->toThrow(QueryException::class);
})->group('LANDING', 'SEC-PRIV');

it('hides landing content management from non-admin workspaces', function () {
    signIn(RoleCode::Participant);

    $this->get('/admin/beranda/slide')->assertNotFound(); // area admin disembunyikan dari non-admin
})->group('LANDING', 'SEC-AUTHZ');

it('limits landing content management to CMS permissions', function () {
    signIn(RoleCode::FinanceAdmin);

    $this->get('/admin/beranda/slide')->assertForbidden();
    $this->post('/admin/beranda/mitra', ['name' => 'X', 'type' => 'company', 'position' => 1])->assertForbidden();
})->group('LANDING', 'SEC-AUTHZ');

it('re-encodes uploaded slide images and serves them from the private disk', function () {
    Storage::fake('local');
    signIn(RoleCode::SuperAdmin);

    $this->post('/admin/beranda/slide', ['title' => 'Slide Uji', 'position' => 1, 'is_active' => '1', 'cta_label' => 'Lihat', 'cta_url' => '/#pelatihan', 'image' => pngUpload(2400, 1200)])
        ->assertRedirect(route('admin.landing.slides.index'));

    $slide = LandingSlide::query()->sole();
    expect($slide->image_path)->toStartWith('landing/slide/')->toEndWith('.webp');
    [$width] = getimagesizefromstring(Storage::disk('local')->get($slide->image_path));
    expect($width)->toBe(1920);

    $this->get(route('landing.image.slide', $slide))->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get('/')->assertSee(route('landing.image.slide', ['slide' => $slide]), false)->assertDontSee('images/landing/slide-1.webp', false);
})->group('LANDING', 'SEC-FILE');

it('rejects non-image uploads, svg, and unsafe slide links', function () {
    Storage::fake('local');
    signIn(RoleCode::SuperAdmin);

    $svg = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $this->post('/admin/beranda/mitra', ['name' => 'PT SVG', 'type' => 'company', 'position' => 1, 'logo' => $svg])->assertSessionHasErrors('logo');

    foreach (['javascript:alert(1)', '//evil.test', 'http://evil.test'] as $url) {
        $this->post('/admin/beranda/slide', ['title' => 'Tautan Buruk', 'position' => 1, 'cta_label' => 'Klik', 'cta_url' => $url])->assertSessionHasErrors('cta_url');
    }
    $this->post('/admin/beranda/mitra', ['name' => 'PT Http', 'type' => 'company', 'position' => 1, 'website_url' => 'http://tidak-aman.test'])->assertSessionHasErrors('website_url');

    expect(LandingSlide::query()->count())->toBe(0)->and(LandingPartner::query()->count())->toBe(0);
})->group('LANDING', 'SEC-FILE');

it('updates the site owner profile shown on the landing page', function () {
    signIn(RoleCode::AcademicAdmin);

    $this->put('/admin/beranda/profil', [
        'company_name' => 'PT Pemilik Uji', 'email' => 'halo@pemilik.test', 'phone' => '021 555 0101',
        'whatsapp' => '0812 0000 1111', 'address' => 'Jl. Uji No. 1, Jakarta', 'website_url' => 'https://pemilik.test',
    ])->assertRedirect(route('admin.landing.profile.edit'));

    $this->get('/')->assertSee('PT Pemilik Uji')->assertSee('halo@pemilik.test')->assertSee('https://wa.me/6281200001111', false)->assertSee('Jl. Uji No. 1, Jakarta');
})->group('LANDING');

it('hides inactive partner logos from the public image route', function () {
    $partner = new LandingPartner;
    $partner->forceFill(['name' => 'PT Nonaktif', 'type' => 'company', 'is_active' => false, 'logo_path' => 'landing/logo/x.webp'])->save();

    $this->get(route('landing.image.partner', $partner))->assertNotFound();
})->group('LANDING');
