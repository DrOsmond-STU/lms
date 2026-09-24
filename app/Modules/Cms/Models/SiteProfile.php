<?php

declare(strict_types=1);

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Informasi pemilik situs (satu baris, id = 1).
 *
 * @property int $id
 * @property string $company_name
 * @property string|null $tagline
 * @property string|null $about
 * @property string|null $address
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $business_hours
 * @property string|null $website_url
 * @property string|null $linkedin_url
 * @property string|null $instagram_url
 */
final class SiteProfile extends Model
{
    protected $table = 'site_profile';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [];

    /** Profil tersimpan, atau bawaan (belum disimpan) bila admin belum mengisinya. */
    public static function current(): self
    {
        $profile = self::query()->find(1);
        if ($profile instanceof self) {
            return $profile;
        }

        $default = new self;
        $default->forceFill([
            'id' => 1,
            'company_name' => 'Semesta Teknologi Utama',
            'tagline' => 'Penyelenggara platform pelatihan & sertifikasi STU LMS',
            'about' => 'STU LMS dikelola oleh Semesta Teknologi Utama untuk mendukung pelatihan dan sertifikasi kompetensi Internasional maupun BNSP — dari pendaftaran, pembelajaran, ujian, hingga sertifikat digital yang dapat diverifikasi publik.',
        ]);

        return $default;
    }

    /** Nomor WhatsApp dalam format wa.me (hanya digit, awalan 62). */
    public function whatsappLink(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }
}
