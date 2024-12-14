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
use Mcp\Types\InitializeRequestParams;
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
use Mcp\Types\JsonRpcMessage;
use RuntimeException;

/**
 * Client session for MCP communication
 *
 * The client interacts with a server by sending requests and notifications, and receiving responses.
 */
class ClientSession extends BaseSession {
    private ?InitializeResult $initResult = null;
    private bool $initialized = false;

    public function __construct(
        private MemoryStream $readStream,
        private MemoryStream $writeStream,
        private ?float $readTimeout = null,
    ) {
        // Client receives ServerRequest and ServerNotification from the server
        parent::__construct(
            receiveRequestType: ServerRequest::class,
            receiveNotificationType: ServerNotification::class
        );
    }

    /**
     * Initialize the client session by sending an InitializeRequest and then an InitializedNotification.
     */
    public function initialize(): void {
        $request = new InitializeRequest(
            method: "initialize",
            params: new InitializeRequestParams(
                protocolVersion: Version::LATEST_PROTOCOL_VERSION,
                capabilities: new ClientCapabilities(
                    roots: new ClientRootsCapability(listChanged: true)
                ),
                clientInfo: new Implementation(
                    name: 'mcp',
                    version: '0.1.0'
                )
            )
        );

        /** @var InitializeResult $result */
        $result = $this->sendRequest($request, InitializeResult::class);

        if (!in_array($result->protocolVersion, Version::SUPPORTED_PROTOCOL_VERSIONS)) {
            throw new RuntimeException(
                "Unsupported protocol version from the server: {$result->protocolVersion}"
            );
        }

        // Send InitializedNotification
        $notification = new InitializedNotification(
            method: "notifications/initialized"
        );
        $this->sendNotification($notification);

        $this->initResult = $result;
        $this->initialized = true;
        $this->startMessageProcessing();
    }

    public function getInitializeResult(): InitializeResult {
        if ($this->initResult === null) {
            throw new RuntimeException('Session not yet initialized');
        }
        return $this->initResult;
    }

    public function sendPing(): EmptyResult {
        $request = new PingRequest(method: "ping");
        return $this->sendRequest($request, EmptyResult::class);
    }

    public function sendProgressNotification(
        ProgressToken $progressToken,
        float $progress,
        ?float $total = null
    ): void {
        $notification = new ProgressNotification(
            method: "notifications/progress",
            params: [
                'progressToken' => $progressToken,
                'progress' => $progress,
                'total' => $total
            ]
        );
        $this->sendNotification($notification);
    }

    public function setLoggingLevel(LoggingLevel $level): EmptyResult {
        $request = new \Mcp\Types\SetLevelRequest(
            method: "logging/setLevel",
            params: ['level' => $level]
        );
        return $this->sendRequest($request, EmptyResult::class);
    }

    public function listResources(): ListResourcesResult {
        $request = new \Mcp\Types\ListResourcesRequest(method: "resources/list");
        return $this->sendRequest($request, ListResourcesResult::class);
    }

    public function readResource(string $uri): ReadResourceResult {
        $request = new \Mcp\Types\ReadResourceRequest(
            method: "resources/read",
            params: ['uri' => $uri]
        );
        return $this->sendRequest($request, ReadResourceResult::class);
    }

    public function subscribeResource(string $uri): EmptyResult {
        $request = new \Mcp\Types\SubscribeRequest(
            method: "resources/subscribe",
            params: ['uri' => $uri]
        );
        return $this->sendRequest($request, EmptyResult::class);
    }

    public function unsubscribeResource(string $uri): EmptyResult {
        $request = new \Mcp\Types\UnsubscribeRequest(
            method: "resources/unsubscribe",
            params: ['uri' => $uri]
        );
        return $this->sendRequest($request, EmptyResult::class);
    }

    public function callTool(string $name, ?array $arguments = null): CallToolResult {
        $request = new \Mcp\Types\CallToolRequest(
            method: "tools/call",
            params: [
                'name' => $name,
                'arguments' => $arguments
            ]
        );
        return $this->sendRequest($request, CallToolResult::class);
    }

    public function listPrompts(): ListPromptsResult {
        $request = new \Mcp\Types\ListPromptsRequest(method: "prompts/list");
        return $this->sendRequest($request, ListPromptsResult::class);
    }

    public function getPrompt(string $name, ?array $arguments = null): GetPromptResult {
        $request = new \Mcp\Types\GetPromptRequest(
            method: "prompts/get",
            params: [
                'name' => $name,
                'arguments' => $arguments
            ]
        );
        return $this->sendRequest($request, GetPromptResult::class);
    }

    public function complete(
        ResourceReference|PromptReference $ref,
        array $argument
    ): CompleteResult {
        $request = new \Mcp\Types\CompleteRequest(
            method: "completion/complete",
            params: [
                'ref' => $ref,
                'argument' => $argument
            ]
        );
        return $this->sendRequest($request, CompleteResult::class);
    }

    public function listTools(): ListToolsResult {
        $request = new \Mcp\Types\ListToolsRequest(method: "tools/list");
        return $this->sendRequest($request, ListToolsResult::class);
    }

    public function sendRootsListChanged(): void {
        $notification = new RootsListChangedNotification(
            method: "notifications/roots/list_changed"
        );
        $this->sendNotification($notification);
    }

    public function receiveMessage(): JsonRpcMessage|\Exception|null {
        $msg = $this->readStream->receive();
        return $msg; // The transport already returns JsonRpcMessage or Exception or null
    }

    protected function getReadTimeout(): ?float {
        return $this->readTimeout;
    }

    protected function startMessageProcessing(): void {
        // If you want to start a background thread or event loop for incoming messages, do it here.
        // Currently, we read messages in waitForResponse().
    }

    protected function stopMessageProcessing(): void {
        // Stop any ongoing background processing if implemented in the future.
    }

    protected function writeMessage(\Mcp\Types\JsonRpcMessage $message): void {
        // Directly send the JsonRpcMessage to the writeStream.
        $this->writeStream->send($message);
    }

    protected function waitForResponse(int $requestId, string $resultType): mixed {
        $timeout = $this->getReadTimeout();
        $startTime = microtime(true);

        while (true) {
            if ($timeout !== null && (microtime(true) - $startTime) >= $timeout) {
                throw new RuntimeException("Timed out waiting for response to request $requestId");
            }

            $message = $this->readStream->receive();
            if ($message === null) {
                usleep(10000);
                continue;
            }

            if ($message instanceof \Exception) {
                // If the readStream returned an exception, rethrow it
                throw $message;
            }

            // $message should be a JsonRpcMessage
            if (!$message instanceof JsonRpcMessage) {
                throw new RuntimeException("Invalid message type received from readStream");
            }

            // If this message has an id and matches our requestId, it might be the response.
            if ($message->id !== null && $message->id->getValue() === $requestId) {
                // Check if it's an error response
                if ($message->error !== null) {
                    $code = $message->error['code'] ?? 0;
                    $msg = $message->error['message'] ?? 'Unknown error';
                    throw new RuntimeException("Server returned an error: [{$code}] {$msg}");
                }

                // If it's a success response, construct the result object
                if ($message->result === null) {
                    // Possibly EmptyResult or no data
                    if ($resultType === \Mcp\Types\EmptyResult::class) {
                        return new \Mcp\Types\EmptyResult();
                    }
                    return null;
                }

                // Construct the $resultType object from $message->result
                // If $message->result is an array, use argument unpacking
                if (is_array($message->result)) {
                    return new $resultType(...$message->result);
                } else {
                    // If result is not an array, just pass it directly if constructor allows it
                    return new $resultType($message->result);
                }
            }

            // If not our response, we can call handleIncomingMessage() to process server requests/notifications
            $this->handleIncomingMessage($message);
            // Then loop again until we find our response.
        }
    }
}