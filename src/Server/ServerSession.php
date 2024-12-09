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
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\LoggingLevel;
use Mcp\Types\ClientCapabilities;
use Mcp\Types\InitializeRequestParams;
use Mcp\Types\Implementation;
use Mcp\Shared\Version;
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
    private ?InitializeRequestParams $clientParams = null;
    private LoggerInterface $logger;

    public function __construct(
        private readonly Transport $transport,
        private readonly InitializationOptions $initOptions,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        parent::__construct();
    }

    public function start(): void {
        if ($this->isInitialized) {
            throw new \RuntimeException('Session already initialized');
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
    public function checkClientCapability(ClientCapabilities $capability): bool {
        if ($this->clientParams === null) {
            return false;
        }

        $clientCaps = $this->clientParams->capabilities;

        // Check roots capability
        if ($capability->roots !== null) {
            if ($clientCaps->roots === null) {
                return false;
            }
            if ($capability->roots->listChanged && !$clientCaps->roots->listChanged) {
                return false;
            }
        }

        // Check sampling capability
        if ($capability->sampling !== null) {
            if ($clientCaps->sampling === null) {
                return false;
            }
        }

        // Check experimental capabilities
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
            throw new \RuntimeException('Received request before initialization was complete');
        }

        parent::handleRequest($message);
    }

    protected function handleNotification(JsonRpcMessage $message): void {
        if ($message->method === 'notifications/initialized') {
            $this->initializationState = InitializationState::Initialized;
            return;
        }

        if ($this->initializationState !== InitializationState::Initialized) {
            throw new \RuntimeException('Received notification before initialization was complete');
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

    /**
     * Send a log message notification
     */
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

    /**
     * Send a resource updated notification
     */
    public function sendResourceUpdated(string $uri): void {
        $notification = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: 'notifications/resources/updated',
            params: ['uri' => $uri]
        );

        $this->transport->writeMessage($notification);
    }

    /**
     * Send a progress notification
     */
    public function sendProgressNotification(
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

    /**
     * Send various list changed notifications
     */
    public function sendResourceListChanged(): void {
        $this->sendNotification('notifications/resources/list_changed');
    }

    public function sendToolListChanged(): void {
        $this->sendNotification('notifications/tools/list_changed');
    }

    public function sendPromptListChanged(): void {
        $this->sendNotification('notifications/prompts/list_changed');
    }

    private function sendNotification(string $method, ?array $params = null): void {
        $notification = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: $method,
            params: $params
        );

        $this->transport->writeMessage($notification);
    }
}