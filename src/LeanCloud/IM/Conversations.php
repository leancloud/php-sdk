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

    /**
     * 添加用户进入群聊
     * @param array $members
     * @link https://leancloud.github.io/javascript-sdk/docs/AV.Conversation.html#addMember
     * @return mixed
     */
    public function addMembers($members)
    {
        $response = self::useApiVersionCall('1.2', function () use ($members) {
            $charRoomId = $this->getConversationId();
            return Client::post("/rtm/conversations/{$charRoomId}/members", [
                'client_ids' => $members
            ]);
        });

        return $response;
    }

    /**
     *
     * @param $fromClient
     * @param $message
     * @param $options
     * @param $authOptions
     * {
     *   'useMasterKey' => '',
     *   'sessionToken' => '',
     * }
     * @return mixed
     * @link https://leancloud.github.io/javascript-sdk/docs/AV.Conversation.html#send
     */
    public function send(
        $fromClient,
        $message,
        $options = [],
        $authOptions = []
    )
    {
        $defaultOptions = [
            'transient' => false,
        ];

        $defaultAuthOptions = [
            'useMasterKey' => '',
            'sessionToken' => ''
        ];

        $authOptions = $authOptions + $defaultAuthOptions;
        $options = $options + $defaultOptions;

        return self::useApiVersionCall('1.2', function () use ($message, $fromClient, $options, $authOptions) {
            $charRoomId = $this->getConversationId();
            return Client::post("/rtm/conversations/${charRoomId}/messages", [
                'from_client' => $fromClient,
                'message' => $message,
                'transient' => $options['transient']
            ], $authOptions['sessionToken'], [], $authOptions['useMasterKey']);
        });
    }
}

