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
        MemoryStream $readStream,
        MemoryStream $writeStream,
        private ?float $readTimeout = null,
    ) {
        parent::__construct(
            readStream: $readStream,
            writeStream: $writeStream,
            receiveRequestType: ServerRequest::class,
            receiveNotificationType: ServerNotification::class
        );
    }

    /**
     * Initialize the client session
     */
    public function initialize(): InitializeResult {
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

        return $result;
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
}