<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

$channelPrefix = Str::studly(env('BROADCAST_CHANNEL_PREFIX'));
Broadcast::channel($channelPrefix.'{id}', function ($user, $id) {
    if (app('website_base_config')->get('broadcast.enabled', false)) {

        return true; // @todo: decide here authorized to this channel
    }

    return false;
});
