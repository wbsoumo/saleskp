/**
 * Telecaller Reporting Web App - Main Application JS
 */

$(document).ready(function () {
    // 1. Single audio playback management
    // Ensures only one audio element is playing at any time
    document.addEventListener('play', function (e) {
        if (e.target.tagName.toLowerCase() === 'audio') {
            const allAudios = document.querySelectorAll('audio');
            allAudios.forEach(function (audio) {
                if (audio !== e.target) {
                    audio.pause();
                }
            });

            // Add visual playing indicator
            $('.audio-player-container').removeClass('playing-now');
            $(e.target).closest('.audio-player-container').addClass('playing-now');
        }
    }, true);

    document.addEventListener('pause', function (e) {
        if (e.target.tagName.toLowerCase() === 'audio') {
            $(e.target).closest('.audio-player-container').removeClass('playing-now');
        }
    }, true);

    // 2. Playback Speed Selector
    $(document).on('click', '.speed-btn', function (e) {
        e.preventDefault();
        const targetId = $(this).data('target');
        const speed = parseFloat($(this).data('speed'));
        const audio = document.getElementById(targetId);

        if (audio) {
            audio.playbackRate = speed;
            $(this).siblings().removeClass('active btn-primary').addClass('btn-outline-secondary');
            $(this).removeClass('btn-outline-secondary').addClass('active btn-primary');
        }
    });

    // 3. Audio Upload Form Client-Side Validation
    const uploadForm = document.getElementById('recordingUploadForm');
    if (uploadForm) {
        const fileInput = uploadForm.querySelector('input[type="file"][name="audio_file"]');
        const durationInput = uploadForm.querySelector('input[name="call_duration"]');

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;

                // Validate Max Size (25MB default)
                const maxSize = (parseInt(this.getAttribute('data-max-size')) || 25) * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('Selected file is too large! Maximum allowed size is ' + (maxSize / (1024 * 1024)) + ' MB.');
                    this.value = '';
                    return;
                }

                // Try to automatically detect audio duration using HTML5 Audio object
                if (durationInput && (!durationInput.value || durationInput.value.trim() === '')) {
                    const objectUrl = URL.createObjectURL(file);
                    const tempAudio = new Audio();
                    tempAudio.src = objectUrl;
                    tempAudio.addEventListener('loadedmetadata', function () {
                        const totalSec = Math.round(tempAudio.duration);
                        const mins = Math.floor(totalSec / 60);
                        const secs = totalSec % 60;
                        const formatted = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
                        durationInput.value = formatted;
                        URL.revokeObjectURL(objectUrl);
                    });
                }
            });
        }
    }

    // 4. Auto-dismiss flash alerts after 6 seconds
    setTimeout(function () {
        $('.alert-dismissible').fadeOut('slow');
    }, 6000);

    // 5. Tooltips initialization
    if (typeof $('[data-toggle="tooltip"]').tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
