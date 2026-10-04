<?php

return [
    'local_url' => env('ADMINER_LOCAL_URL', 'http://localhost:8081'),
    'server_tunnel_command' => env(
        'ADMINER_SERVER_TUNNEL_COMMAND',
        'ssh -L 18081:127.0.0.1:18081 warungaisha@192.168.100.48',
    ),
];
