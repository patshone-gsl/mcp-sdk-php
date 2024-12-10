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
 * Filename: Client/ClientSession.php
 */

declare(strict_types=1);

namespace Mcp\Client;

use Mcp\Shared\BaseSession;
use Mcp\Shared\Version;
use Mcp\Shared\MemoryStream;
use Mcp\Types\ClientRequest;
use Mcp\Types\ClientNotification;
use Mcp\Types\ServerRequest;
use Mcp\Types\ServerNotification;
use Mcp\Types\InitializeRequest;
use Mcp\Types\InitializeResult;
use Mcp\Types\EmptyResult;
use Mcp\Types\Implementation;
use Mcp\Types\ClientCapabilities;
use Mcp\Types\ClientRootsCapability;
use Mcp\Types\LoggingLevel;
use Mcp\Types\ProgressToken;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\CallToolResult;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\CompleteResult;
use Mcp\Types\ResourceReference;
use Mcp\Types\PromptReference;
use Mcp\Types\InitializedNotification;
use Mcp\Types\ProgressNotification;
use Mcp\Types\PingRequest;
use Mcp\Types\RootsListChangedNotification;
use RuntimeException;

/**
 * Client session for MCP communication
 */
class ClientSession extends BaseSession {
    public function __construct(
        private MemoryStream $readStream,
        private MemoryStream $writeStream,
        private ?float $readTimeout = null,
    ) {
        parent::__construct(
            receiveRequestType: ServerRequest::class,
            receiveNotificationType: ServerNotification::class
        );
    }

    /**
     * Initialize the client session
     */
    private ?InitializeResult $initResult = null;
    
    public function initialize(): void {
        $request = new InitializeRequest(
            capabilities: new ClientCapabilities(
                roots: new ClientRootsCapability(listChanged: true),
                sampling: null,
                experimental: null
            ),
            clientInfo: new Implementation(
                name: 'mcp',
                version: '0.1.0'
            ),
            protocolVersion: Version::LATEST_PROTOCOL_VERSION
        );
    
        $result = $this->sendRequest($request, InitializeResult::class);
    
        if (!in_array($result->protocolVersion, Version::SUPPORTED_PROTOCOL_VERSIONS)) {
            throw new RuntimeException(
                "Unsupported protocol version from the server: {$result->protocolVersion}"
            );
        }
    
        $notification = new InitializedNotification();
        $this->sendNotification($notification);
    
        // Store the result internally, don't return it.
        $this->initResult = $result;
    
        // Call the parent initialize, which sets isInitialized and starts message processing.
        parent::initialize();
    }
    
    // Provide a getter if you need access to the InitializeResult later
    public function getInitializeResult(): InitializeResult {
        if ($this->initResult === null) {
            throw new RuntimeException('Session not yet initialized');
        }
        return $this->initResult;
    }

    /**
     * Send a ping request
     */
    public function sendPing(): EmptyResult {
        $request = new PingRequest();
        return $this->sendRequest($request, EmptyResult::class);
    }

    /**
     * Send a progress notification
     */
    public function sendProgressNotification(
        ProgressToken $progressToken,
        float $progress,
        ?float $total = null
    ): void {
        $notification = new ProgressNotification(
            progressToken: $progressToken,
            progress: $progress,
            total: $total
        );
        $this->sendNotification($notification);
    }

    /**
     * Set the logging level
     */
    public function setLoggingLevel(LoggingLevel $level): EmptyResult {
        $request = new \Mcp\Types\SetLevelRequest($level);
        return $this->sendRequest($request, EmptyResult::class);
    }

    /**
     * List available resources
     */
    public function listResources(): ListResourcesResult {
        $request = new \Mcp\Types\ListResourcesRequest();
        return $this->sendRequest($request, ListResourcesResult::class);
    }

    /**
     * Read a specific resource
     */
    public function readResource(string $uri): ReadResourceResult {
        $request = new \Mcp\Types\ReadResourceRequest($uri);
        return $this->sendRequest($request, ReadResourceResult::class);
    }

    /**
     * Subscribe to resource updates
     */
    public function subscribeResource(string $uri): EmptyResult {
        $request = new \Mcp\Types\SubscribeRequest($uri);
        return $this->sendRequest($request, EmptyResult::class);
    }

    /**
     * Unsubscribe from resource updates
     */
    public function unsubscribeResource(string $uri): EmptyResult {
        $request = new \Mcp\Types\UnsubscribeRequest($uri);
        return $this->sendRequest($request, EmptyResult::class);
    }

    /**
     * Call a tool
     */
    public function callTool(string $name, ?array $arguments = null): CallToolResult {
        $request = new \Mcp\Types\CallToolRequest($name, $arguments);
        return $this->sendRequest($request, CallToolResult::class);
    }

    /**
     * List available prompts
     */
    public function listPrompts(): ListPromptsResult {
        $request = new \Mcp\Types\ListPromptsRequest();
        return $this->sendRequest($request, ListPromptsResult::class);
    }

    /**
     * Get a specific prompt
     */
    public function getPrompt(string $name, ?array $arguments = null): GetPromptResult {
        $request = new \Mcp\Types\GetPromptRequest($name, $arguments);
        return $this->sendRequest($request, GetPromptResult::class);
    }

    /**
     * Get completion suggestions
     */
    public function complete(
        ResourceReference|PromptReference $ref,
        array $argument
    ): CompleteResult {
        $request = new \Mcp\Types\CompleteRequest($argument, $ref);
        return $this->sendRequest($request, CompleteResult::class);
    }

    /**
     * List available tools
     */
    public function listTools(): ListToolsResult {
        $request = new \Mcp\Types\ListToolsRequest();
        return $this->sendRequest($request, ListToolsResult::class);
    }

    /**
     * Send a notification that the roots list has changed
     */
    public function sendRootsListChanged(): void {
        $notification = new RootsListChangedNotification();
        $this->sendNotification($notification);
    }

    protected function getReadTimeout(): ?float {
        return $this->readTimeout;
    }
    
    protected function startMessageProcessing(): void {
        // No background processing needed for now.
        // If you later implement async message loops, start them here.
    }
    
    protected function stopMessageProcessing(): void {
        // Stop any ongoing background processing if you add it in the future.
    }
    
    protected function writeMessage(\Mcp\Types\JsonRpcMessage $message): void {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON message: ' . json_last_error_msg());
        }
        $this->writeStream->send($json . "\n");
    }
    
    protected function waitForResponse(int $requestId, string $resultType): mixed {
        $timeout = $this->getReadTimeout();
        $startTime = microtime(true);
    
        while (true) {
            if ($timeout !== null && (microtime(true) - $startTime) >= $timeout) {
                throw new \RuntimeException("Timed out waiting for response to request $requestId");
            }
    
            $message = $this->readStream->receive();
    
            if ($message === null) {
                // No message yet, sleep briefly to prevent busy-waiting
                usleep(10000); 
                continue;
            }
    
            if ($message instanceof \Exception) {
                // The stream returned an exception as a message
                throw $message;
            }
    
            // Expecting $message to be a JSON string representing a JSON-RPC message
            $data = json_decode($message, true);
            if ($data === null) {
                throw new \RuntimeException("Invalid JSON message received: $message");
            }
    
            // Construct a JsonRpcMessage object
            $jsonRpcMessage = new \Mcp\Types\JsonRpcMessage(
                jsonrpc: $data['jsonrpc'] ?? '2.0',
                id: isset($data['id']) ? new \Mcp\Types\RequestId($data['id']) : null,
                method: $data['method'] ?? null,
                params: $data['params'] ?? null,
                result: $data['result'] ?? null,
                error: $data['error'] ?? null
            );
    
            // Process this message through the base session handler.
            // If it's a response, handleIncomingMessage() will invoke the response handler.
            $this->handleIncomingMessage($jsonRpcMessage);
    
            // Check if this message is a response to our request.
            if ($jsonRpcMessage->id !== null && $jsonRpcMessage->id->getValue() === $requestId) {
                // If there's an error, throw an exception.
                if ($jsonRpcMessage->error !== null) {
                    $code = $jsonRpcMessage->error['code'] ?? 0;
                    $msg = $jsonRpcMessage->error['message'] ?? 'Unknown error';
                    throw new \RuntimeException("Server returned an error: [{$code}] {$msg}");
                }
    
                // If there’s a result, construct the result object.
                // Many of your result classes (like InitializeResult, etc.) appear to accept arrays
                // in their constructors. Adjust as needed if the actual constructor signatures differ.
                if ($jsonRpcMessage->result === null) {
                    // Possibly an EmptyResult or no data needed.
                    if ($resultType === \Mcp\Types\EmptyResult::class) {
                        return new \Mcp\Types\EmptyResult();
                    }
                    return null;
                }
    
                // Construct the result type using the data in $jsonRpcMessage->result.
                // Assuming the result array keys match the constructor parameters of $resultType.
                // If the constructors differ, you'll need to manually map fields.
                if (is_array($jsonRpcMessage->result)) {
                    return new $resultType(...$jsonRpcMessage->result);
                } else {
                    // If result is not an array, you may need to handle it differently.
                    // This is a fallback scenario.
                    return new $resultType($jsonRpcMessage->result);
                }
            }
        }
    }
    
}