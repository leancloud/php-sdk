<?php

namespace LeanCloud\IM;

use LeanCloud\Client;

/**
 * 对话 群聊|单聊
 * @link https://leancloud.cn/docs/realtime_rest_api_v2.html#hash1188687
 * @package LeanCloud
 */
class Conversations
{
    /**
     * @var string|null
     */
    protected $conversationId = null;

    public function __construct($conversationId)
    {
        $this->conversationId = $conversationId;
    }

    public static function useApiVersionCall(
        $apiVersion,
        callable $callback
    )
    {
        $previous_version = Client::getApiVersion();
        Client::setApiVersion($apiVersion);

        try {
            $result = $callback();
        } finally {
            Client::setApiVersion($previous_version);
        }

        return $result;
    }

    /**
     * 获取对话id
     * @return null|string
     */
    public function getConversationId()
    {
        return $this->conversationId;
    }

    /**
     * 创建群聊
     * @param $name
     * @param array $members
     * @return static
     * @throws IMException
     */
    public static function create($name, $members = [])
    {
        $response = self::useApiVersionCall('1.2', function () use ($name, $members) {
            return Client::post('/rtm/conversations', [
                'name' => trim($name),
                'm' => $members
            ]);
        });

        if (empty($response['objectId'])) {
            throw new IMException(var_export($response, true));
        }

        return new static($response['objectId']);
    }

    public function addMembers($members = [])
    {
        $response = self::useApiVersionCall('1.2', function () use ($members) {
            $charRoomId = $this->getConversationId();
            return Client::post("/rtm/conversations/{$charRoomId}/members", [
                'client_ids' => $members
            ]);
        });

        return $response;
    }
}

