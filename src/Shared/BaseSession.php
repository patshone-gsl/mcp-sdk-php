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
 * Filename: Shared/BaseSession.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Types\ErrorData;
use Mcp\Types\ProgressToken;
use Mcp\Types\ProgressNotification;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\McpModel;
use Mcp\Types\McpError;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base session for managing MCP communication.
 *
 * This class acts as a synchronous equivalent to the Python BaseSession. It does not
 * use async/await or streaming memory objects, but tries to replicate the logic.
 *
 * Subclasses should implement the abstract methods to handle I/O and message processing.
 */
abstract class BaseSession {
    protected bool $isInitialized = false;
    /** @var array<int, callable(JsonRpcMessage):void> */
    private array $responseHandlers = [];
    /** @var callable[] */
    private array $requestHandlers = [];
    /** @var callable[] */
    private array $notificationHandlers = [];
    private int $requestId = 0;

    /**
     * @param string $receiveRequestType       A fully-qualified class name of a type implementing McpModel for incoming requests.
     * @param string $receiveNotificationType  A fully-qualified class name of a type implementing McpModel for incoming notifications.
     */
    public function __construct(
        private readonly string $receiveRequestType,
        private readonly string $receiveNotificationType,
    ) {}

    /**
     * Initializes the session and starts message processing.
     */
    public function initialize(): void {
        if ($this->isInitialized) {
            throw new RuntimeException('Session already initialized');
        }
        $this->isInitialized = true;
        $this->startMessageProcessing();
    }

    /**
     * Closes the session and stops message processing.
     */
    public function close(): void {
        if (!$this->isInitialized) {
            return;
        }
        $this->stopMessageProcessing();
        $this->isInitialized = false;
    }

    /**
     * Sends a request and waits for a typed result. If an error response is received, throws an exception.
     * @param McpModel $request A typed request object (e.g., InitializeRequest, PingRequest).
     * @param string $resultType The fully-qualified class name of the expected result type (must implement McpModel).
     * @return McpModel The validated result object.
     * @throws McpError If an error response is received.
     */
    public function sendRequest(McpModel $request, string $resultType): McpModel {
        $this->validateRequestObject($request);

        $requestIdValue = $this->requestId++;
        $requestId = new RequestId($requestIdValue);

        // Convert the typed request into a JSON-RPC request message
        // Assuming $request has public properties: method, params
        $jsonRpcRequest = new JsonRpcMessage(
            jsonrpc: '2.0',
            id: $requestId,
            method: $request->method,
            params: $request->params ?? []
        );

        // Store a handler that will be called when a response with this requestId is received
        $futureResult = null;
        $this->responseHandlers[$requestIdValue] = function (JsonRpcMessage $message) use (&$futureResult, $resultType): void {
            // Called when a response with matching requestId arrives
            if ($message->error !== null) {
                // It's an error response
                // Convert to McpError
                throw new McpError($message->error);
            } elseif ($message->result !== null) {
                // It's a success response
                // Validate the result using $resultType
                /** @var McpModel $resultInstance */
                $resultInstance = new $resultType(...$message->result);
                $resultInstance->validate();
                $futureResult = $resultInstance;
            } else {
                // Invalid response
                throw new InvalidArgumentException('Invalid JSON-RPC response received');
            }
        };

        // Send the request message
        $this->writeMessage($jsonRpcRequest);

        // Wait for the response synchronously
        return $this->waitForResponse($requestIdValue, $resultType, $futureResult);
    }

    /**
     * Sends a notification. Notifications do not expect a response.
     * @param McpModel $notification A typed notification object.
     */
    public function sendNotification(McpModel $notification): void {
        // Convert the typed notification into a JSON-RPC notification message
        $jsonRpcMessage = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: $notification->method,
            params: $notification->params ?? []
        );

        $this->writeMessage($jsonRpcMessage);
    }

    /**
     * Sends a response to a previously received request.
     * @param RequestId $requestId The request ID to respond to.
     * @param McpModel|ErrorData $response Either a typed result model or an ErrorData for an error response.
     */
    public function sendResponse(RequestId $requestId, mixed $response): void {
        if ($response instanceof ErrorData) {
            // Error response
            $message = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: $requestId,
                error: $response
            );
        } else {
            // Success result
            // Convert $response (McpModel) into an array
            // Ideally we have a consistent method: $response->toArray() or using reflection
            // Assuming public props or a jsonSerialize approach
            $resultArray = $response->jsonSerialize();
            $message = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: $requestId,
                result: $resultArray
            );
        }

        $this->writeMessage($message);
    }

    /**
     * Sends a progress notification for a request currently in progress.
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
     * Registers a callback to handle incoming requests.
     * The callback will receive a RequestResponder as argument.
     */
    public function onRequest(callable $handler): void {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Registers a callback to handle incoming notifications.
     */
    public function onNotification(callable $handler): void {
        $this->notificationHandlers[] = $handler;
    }

    /**
     * Handles an incoming message. Called by the subclass that implements message processing.
     * @param JsonRpcMessage $message The incoming message.
     */
    protected function handleIncomingMessage(JsonRpcMessage $message): void {
        $this->validateMessage($message);

        if ($message->id !== null) {
            if ($message->method !== null) {
                // It's a request
                $request = $this->validateIncomingRequest($message);

                // Validate request
                $request->validate();

                $responder = new RequestResponder(
                    requestId: $message->id,
                    params: $message->params['_meta'] ?? [],
                    request: $request,
                    session: $this
                );

                // Call onRequest handlers
                foreach ($this->requestHandlers as $handler) {
                    $handler($responder);
                }
            } else {
                // It's a response (no method)
                if (isset($this->responseHandlers[$message->id->getValue()])) {
                    $handler = $this->responseHandlers[$message->id->getValue()];
                    unset($this->responseHandlers[$message->id->getValue()]);
                    $handler($message);
                } else {
                    // Received a response for an unknown request ID
                    // Log or handle error
                }
            }
        } else if ($message->method !== null) {
            // It's a notification
            $notification = $this->validateIncomingNotification($message);
            $notification->validate();

            // Call onNotification handlers
            foreach ($this->notificationHandlers as $handler) {
                $handler($notification);
            }
        } else {
            // Invalid message: no id, no method
            throw new InvalidArgumentException('Invalid message: no id and no method');
        }
    }

    private function validateMessage(JsonRpcMessage $message): void {
        if ($message->jsonrpc !== '2.0') {
            throw new InvalidArgumentException('Invalid JSON-RPC version');
        }
    }

    private function validateRequestObject(McpModel $request): void {
        // Check if request has a method property
        if (!property_exists($request, 'method') || empty($request->method)) {
            throw new InvalidArgumentException('Request must have a method');
        }
    }

    /**
     * Converts an incoming JsonRpcMessage into a typed request object.
     * @throws InvalidArgumentException If instantiation fails.
     */
    private function validateIncomingRequest(JsonRpcMessage $message): McpModel {
        /** @var McpModel $request */
        $requestClass = $this->receiveRequestType;
        $request = new $requestClass(
            method: $message->method,
            params: $message->params ?? []
        );
        return $request;
    }

    /**
     * Converts an incoming JsonRpcMessage into a typed notification object.
     * @throws InvalidArgumentException If instantiation fails.
     */
    private function validateIncomingNotification(JsonRpcMessage $message): McpModel {
        /** @var McpModel $notification */
        $notificationClass = $this->receiveNotificationType;
        $notification = new $notificationClass(
            method: $message->method,
            params: $message->params ?? []
        );
        return $notification;
    }

    /**
     * Waits for a response with the given requestId, blocking until it arrives.
     * In a synchronous environment, this might mean reading messages from the underlying transport
     * until we find a response with the correct ID.
     *
     * @param int $requestIdValue The numeric request ID value.
     * @param string $resultType The expected result type.
     * @param McpModel|null $futureResult A reference that will be set by the response handler closure.
     * @return McpModel The result object.
     * @throws McpError If an error response is received.
     * @throws InvalidArgumentException If no result is received.
     */
    protected function waitForResponse(int $requestIdValue, string $resultType, ?McpModel &$futureResult): McpModel {
        // The handler we set above will set $futureResult when the response arrives.
        // So we run a loop reading messages until $futureResult is not null or an error is thrown.

        while ($futureResult === null) {
            $message = $this->readNextMessage();
            $this->handleIncomingMessage($message);
            // If the response handler threw an exception (McpError), it won't reach here.
            // Otherwise, we keep looping until futureResult is set.
        }

        return $futureResult;
    }

    /**
     * Reads the next message from the underlying transport.
     * This must be implemented by subclasses and should block until a message is available.
     */
    abstract protected function readNextMessage(): JsonRpcMessage;

    /**
     * Starts message processing. For a synchronous model, this might be a no-op or set up resources.
     */
    abstract protected function startMessageProcessing(): void;

    /**
     * Stops message processing. For synchronous model, may close streams or sockets.
     */
    abstract protected function stopMessageProcessing(): void;

    /**
     * Writes a JsonRpcMessage to the underlying transport.
     * Implementations must serialize the message to JSON and send it to the peer.
     */
    abstract protected function writeMessage(JsonRpcMessage $message): void;
}