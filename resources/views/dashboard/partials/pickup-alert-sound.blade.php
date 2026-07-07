<script>
    const hasPickupAlert = {{ ((int) ($contratosPorRecoger ?? 0)) > 0 ? 'true' : 'false' }};
    const canPlayPickupAlertSound = {{ (($canPlayPickupAlertSound ?? false) ? 'true' : 'false') }};
    const navEntry = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0])
        ? performance.getEntriesByType('navigation')[0]
        : null;
    const isReloadNavigation = !!(navEntry && navEntry.type === 'reload');
    const shouldPlayPickupSoundOnEntry = !isReloadNavigation;
    let pickupAlertSoundPlayed = false;
    let pickupAlertRetryTimer = null;
    let pickupAlertAudio = null;
    let pickupAlertStopTimer = null;
    const pickupAlertAudioUrl = @json(asset('sounds/pickup-alert.mp3'));

    async function playPickupWhistleSound(forceResume = false) {
        if (pickupAlertSoundPlayed) {
            return false;
        }

        try {
            if (!pickupAlertAudio) {
                pickupAlertAudio = new Audio(pickupAlertAudioUrl);
                pickupAlertAudio.preload = 'auto';
                pickupAlertAudio.playsInline = true;
            }

            pickupAlertAudio.pause();
            pickupAlertAudio.currentTime = 0;
            pickupAlertAudio.volume = 1;
            pickupAlertAudio.loop = true;

            const playPromise = pickupAlertAudio.play();
            if (playPromise && typeof playPromise.then === 'function') {
                await playPromise;
            }

            pickupAlertSoundPlayed = true;

            if (pickupAlertStopTimer) {
                clearTimeout(pickupAlertStopTimer);
            }
            pickupAlertStopTimer = setTimeout(() => {
                if (pickupAlertAudio) {
                    pickupAlertAudio.pause();
                    pickupAlertAudio.currentTime = 0;
                    pickupAlertAudio.loop = false;
                }
            }, 10000);

            return true;
        } catch (error) {
            if (forceResume) {
                // Intencional: forzar intento de reproduccion en interaccion del usuario.
            }
            return false;
        }
    }

    if (hasPickupAlert && canPlayPickupAlertSound && shouldPlayPickupSoundOnEntry) {
        setTimeout(() => {
            playPickupWhistleSound();
        }, 220);

        const unlockAndPlay = async () => {
            const played = await playPickupWhistleSound(true);
            if (played) {
                ['click', 'touchstart', 'keydown'].forEach((eventName) => {
                    document.removeEventListener(eventName, unlockAndPlay);
                });
            }
        };

        ['click', 'touchstart', 'keydown'].forEach((eventName) => {
            document.addEventListener(eventName, unlockAndPlay);
        });

        const manualSoundBtn = document.getElementById('pickupAlertSoundBtn');
        if (manualSoundBtn) {
            manualSoundBtn.addEventListener('click', async () => {
                const played = await playPickupWhistleSound(true);
                if (played) {
                    manualSoundBtn.textContent = 'Sonando...';
                }
            });
        }

        let attempts = 0;
        pickupAlertRetryTimer = setInterval(async () => {
            attempts += 1;
            const played = await playPickupWhistleSound(true);
            if (played || attempts >= 12) {
                clearInterval(pickupAlertRetryTimer);
                pickupAlertRetryTimer = null;
            }
        }, 500);
    }
</script>
