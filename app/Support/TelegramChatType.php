<?php

namespace App\Support;

final class TelegramChatType
{

    public static function fromMadeline(array $info): string
    {
        $chat = $info['Chat'] ?? $info['chat'] ?? null;
        $user = $info['User'] ?? $info['user'] ?? null;
        $root = is_array($chat) ? $chat : (is_array($user) ? $user : $info);
        $raw  = $root['_'] ?? null;


        // ✅ If it's normalized (getPwrChat/getInfo sometimes)
        if (isset($chat['type']) && is_string($chat['type'])) {
            $t = strtolower($chat['type']);
            if (in_array($t, ['channel', 'supergroup', 'group', 'user', 'bot'], true)) {
                return $t;
            }
        }

        // ✅ Raw MTProto object

        if ($raw === 'channel') {
            return !empty($chat['megagroup']) ? 'supergroup' : 'channel';
        }

        if ($raw === 'chat') {
            return 'group';
        }

        if ($raw === 'user') {
            return !empty($chat['bot']) ? 'bot' : 'user';
        }

        return 'unknown';
    }


    public static function natureFromMtprotoChat(array $chat): array
    {
        if (($chat['_'] ?? null) === 'channel') {
            $isSupergroup = !empty($chat['megagroup']);

            return [
                'chat_type'  => $isSupergroup ? 'supergroup' : 'channel',
                'audience'   => $isSupergroup ? 'members' : 'subscribers',
                'is_channel' => !$isSupergroup, // ✅ channel => true
                'is_group'   => $isSupergroup,  // ✅ supergroup => true
            ];
        }

        if (($chat['_'] ?? null) === 'chat') {
            return [
                'chat_type'  => 'group',
                'audience'   => 'members',
                'is_channel' => false,
                'is_group'   => true,
            ];
        }

        // user/bot/unknown
        return [
            'chat_type'  => 'unknown',
            'audience'   => null,
            'is_channel' => false,
            'is_group'   => false,
        ];
    }

}
