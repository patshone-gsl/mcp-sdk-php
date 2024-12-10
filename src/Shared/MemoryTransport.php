<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2024 Logiscape LLC <https://logiscape.com>
 *
 * Based on the Python SDK for the Model Context Protocol
 * https://github.com/modelcontextprotocol/python-sdk
 *
 * PHP conversion developed by:
 * - Josh Abbott
 * - Claude 3.5 Sonnet (Anthropic AI model)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    logiscape/mcp-sdk-php 
 * @author     Josh Abbott <https://joshabbott.com>
 * @copyright  Logiscape LLC
 * @license    MIT License
 * @link       https://github.com/logiscape/mcp-sdk-php
 *
 * Filename: Shared/MemoryTransport.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\JsonRpcMessage;
use Mcp\Client\ClientSession;
use Mcp\Server\Server;

/**
 * Manager for memory-based client-server communication
 */
class MemoryTransport {
    /**
     * Creates a pair of bidirectional memory streams for client-server communication.
     * 
     * @return array{array{MemoryStream, MemoryStream}, array{MemoryStream, MemoryStream}}
     */
    public static function createClientServerStreams(): array {
        $serverToClient = new MemoryStream();
        $clientToServer = new MemoryStream();

        $clientStreams = [$serverToClient, $clientToServer];
        $serverStreams = [$clientToServer, $serverToClient];

        return [$clientStreams, $serverStreams];
    }

    /**
     * Creates a ClientSession connected to a running MCP server.
     */
    public static function createConnectedSession(
        Server $server,
        ?int $readTimeout = null,
        bool $raiseExceptions = false
    ): ClientSession {
        [$clientStreams, $serverStreams] = self::createClientServerStreams();
        [$clientRead, $clientWrite] = $clientStreams;
        [$serverRead, $serverWrite] = $serverStreams;

        $clientSession = new ClientSession(
            $clientRead,
            $clientWrite,
            $readTimeout
        );

        $server->run(
            $serverRead,
            $serverWrite,
            $server->createInitializationOptions(),
            $raiseExceptions
        );

        $clientSession->initialize();

        return $clientSession;
    }
}