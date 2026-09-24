<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konten beranda publik (CMS ringan, docs/03 FR-CMS): slide, testimoni, mitra pengguna, dan
 * profil pemilik situs. Konten publik — bukan data ber-tenant, tanpa RLS. Tautan dibatasi
 * CHECK ke path internal atau https; testimoni hanya boleh terbit bila persetujuan
 * publikasi dari orangnya sudah dikonfirmasi (UU PDP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_slides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('eyebrow', 80)->nullable();
            $table->string('title', 120);
            $table->string('subtitle', 300)->nullable();
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url', 300)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->smallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE landing_slides ADD CONSTRAINT landing_slides_cta_check CHECK (cta_url IS NULL OR cta_url ~ '^(/([^/].*)?|https://[^\\s]+)$')");

        Schema::create('landing_testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('role_title', 120)->nullable();
            $table->string('organization_name', 160)->nullable();
            $table->string('program_name', 200)->nullable();
            $table->string('quote', 600);
            $table->smallInteger('rating')->default(5);
            $table->boolean('consent_confirmed')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_sample')->default(false);
            $table->smallInteger('position')->default(0);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE landing_testimonials ADD CONSTRAINT landing_testimonials_rating_check CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE landing_testimonials ADD CONSTRAINT landing_testimonials_consent_check CHECK (NOT is_published OR consent_confirmed)');

        Schema::create('landing_partners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('type', 20)->default('company');
            $table->string('logo_path', 255)->nullable();
            $table->string('website_url', 300)->nullable();
            $table->smallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE landing_partners ADD CONSTRAINT landing_partners_type_check CHECK (type IN ('university','company','government','association','other'))");
        DB::statement("ALTER TABLE landing_partners ADD CONSTRAINT landing_partners_url_check CHECK (website_url IS NULL OR website_url ~ '^https://[^\\s]+$')");

        // Satu baris saja (id = 1).
        Schema::create('site_profile', function (Blueprint $table) {
            $table->smallInteger('id')->primary()->default(1);
            $table->string('company_name', 160);
            $table->string('tagline', 200)->nullable();
            $table->string('about', 1200)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('business_hours', 120)->nullable();
            $table->string('website_url', 300)->nullable();
            $table->string('linkedin_url', 300)->nullable();
            $table->string('instagram_url', 300)->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE site_profile ADD CONSTRAINT site_profile_singleton CHECK (id = 1)');
        DB::statement("ALTER TABLE site_profile ADD CONSTRAINT site_profile_website_url_check CHECK (website_url IS NULL OR website_url ~ '^https://[^\\s]+$')");
        DB::statement("ALTER TABLE site_profile ADD CONSTRAINT site_profile_linkedin_url_check CHECK (linkedin_url IS NULL OR linkedin_url ~ '^https://[^\\s]+$')");
        DB::statement("ALTER TABLE site_profile ADD CONSTRAINT site_profile_instagram_url_check CHECK (instagram_url IS NULL OR instagram_url ~ '^https://[^\\s]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('site_profile');
        Schema::dropIfExists('landing_partners');
        Schema::dropIfExists('landing_testimonials');
        Schema::dropIfExists('landing_slides');
    }
};
