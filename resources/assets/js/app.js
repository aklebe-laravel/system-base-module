// pusher
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

if (import.meta.env.BROADCAST_DRIVER === 'pusher') {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true
    });
}

import CliConsole from './cli-console';
window.CliConsole = CliConsole;
