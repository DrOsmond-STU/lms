<?php

declare(strict_types=1);

use App\Livewire\ClassChat;
use App\Modules\Access\RoleCode;
use App\Modules\Discussion\Models\DiscussionPost;
use App\Modules\Discussion\Models\DiscussionThread;
use App\Modules\Discussion\Models\Poll;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Kelas interaktif: forum & tanya jawab, komentar materi, moderasi & laporan, polling, obrolan.
 */

it('runs forum, Q&A, comments, moderation and reports within a class', function () {
    $course = makeCourse(['final' => false]);
    $class = $course['class'];
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($class, $participant);

    // Utas diskusi & pertanyaan.
    $this->post(route('discussion.store', $class), ['kind' => 'discussion', 'title' => 'Halo semua', 'body' => 'Salam kenal **teman-teman**.'])->assertSessionHasNoErrors();
    $this->post(route('discussion.store', $class), ['kind' => 'question', 'title' => 'Apa itu CIA triad?', 'body' => 'Mohon penjelasan.'])->assertSessionHasNoErrors();
    $question = asSystem(fn () => DiscussionThread::query()->where('kind', 'question')->firstOrFail());
    expect($question->body_html)->toContain('<p>');
    $this->get(route('discussion.index', [$class, 'jenis' => 'question']))->assertOk()->assertSee('Apa itu CIA triad?')->assertSee('Belum terjawab');

    // Komentar pada materi tampil di halaman lesson.
    $this->post(route('discussion.comment', [$class, $course['lessons'][0]]), ['body' => 'Bagian kedua kurang jelas.'])->assertSessionHasNoErrors();
    $this->get(route('learning.lesson', [$enrollment, $course['lessons'][0]]))->assertOk()->assertSee('Bagian kedua kurang jelas.');
    $this->post('/keluar');
    nextRequest();

    // Peserta lain membalas & melaporkan; tidak dapat memoderasi.
    $other = signIn(RoleCode::Participant);
    enrollVia($class, $other);
    $this->post(route('discussion.reply', [$class, $question]), ['body' => 'CIA = Confidentiality, Integrity, Availability.'])->assertSessionHasNoErrors();
    $reply = asSystem(fn () => DiscussionPost::query()->where('thread_id', $question->id)->firstOrFail());
    $this->post(route('discussion.moderate', [$class, $question]), ['action' => 'lock'])->assertForbidden();
    $this->post(route('discussion.report', [$class, $question]), ['reason' => 'Konten tidak relevan dengan materi.'])->assertSessionHasNoErrors();
    expect(asSystem(fn () => DB::table('discussion_reports')->where('thread_id', $question->id)->where('status', 'open')->count()))->toBe(1);
    $this->post('/keluar');
    nextRequest();

    // Penulis pertanyaan menandai jawaban.
    loginAs($participant);
    nextRequest();
    $this->post(route('discussion.answer', [$class, $question, $reply]))->assertSessionHasNoErrors();
    expect(asSystem(fn () => $question->fresh()->is_resolved))->toBeTrue();
    expect(asSystem(fn () => DB::table('notifications')->where('user_id', $other->id)->where('title', 'like', 'Balasan Anda ditandai%')->exists()))->toBeTrue();
    $this->post('/keluar');
    nextRequest();

    // Moderator (admin akademik): laporan terlihat, sembunyikan balasan → laporan selesai, kunci utas → balasan ditolak.
    signIn(RoleCode::AcademicAdmin);
    $this->get(route('discussion.reports', $class))->assertOk()->assertSee('Konten tidak relevan');
    $this->post(route('discussion.moderate', [$class, $question]), ['action' => 'hide_post', 'post_id' => $reply->id, 'reason' => 'Uji moderasi'])->assertSessionHasNoErrors();
    $this->post(route('discussion.moderate', [$class, $question]), ['action' => 'lock'])->assertSessionHasNoErrors();
    expect(asSystem(fn () => DB::table('discussion_reports')->where('thread_id', $question->id)->where('status', 'open')->count()))->toBe(0);
    $this->post('/keluar');
    nextRequest();

    loginAs($participant);
    nextRequest();
    $this->post(route('discussion.reply', [$class, $question]), ['body' => 'Masih bisa?'])->assertSessionHasErrors('body');
    $this->get(route('discussion.show', [$class, $question]))->assertOk()->assertDontSee('CIA = Confidentiality')->assertSee('dikunci');

    // Bukan anggota kelas → 404.
    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);
    $this->get(route('discussion.index', $class))->assertNotFound();
});

it('runs polls and class chat', function () {
    $course = makeCourse(['final' => false]);
    $class = $course['class'];
    $admin = signIn(RoleCode::AcademicAdmin);
    $this->post(route('discussion.polls.store', $class), ['question' => 'Jadwal sesi berikutnya?', 'options' => ['Senin', 'Rabu', ''], 'is_anonymous' => '1'])->assertSessionHasNoErrors();
    $this->post(route('discussion.polls.store', $class), ['question' => 'Kurang opsi', 'options' => ['Satu']])->assertSessionHasErrors('options');
    $poll = asSystem(fn () => Poll::query()->firstOrFail());
    expect($poll->options)->toBe(['Senin', 'Rabu']);
    $this->post('/keluar');
    nextRequest();

    $participant = signIn(RoleCode::Participant);
    enrollVia($class, $participant);
    $this->get(route('discussion.polls', $class))->assertOk()->assertSee('Jadwal sesi berikutnya?');
    $this->post(route('discussion.polls.vote', [$class, $poll]), ['options' => [0, 1]])->assertSessionHasErrors('options');
    $this->post(route('discussion.polls.vote', [$class, $poll]), ['options' => [1]])->assertSessionHasNoErrors();
    $this->post(route('discussion.polls.vote', [$class, $poll]), ['options' => [0]])->assertSessionHasNoErrors();
    expect(asSystem(fn () => $poll->load('votes')->tally()))->toBe([1, 0]);
    $this->post(route('discussion.polls.store', $class), ['question' => 'x', 'options' => ['a', 'b']])->assertForbidden();

    // Obrolan kelas (Livewire): kirim pesan, tampil; kosong ditolak. Konteks tenant diterapkan seperti middleware HTTP.
    app(TenantContext::class)->applyFor($participant);
    Livewire::actingAs($participant)->test(ClassChat::class, ['class' => $class])
        ->set('body', 'Halo kelas!')->call('send')->assertHasNoErrors()->assertSee('Halo kelas!')->assertSet('body', '')
        ->set('body', '')->call('send')->assertHasErrors(['body']);
    $this->get(route('discussion.chat', $class))->assertOk()->assertSee('Obrolan Kelas');
    expect(asSystem(fn () => DB::table('class_messages')->where('course_class_id', $class->id)->count()))->toBe(1);

    // Admin menutup polling → suara ditolak; moderator dapat menyembunyikan pesan obrolan.
    $this->post('/keluar');
    nextRequest();
    loginAs($admin, enrollTotp($admin));
    nextRequest();
    $this->post(route('discussion.polls.close', [$class, $poll]))->assertSessionHasNoErrors();
    $messageId = (string) asSystem(fn () => DB::table('class_messages')->value('id'));
    app(TenantContext::class)->applyFor($admin);
    Livewire::actingAs($admin)->test(ClassChat::class, ['class' => $class])->call('hide', $messageId)->assertDontSee('Halo kelas!');
    app(TenantContext::class)->clear();
    $this->post('/keluar');
    nextRequest();
    loginAs($participant);
    nextRequest();
    $this->post(route('discussion.polls.vote', [$class, $poll]), ['options' => [1]])->assertSessionHasErrors('options');
});
