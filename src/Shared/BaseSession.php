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
 * Filename: Shared/BaseSession.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Types\ErrorData;
use Mcp\Types\ProgressToken;
use Mcp\Types\ProgressNotification;
use InvalidArgumentException;
use RuntimeException;

/**
 * Handles responding to individual requests.
 */
class RequestResponder {
    private bool $responded = false;

    public function __construct(
        private readonly RequestId $requestId,
        private readonly array $params,
        private readonly mixed $request,
        private readonly BaseSession $session,
    ) {}

    public function respond(mixed $response): void {
        if ($this->responded) {
            throw new \RuntimeException('Request already responded to');
        }
        $this->responded = true;

        $this->session->sendResponse($this->requestId, $response);
    }
}

/**
 * Base session for managing MCP communication.
 */
abstract class BaseSession {
    protected bool $isInitialized = false;
    private array $responseHandlers = [];
    private array $requestHandlers = [];
    private array $notificationHandlers = [];
    private int $requestId = 0;

    public function __construct(
        private readonly string $receiveRequestType,
        private readonly string $receiveNotificationType,
    ) {}

    public function initialize(): void {
        if ($this->isInitialized) {
            throw new \RuntimeException('Session already initialized');
        }
        $this->isInitialized = true;
        $this->startMessageProcessing();
    }

    public function close(): void {
        if (!$this->isInitialized) {
            return;
        }
        $this->stopMessageProcessing();
        $this->isInitialized = false;
    }

    public function sendRequest(mixed $request, string $resultType): mixed {
        $this->validateRequest($request);
        
        $requestId = $this->requestId++;
        
        $jsonRpcRequest = new JsonRpcMessage(
            jsonrpc: '2.0',
            id: new RequestId($requestId),
            method: $request->method,
            params: $request->params
        );

        $this->writeMessage($jsonRpcRequest);

        return $this->waitForResponse($requestId, $resultType);
    }

    public function sendNotification(mixed $notification): void {
        $jsonRpcMessage = new JsonRpcMessage(
            jsonrpc: '2.0',
            method: $notification->method,
            params: $notification->params
        );

        $this->writeMessage($jsonRpcMessage);
    }

    public function sendResponse(RequestId $requestId, mixed $response): void {
        if ($response instanceof ErrorData) {
            $message = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: $requestId,
                error: $response
            );
        } else {
            $message = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: $requestId,
                result: $response
            );
        }

        $this->writeMessage($message);
    }

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

    public function onRequest(callable $handler): void {
        $this->requestHandlers[] = $handler;
    }

    public function onNotification(callable $handler): void {
        $this->notificationHandlers[] = $handler;
    }

    protected function handleIncomingMessage(JsonRpcMessage $message): void {
        $this->validateMessage($message);

        if ($message->id !== null) {
            if ($message->method !== null) {
                // It's a request
                $request = $this->validateIncomingRequest($message);
                $responder = new RequestResponder(
                    $message->id,
                    $message->params['_meta'] ?? null,
                    $request,
                    $this
                );
                foreach ($this->requestHandlers as $handler) {
                    $handler($responder);
                }
            } else {
                // It's a response
                if (isset($this->responseHandlers[$message->id->getValue()])) {
                    $handler = $this->responseHandlers[$message->id->getValue()];
                    unset($this->responseHandlers[$message->id->getValue()]);
                    $handler($message);
                }
            }
        } else if ($message->method !== null) {
            // It's a notification
            $notification = $this->validateIncomingNotification($message);
            foreach ($this->notificationHandlers as $handler) {
                $handler($notification);
            }
        }
    }

    private function validateMessage(JsonRpcMessage $message): void {
        if ($message->jsonrpc !== '2.0') {
            throw new \InvalidArgumentException('Invalid JSON-RPC version');
        }
    }

    private function validateRequest($request): void {
        if (empty($request->method)) {
            throw new \InvalidArgumentException('Request must have a method');
        }
    }

    private function validateIncomingRequest(JsonRpcMessage $message): mixed {
        $requestClass = $this->receiveRequestType;
        return new $requestClass(
            method: $message->method,
            params: $message->params
        );
    }

    private function validateIncomingNotification(JsonRpcMessage $message): mixed {
        $notificationClass = $this->receiveNotificationType;
        return new $notificationClass(
            method: $message->method,
            params: $message->params
        );
    }

    abstract protected function startMessageProcessing(): void;
    abstract protected function stopMessageProcessing(): void;
    abstract protected function writeMessage(JsonRpcMessage $message): void;
    abstract protected function waitForResponse(int $requestId, string $resultType): mixed;
}