<x-layouts.app :title="$program->name" workspace="participant">
    <x-slot:back><a href="{{ route('catalog.participant') }}" class="hero-back">&larr; Pilih Pelatihan</a></x-slot:back>
    <x-slot:heading>Detail Program</x-slot:heading>
    <x-slot:subtitle>Pelajari silabus, lalu pilih kelas/batch yang sesuai jadwal Anda.</x-slot:subtitle>
    <div>
        @component('catalog._detail', ['program' => $program, 'syllabus' => $syllabus])
            <section class="card p-6" aria-labelledby="classes-heading">
                <h2 id="classes-heading" class="font-bold text-slate-800">Kelas / Batch</h2>
                <x-form-error field="class" />
                @if ($activeEnrollment && $activeEnrollment->status === 'awaiting_payment')
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Kursi Anda sudah dipesan. Selesaikan pembayaran agar dapat mulai belajar.</p>
                    @if ($pendingPayment)<a href="{{ route('payments.show', $pendingPayment) }}" class="btn-primary mt-3">Selesaikan Pembayaran</a>@endif
                @elseif ($activeEnrollment && $activeEnrollment->status === 'applied')
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Pendaftaran Anda pada kelas {{ $activeEnrollment->courseClass->batch_name }} menunggu persetujuan trainer/admin. Anda akan diberi tahu setelah diputuskan.</p>
                    <a href="{{ route('learning.index') }}" class="btn-secondary mt-3">Lihat Pembelajaran Saya</a>
                @elseif ($activeEnrollment)
                    <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">Anda sudah terdaftar pada program ini.</p>
                    <a href="{{ route('learning.classroom', $activeEnrollment) }}" class="btn-primary mt-3">Lanjutkan Belajar</a>
                @else
                    <ul class="mt-3 space-y-4 text-sm">
                        @forelse ($classes as $class)
                            <li class="rounded-lg border border-slate-200 p-3">
                                <span class="font-bold">{{ $class->batch_name }}</span>
                                <span class="block text-xs text-slate-500">{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] }} · sisa {{ $class->seatsLeft() }} kursi</span>
                                @if ($class->trainers->isNotEmpty())<span class="block text-xs text-slate-500">Trainer: {{ $class->trainers->pluck('name')->implode(', ') }}</span>@endif
                                @if ($class->isEnrollmentOpen() && $class->seatsLeft() > 0)
                                    @if ($program->isFree())
                                        <form method="POST" action="{{ route('catalog.enroll', $class) }}" class="mt-2">@csrf<button class="btn-primary">{{ $class->requires_approval ? 'Ajukan Pendaftaran' : 'Daftar' }}</button></form>
                                        @if ($class->requires_approval)<p class="mt-1 text-xs text-slate-500">Pendaftaran perlu persetujuan trainer/admin sebelum materi dapat diakses.</p>@endif
                                    @elseif ($paymentOpen)
                                        <form method="POST" action="{{ route('payments.checkout', $class) }}" class="mt-2">@csrf<button class="btn-primary">Daftar &amp; Bayar {{ $program->priceLabel() }}</button></form>
                                        <p class="mt-1 text-xs text-slate-500">Transfer bank, verifikasi oleh Admin Keuangan.</p>
                                    @else
                                        <p class="mt-2 text-xs text-amber-700">Pembayaran belum dibuka. Hubungi admin atau organisasi Anda untuk didaftarkan.</p>
                                    @endif
                                @else
                                    <p class="mt-2 text-xs text-slate-500">Pendaftaran tidak dibuka.</p>
                                @endif
                            </li>
                        @empty
                            <li class="text-slate-500">Belum ada kelas dibuka.</li>
                        @endforelse
                    </ul>
                @endif
            </section>
        @endcomponent
    </div>
</x-layouts.app>
