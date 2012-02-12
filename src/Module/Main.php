<?php

namespace Ircbot\Module;

use \Ircbot\Application as Ircbot;

class Main extends AModule
{
    public $events = array(
        'onRawdata'   => 'onRawdata',
        'onPing'      => 'onPing',
        'on001'       => 'onConnect',
        'on375'       => 'onMOTDStart',
        'on372'       => 'onMOTD',
        'on332'       => 'onTopic',
        'on333'       => 'onTopicWhoTime',
        'on366'       => 'onEndNames',
        'on004'       => 'onMyInfo',
        'onJoin'      => 'onJoin',
        'onPart'      => 'onPart',
        'onTopic'     => 'onTopic',
        'onError'     => 'onError',
        'onNameReply' => 'onNameReply',
        'onISupport'  => 'onISupport',
        'loopIterate' => 'loopIterate',
        'SIGINT'      => 'onSIGINT',
    );
    
    private $_tmp = array();

    public function onRawdata($data)
    {
        list($rawdata, $queue) = $data;
        $parser = new \Ircbot\Parser\Irc;
        $data = $parser($rawdata);
        if ($data) {
            $queue->addEntry($data);
        }
    }
    
    public function loopIterate()
    {
        Ircbot::getInstance()->getSignalHandler()->getSignals();
    }
    
    public function onPing(\Ircbot\Command\Ping $ping)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($ping->botId);
        $pong = new \Ircbot\Command\Pong();
        $pong->code = $ping->code;
        $bot->sendRawData($pong);
    }
    public function onConnect(\Ircbot\Numeric\Numeric $numeric)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($numeric->botId);
        $bot->isConnected = true;
        Ircbot::getInstance()->getEventHandler()
            ->raiseEvent('onConnect', $numeric->botId);
    }
    public function onMOTDStart(\Ircbot\Numeric\Numeric $numeric)
    {
        Ircbot::getInstance()->getBotHandler()
            ->getBotById($numeric->botId)->serverMOTD = array();
    }
    public function onMOTD(\Ircbot\Numeric\Numeric $numeric)
    {
        Ircbot::getInstance()->getBotHandler()
            ->getBotById($numeric->botId)->serverMOTD[] = $numeric->message;
    }
    public function onJoin(\Ircbot\Command\Join $join)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($join->botId);
        $channelHandler = Ircbot::getInstance()
            ->getChannelHandler();
        $channel = $channelHandler->getChan($join->channel, $join->botId);
        if (!$channel) {
            $channel = $channelHandler->addChan($join->channel, $join->botId);
        }
        if (strtolower($join->mask->nickname) == strtolower($bot->nickname)) {
            $channel->hasBot = true;
        }
    }
    public function onPart(\Ircbot\Command\Part $part)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($part->botId);
        $channelHandler = Ircbot::getInstance()
            ->getChannelHandler();
        $channel = $channelHandler->getChan($part->channel, $part->botId);
        if (!$channel) {
            $channel = $channelHandler->addChan($part->channel, $part->botId);
        }
        if (strtolower($part->mask->nickname) == strtolower($bot->nickname)) {
            $channelHandler->delChan($part->channel, $part->botId);
        }
    }
    public function onTopic($data)
    {
        if ($data instanceof \Ircbot\Numeric\Numeric) {
            $topic = new \Ircbot\Type\Topic();
            $topic->message = substr(\Ircbot\Utility\String::token('1-'), 1);
            $hash = md5(
                'topic_' . $data->botId . \Ircbot\Utility\String::token('0')
            );
            $this->_tmp[$hash] = $topic;
        } elseif ($data instanceof \Ircbot\Command\Topic) {
            $topic = new \Ircbot\Type\Topic();
            $topic->who = $data->who;
            $topic->message = $data->message;
            $topic->timestamp = time();
            $channel = Ircbot::getInstance()->getChannelHandler()
                ->getChan($data->channel, $data->botId);
            $channel->topic = $topic;
            print_r($channel);
        }
    }
    public function onTopicWhoTime(\Ircbot\Numeric\Numeric $numeric)
    {
        $hash = md5(
            'topic_' . $numeric->botId . \Ircbot\Utility\String::token('0')
        );
        /* @var $topic IRCBot_Types_Topic */
        $topic = $this->_tmp[$hash];
        $topic->who = \Ircbot\Utility\String::token('1');
        $topic->timestamp = \IrcbotUtility\String::token('2');
        unset($this->_tmp[$hash]);
        $channel = Ircbot::getInstance()->getChannelHandler()
            ->getChan(\Ircbot\Utility\String::token('0'), $numeric->botId);
        $channel->topic = $topic;
    }
    public function onError(\Ircbot\Command\Error $error)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($error->botId);
        $bot->disconnected();
    }
    public function onSIGINT()
    {
        Ircbot::getInstance()->getBotHandler()
            ->allBotsExecute(
                'sendRawData',
                new \Ircbot\Command\Quit('Received SIGINT signal')
            );
        Ircbot::getInstance()->getBotHandler()
            ->allBotsExecute('handleQueueOut', true);
        exit();
    }
    public function onNameReply(\Ircbot\Numeric\NameReply $nameReply)
    {
        $hash = md5('names_' . $nameReply->botId . $nameReply->channel);
        $this->_tmp[$hash][] = $nameReply;
    }
    public function onEndNames(\Ircbot\Numeric\Numeric $numeric)
    {
        $hash = md5(
            'names_' . $numeric->botId . \Ircbot\Utility\String::token('0')
        );
        $names = array();
        foreach ($this->_tmp[$hash] as $nameReply) {
            $names = array_merge($names, $nameReply->names);
        }
        $channel = Ircbot::getInstance()->getChannelHandler()
            ->getChan(\Ircbot\Utility\String::token('0'), $numeric->botId);
        $channel->nicklist = $names;
        unset($this->_tmp[$hash]);
        Ircbot::getInstance()->getEventHandler()
            ->raiseEvent('channelReady', $numeric);
    }
    public function onISupport(\Ircbot\Numeric\ISupport $numeric)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($numeric->botId);
        $bot->currentNetwork->addISupport($numeric);
    }
    public function onMyInfo(\Ircbot\Numeric\Numeric $numeric)
    {
        $bot = Ircbot::getInstance()->getBotHandler()
            ->getBotById($numeric->botId);
        $bot->currentNetwork->hostname = \Ircbot\Utility\String::token('0');
    }
}
