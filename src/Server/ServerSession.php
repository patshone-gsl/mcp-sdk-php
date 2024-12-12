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
 * Filename: Server/ServerSession.php
 */

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Shared\BaseSession;
use Mcp\Shared\Version;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\LoggingLevel;
use Mcp\Types\Implementation;
use Mcp\Types\ClientRequest;
use Mcp\Types\ClientNotification;
use Mcp\Server\InitializationOptions;
use Mcp\Server\Transport\Transport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Server initialization states
 */
enum InitializationState: int {
    case NotInitialized = 1;
    case Initializing = 2;
    case Initialized = 3;
}

/**
 * Server-side session implementation for MCP
 */
class ServerSession extends BaseSession {
    private InitializationState $initializationState = InitializationState::NotInitialized;
    private ?object $clientParams = null;
    private LoggerInterface $logger;

    public function __construct(
        private readonly Transport $transport,
        private readonly InitializationOptions $initOptions,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        // Server receives ClientRequest and ClientNotification from the client
        parent::__construct(
            receiveRequestType: ClientRequest::class,
            receiveNotificationType: ClientNotification::class
        );
    }

    public function start(): void {
        if ($this->isInitialized) {
            throw new RuntimeException('Session already initialized');
        }

        $this->isInitialized = true;
        $this->transport->start();
    }

    public function stop(): void {
        if (!$this->isInitialized) {
            return;
        }

        $this->transport->stop();
        $this->isInitialized = false;
    }

    /**
     * Check if client supports a specific capability
     */
    public function checkClientCapability(\Mcp\Types\ClientCapabilities $capability): bool {
        if ($this->clientParams === null) {
            return false;
        }

        $clientCaps = $this->clientParams->capabilities;

        if ($capability->roots !== null) {
            if ($clientCaps->roots === null) {
                return false;
            }
            if ($capability->roots->listChanged && !$clientCaps->roots->listChanged) {
                return false;
            }
        }

        if ($capability->sampling !== null) {
            if ($clientCaps->sampling === null) {
                return false;
            }
        }

        if ($capability->experimental !== null) {
            if ($clientCaps->experimental === null) {
                return false;
            }
            foreach ($capability->experimental as $key => $value) {
                if (!isset($clientCaps->experimental[$key]) ||
                    $clientCaps->experimental[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function handleRequest(JsonRpcMessage $message): void {
        if ($message->method === 'initialize') {
            $this->handleInitialize($message);
            return;
        }

        if ($this->initializationState !== InitializationState::Initialized) {
            throw new RuntimeException('Received request before initialization was complete');
        }

        parent::handleRequest($message);
    }

    protected function handleNotification(JsonRpcMessage $message): void {
        if ($message->method === 'notifications/initialized') {
            $this->initializationState = InitializationState::Initialized;
            return;
        }

        if ($this->initializationState !== InitializationState::Initialized) {
            throw new RuntimeException('Received notification before initialization was complete');
        }

        parent::handleNotification($message);
    }

    private function handleInitialize(JsonRpcMessage $message): void {
        $this->initializationState = InitializationState::Initializing;
        $this->clientParams = $message->params;

        $response = new JsonRpcMessage(
            jsonrpc: '2.0',
            id: $message->id,
            result: [
                'protocolVersion' => Version::LATEST_PROTOCOL_VERSION,
                'capabilities' => $this->initOptions->capabilities,
                'serverInfo' => new Implementation(
                    name: $this->initOptions->serverName,
                    version: $this->initOptions->serverVersion
                )
            ]
        );

        $this->transport->writeMessage($response);
    }

    public function sendLogMessage(
        LoggingLevel $level,
        mixed $data,
        ?string $logger = null
    ): void {
        $notification = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: 'notifications/message',
            params: [
                'level' => $level,
                'data' => $data,
                'logger' => $logger
            ]
        );

        $this->transport->writeMessage($notification);
    }

    public function sendResourceUpdated(string $uri): void {
        $notification = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: 'notifications/resources/updated',
            params: ['uri' => $uri]
        );

        $this->transport->writeMessage($notification);
    }

    public function writeProgressNotification(
        string|int $progressToken,
        float $progress,
        ?float $total = null
    ): void {
        $notification = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: 'notifications/progress',
            params: [
                'progressToken' => $progressToken,
                'progress' => $progress,
                'total' => $total
            ]
        );

        $this->transport->writeMessage($notification);
    }

    public function sendResourceListChanged(): void {
        $this->writeNotification('notifications/resources/list_changed');
    }

    public function sendToolListChanged(): void {
        $this->writeNotification('notifications/tools/list_changed');
    }

    public function sendPromptListChanged(): void {
        $this->writeNotification('notifications/prompts/list_changed');
    }

    private function writeNotification(string $method, ?array $params = null): void {
        $notification = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: $method,
            params: $params
        );

        $this->transport->writeMessage($notification);
    }

    /**
     * Implementing BaseSession abstract methods
     */

    protected function startMessageProcessing(): void {
        // The ServerSession uses start() to begin transport processing, so just call it here
        $this->start();
    }

    protected function stopMessageProcessing(): void {
        // Similarly, stop the transport when message processing stops
        $this->stop();
    }

    protected function writeMessage(JsonRpcMessage $message): void {
        // Delegate to the transport
        $this->transport->writeMessage($message);
    }

    protected function waitForResponse(int $requestId, string $resultType): mixed {
        // Typically, the server does not send requests that require waiting for responses.
        // If this ever happens, you can implement logic similar to ClientSession or simply:
        throw new RuntimeException('Server does not support waiting for responses from the client.');
    }
}