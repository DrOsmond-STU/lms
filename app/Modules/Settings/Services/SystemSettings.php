<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengaturan sistem (FR-SET-001/002/005). Nilai keamanan hanya boleh berada dalam batas
 * aman yang ditetapkan di kode — pengaturan UI tidak dapat melonggarkan baseline
 * (config/security.php). Setiap perubahan diaudit dengan nilai sebelum/sesudah.
 */
final class SystemSettings
{
    private const CACHE_KEY = 'system_settings.v1';

    /**
     * @var array<string, array{label: string, type: 'string'|'email'|'int'|'bool', min?: int, max?: int, config?: string, group: string}>
     */
    public const DEFINITIONS = [
        'branding.app_display_name' => ['label' => 'Nama aplikasi', 'type' => 'string', 'max' => 60, 'config' => 'app.name', 'group' => 'Branding'],
        'branding.support_email' => ['label' => 'Email dukungan', 'type' => 'email', 'group' => 'Branding'],
        'branding.support_phone' => ['label' => 'Telepon/WhatsApp dukungan', 'type' => 'string', 'max' => 30, 'group' => 'Branding'],
        'security.session_idle_privileged' => ['label' => 'Batas idle sesi admin/trainer (menit)', 'type' => 'int', 'min' => 5, 'max' => 30, 'config' => 'security.session.idle_minutes.privileged', 'group' => 'Keamanan'],
        'security.session_idle_participant' => ['label' => 'Batas idle sesi peserta (menit)', 'type' => 'int', 'min' => 15, 'max' => 120, 'config' => 'security.session.idle_minutes.participant', 'group' => 'Keamanan'],
        'security.password_min_privileged' => ['label' => 'Panjang minimal kata sandi admin/trainer', 'type' => 'int', 'min' => 12, 'max' => 64, 'config' => 'security.password.min_privileged', 'group' => 'Keamanan'],
        'security.password_min_participant' => ['label' => 'Panjang minimal kata sandi peserta', 'type' => 'int', 'min' => 8, 'max' => 64, 'config' => 'security.password.min_participant', 'group' => 'Keamanan'],
        'security.registration_enabled' => ['label' => 'Registrasi mandiri peserta dibuka', 'type' => 'bool', 'config' => 'security.registration.enabled', 'group' => 'Keamanan'],
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        try {
            /** @var array<string, mixed> */
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => Schema::hasTable('system_settings')
                ? DB::table('system_settings')->pluck('value', 'key')->map(fn ($value) => json_decode((string) $value, true))->all()
                : []);
        } catch (Throwable) {
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Terapkan nilai tersimpan ke konfigurasi runtime (dipanggil saat boot). */
    public static function applyToConfig(): void
    {
        foreach (self::all() as $key => $value) {
            $definition = self::DEFINITIONS[$key] ?? null;
            if ($definition !== null && isset($definition['config']) && $value !== '' && self::withinBounds($definition, $value)) {
                config([$definition['config'] => $value]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(array $input, User $actor, AuditLogger $audit): void
    {
        $current = self::all();
        $changes = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $field = str_replace('.', '__', $key);
            $value = match ($definition['type']) {
                'bool' => (bool) ($input[$field] ?? false),
                'int' => (int) ($input[$field] ?? 0),
                default => trim((string) ($input[$field] ?? '')),
            };
            if (! self::withinBounds($definition, $value)) {
                throw ValidationException::withMessages([$field => $definition['label'].' di luar batas aman ('.($definition['min'] ?? '').'–'.($definition['max'] ?? '').').']);
            }
            $old = $current[$key] ?? null;
            if ($old !== $value) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }
        }
        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($changes, $actor, $audit): void {
            foreach ($changes as $key => $change) {
                DB::table('system_settings')->upsert([[
                    'key' => $key, 'value' => json_encode($change['to']), 'updated_by' => $actor->id, 'updated_at' => now(),
                ]], ['key'], ['value', 'updated_by', 'updated_at']);
            }
            $audit->record('system_setting.updated', $actor, 'system_setting', null, $changes);
        });
        Cache::forget(self::CACHE_KEY);
    }

    /** @param array{type: string, min?: int, max?: int} $definition */
    private static function withinBounds(array $definition, mixed $value): bool
    {
        return match ($definition['type']) {
            'int' => is_int($value) && $value >= ($definition['min'] ?? PHP_INT_MIN) && $value <= ($definition['max'] ?? PHP_INT_MAX),
            'bool' => is_bool($value),
            'email' => is_string($value) && ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false),
            default => is_string($value) && mb_strlen($value) <= ($definition['max'] ?? 255),
        };
    }
}
