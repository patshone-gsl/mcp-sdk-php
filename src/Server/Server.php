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
 * - ChatGPT o1 pro mode
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
use Mcp\Types\LoggingLevel;
use Mcp\Types\RequestId;
use Mcp\Shared\McpError;
use Mcp\Shared\ErrorData as TypesErrorData;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JsonRpcErrorObject;
use Mcp\Types\Result;
use Mcp\Shared\ErrorData;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * MCP Server implementation
 *
 * This class manages request and notification handlers, integrates with ServerSession,
 * and handles incoming messages by dispatching them to the appropriate handlers.
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

        // Register built-in ping handler: returns an EmptyResult as per schema
        $this->requestHandlers['ping'] = function (?array $params): Result {
            // Ping returns an EmptyResult according to the schema
            return new Result();
        };
    }

    /**
     * Creates initialization options for the server.
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
     * Gets server capabilities based on registered handlers.
     */
    public function getCapabilities(
        NotificationOptions $notificationOptions,
        array $experimentalCapabilities
    ): ServerCapabilities {
        // Check which handlers are registered to determine capabilities
        $promptsCapability = null;
        $resourcesCapability = null;
        $toolsCapability = null;
        $loggingCapability = null;

        if (isset($this->requestHandlers['prompts/list'])) {
            // This implies we can list prompts and maybe handle prompt changes
            $promptsCapability = [
                'listChanged' => $notificationOptions->promptsChanged
            ];
        }

        if (isset($this->requestHandlers['resources/list'])) {
            $resourcesCapability = [
                'subscribe' => false,
                'listChanged' => $notificationOptions->resourcesChanged
            ];
        }

        if (isset($this->requestHandlers['tools/list'])) {
            $toolsCapability = [
                'listChanged' => $notificationOptions->toolsChanged
            ];
        }

        if (isset($this->requestHandlers['logging/setLevel'])) {
            $loggingCapability = []; // some logging capabilities defined
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
     * Registers a request handler for a given method.
     *
     * The handler should return a `Result` object or throw `McpError`.
     */
    public function registerHandler(string $method, callable $handler): void {
        $this->requestHandlers[$method] = $handler;
        $this->logger->debug("Registered handler for request method: $method");
    }

    /**
     * Registers a notification handler for a given method.
     *
     * The handler does not return a result, just processes the notification.
     */
    public function registerNotificationHandler(string $method, callable $handler): void {
        $this->notificationHandlers[$method] = $handler;
        $this->logger->debug("Registered notification handler for method: $method");
    }

    /**
     * Processes an incoming message from the client.
     */
    public function handleMessage(JsonRpcMessage $message): void {
        $this->logger->debug("Received message: " . json_encode($message));

        // Determine if request or notification by checking if $message->id is set
        if ($message->method === null) {
            // This is a response message from client, server typically doesn't wait on client responses
            return;
        }

        try {
            if ($message->id !== null) {
                // It's a request
                $handler = $this->requestHandlers[$message->method] ?? null;
                if ($handler === null) {
                    throw new McpError(new TypesErrorData(
                        -32601,
                        "Method not found: {$message->method}"
                    ));
                }

                // Handlers take params and return a Result object or throw McpError
                $params = $message->params ?? null;
                $result = $handler($params);
                if (!$result instanceof Result) {
                    // If the handler doesn't return a Result, wrap it in a Result or throw error
                    // According to schema, result must be a Result object.
                    $resultObj = new Result();
                    // Populate $resultObj if $result is something else
                    // For simplicity, if handler returned array or null, just do nothing special
                    $result = $resultObj;
                }

                $this->sendResponse($message->id, $result);
            } else {
                // It's a notification
                $handler = $this->notificationHandlers[$message->method] ?? null;
                if ($handler !== null) {
                    $params = $message->params ?? null;
                    $handler($params);
                }
            }
        } catch (McpError $e) {
            if ($message->id !== null) {
                $this->sendError($message->id, $e->error);
            }
        } catch (\Exception $e) {
            $this->logger->error("Error handling message: " . $e->getMessage());
            if ($message->id !== null) {
                // Code 0 is a generic error code, or we could choose another code per the schema
                $this->sendError($message->id, new ErrorData(
                    code: 0,
                    message: $e->getMessage()
                ));
            }
        }
    }

    /**
     * Built-in ping handler (already set in the constructor)
     * Just returns an empty result (which we already do in constructor)
     */

    private function sendResponse(RequestId $id, Result $result): void {
        if (!$this->session) {
            throw new RuntimeException('No active session');
        }

        // Create a JSONRPCResponse object and wrap in JsonRpcMessage
        $resp = new JSONRPCResponse(
            jsonrpc: '2.0',
            id: $id,
            result: $result
        );
        $resp->validate();

        $msg = new JsonRpcMessage($resp);
        $this->session->writeMessage($msg);
    }

    private function sendError(RequestId $id, ErrorData $error): void {
        if (!$this->session) {
            throw new RuntimeException('No active session');
        }

        $errorObj = new JsonRpcErrorObject(
            code: $error->code,
            message: $error->message,
            data: $error->data ?? null
        );

        $errResp = new JSONRPCError(
            jsonrpc: '2.0',
            id: $id,
            error: $errorObj
        );
        $errResp->validate();

        $msg = new JsonRpcMessage($errResp);
        $this->session->writeMessage($msg);
    }

    private function getPackageVersion(string $package): string {
        // Return a static version. Actual implementation can read from composer.json or elsewhere.
        return '1.0.0';
    }

    public function setSession(ServerSession $session): void {
        $this->session = $session;
    }
}

class NotificationOptions {
    public function __construct(
        public bool $promptsChanged = false,
        public bool $resourcesChanged = false,
        public bool $toolsChanged = false,
    ) {}
}