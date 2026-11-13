@php
    // Admins are allowed to open registered submissions, but have no guest
    // capability; a link for a guest row would always 404 (SubmissionAccess).
    $submission = $getRecord();
    $available = $submission->user_id !== null;
@endphp

@if($available)
    <a href="{{ route('hasil', $submission) }}" target="_blank" rel="noopener noreferrer" class="fi-link" data-admin-user-view>
        Buka tampilan pengguna<span class="hl-visually-hidden"> (tab baru)</span>
    </a>
    <p class="hl-admin-note">Halaman publik yang sama seperti yang dilihat pemilik pemeriksaan.</p>
@else
    <p class="hl-admin-note" role="status">Pemeriksaan tamu tidak memiliki tautan tampilan pengguna: admin tidak memegang capability tamu, dan tautan akan selalu ditolak.</p>
@endif
