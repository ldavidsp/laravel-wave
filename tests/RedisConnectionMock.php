<?php

namespace Qruto\LaravelWave\Tests;

use Closure;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use M6Web\Component\RedisMock\RedisMock;

/**
 * @mixin \Redis
 */
class RedisConnectionMock extends RedisMock implements Connection, Factory
{
    public static $events = [];

    public function eval($script, $numberOfKeys, ...$arguments)
    {
        if (Str::contains($script, 'publish')) {
            collect(self::$events)->keys()->each(function ($connection) use ($arguments) {
                self::$events[$connection][] = [
                    'message' => $arguments[0],
                    'pattern' => $arguments[1],
                ];
            });

            $socket = Broadcast::socket();

            if (! $socket) {
                return;
            }

            if (! isset(self::$events[$socket])) {
                self::$events[$socket] = [[
                    'message' => $arguments[0],
                    'pattern' => $arguments[1],
                ]];
            }

            return;
        }
    }

    public function psubscribe($channels, Closure $callback)
    {
        if ($channels === '*') {
            $socket = Broadcast::socket();

            if (! $socket || ! isset(self::$events[$socket])) {
                return;
            }

            Collection::make(self::$events[$socket])
                ->each(function ($event) use ($callback) {
                    $callback($event['message'], $event['pattern']);
                });
        }
    }

    public function flushEventsQueue()
    {
        self::$events = [];
    }

    public function connection($name = null)
    {
        return $this;
    }

    public function subscribe($channels, Closure $callback)
    {
        return $this;
    }

    public function command($method, array $parameters = [])
    {
        return $this;
    }

    public function disconnect()
    {
        return $this;
    }
}
