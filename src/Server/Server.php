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
 * Filename: Server/Server.php
 */

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\ServerCapabilities;
use Mcp\Types\ServerPromptsCapability;
use Mcp\Types\ServerResourcesCapability;
use Mcp\Types\ServerToolsCapability;
use Mcp\Types\LoggingLevel;
use Mcp\Types\RequestId;
use Mcp\Shared\McpError;
use Mcp\Shared\ErrorData;
use Mcp\Server\ServerSession;
use Mcp\Server\InitializationOptions;
use Mcp\Server\NotificationOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Options for server notifications
 */
class NotificationOptions {
    public function __construct(
        public readonly bool $promptsChanged = false,
        public readonly bool $resourcesChanged = false,
        public readonly bool $toolsChanged = false
    ) {}
}

/**
 * MCP Server implementation
 */
class Server {
    private array $requestHandlers = [];
    private array $notificationHandlers = [];
    private ?ServerSession $session = null;
    private LoggerInterface $logger;

    public function __construct(
        private readonly string $name,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->logger->debug("Initializing server '$name'");

        // Register built-in ping handler
        $this->requestHandlers['ping'] = [$this, 'handlePing'];
    }

    /**
     * Creates initialization options for the server
     */
    public function createInitializationOptions(
        ?NotificationOptions $notificationOptions = null,
        ?array $experimentalCapabilities = null
    ): InitializationOptions {
        $notificationOptions ??= new NotificationOptions();
        $experimentalCapabilities ??= [];

        return new InitializationOptions(
            serverName: $this->name,
            serverVersion: $this->getPackageVersion('mcp'),
            capabilities: $this->getCapabilities($notificationOptions, $experimentalCapabilities)
        );
    }

    /**
     * Gets server capabilities based on registered handlers
     */
    public function getCapabilities(
        NotificationOptions $notificationOptions,
        array $experimentalCapabilities
    ): ServerCapabilities {
        $promptsCapability = null;
        $resourcesCapability = null;
        $toolsCapability = null;
        $loggingCapability = null;

        if (isset($this->requestHandlers['prompts/list'])) {
            $promptsCapability = new ServerPromptsCapability(
                listChanged: $notificationOptions->promptsChanged
            );
        }

        if (isset($this->requestHandlers['resources/list'])) {
            $resourcesCapability = new ServerResourcesCapability(
                listChanged: $notificationOptions->resourcesChanged,
                subscribe: false
            );
        }

        if (isset($this->requestHandlers['tools/list'])) {
            $toolsCapability = new ServerToolsCapability(
                listChanged: $notificationOptions->toolsChanged
            );
        }

        if (isset($this->requestHandlers['logging/setLevel'])) {
            $loggingCapability = [];
        }

        return new ServerCapabilities(
            prompts: $promptsCapability,
            resources: $resourcesCapability,
            tools: $toolsCapability,
            logging: $loggingCapability,
            experimental: $experimentalCapabilities
        );
    }

    /**
     * Registers request handlers using method registration
     */
    public function registerHandler(string $method, callable $handler): void {
        $this->requestHandlers[$method] = $handler;
        $this->logger->debug("Registered handler for $method");
    }

    /**
     * Registers notification handlers
     */
    public function registerNotificationHandler(string $method, callable $handler): void {
        $this->notificationHandlers[$method] = $handler;
        $this->logger->debug("Registered notification handler for $method");
    }

    /**
     * Processes an incoming message
     */
    public function handleMessage(JsonRpcMessage $message): void {
        $this->logger->debug("Received message: " . json_encode($message));

        if ($message->method === null) {
            return; // Response message, ignore
        }

        try {
            if ($message->id !== null) {
                // Request
                $handler = $this->requestHandlers[$message->method] ?? null;
                if ($handler === null) {
                    throw new McpError(new ErrorData(
                        -32601,
                        "Method not found: {$message->method}"
                    ));
                }

                $result = $handler($message->params ?? null);
                $this->sendResponse($message->id, $result);

            } else {
                // Notification
                $handler = $this->notificationHandlers[$message->method] ?? null;
                if ($handler !== null) {
                    $handler($message->params ?? null);
                }
            }
        } catch (McpError $e) {
            if ($message->id !== null) {
                $this->sendError($message->id, $e->error);
            }
        } catch (\Exception $e) {
            if ($message->id !== null) {
                $this->sendError($message->id, new ErrorData(
                    0,
                    $e->getMessage()
                ));
            }
            $this->logger->error("Error handling message: " . $e->getMessage());
        }
    }

    /**
     * Built-in ping handler
     */
    private function handlePing(?array $params): array {
        return [];
    }

    private function sendResponse($id, $result): void {
        if (!$this->session) {
            throw new \RuntimeException('No active session');
        }

        $response = new JsonRpcMessage(
            jsonrpc: '2.0',
            id: $id,
            result: $result
        );

        $this->session->sendMessage($response);
    }

    private function sendError($id, ErrorData $error): void {
        if (!$this->session) {
            throw new \RuntimeException('No active session');
        }

        $response = new JsonRpcMessage(
            jsonrpc: '2.0',
            id: $id,
            error: $error
        );

        $this->session->sendMessage($response);
    }

    private function getPackageVersion(string $package): string {
        // Implementation would depend on how you want to handle versioning
        return '1.0.0'; // Example version
    }

    public function setSession(ServerSession $session): void {
        $this->session = $session;
    }
}