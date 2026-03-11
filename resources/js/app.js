import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

document.addEventListener('alpine:init', () => {
    Alpine.store('notifPanel', {
        open: false,
        hasNew: false,

        addToast(label, body) {
            window.dispatchEvent(new CustomEvent('notification-toast', {
                detail: { title: label, message: body },
            }));
        },
    });
});

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
