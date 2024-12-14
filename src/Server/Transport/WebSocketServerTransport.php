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
 * Filename: Server/Transport/WebSocketServerTransport.php
 */

declare(strict_types=1);

namespace Mcp\Server\Transport;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Shared\McpError;
use Mcp\Shared\ErrorData;
use Mcp\Shared\BaseSession;
use Mcp\Types\JsonRpcErrorObject;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\RequestParams;
use Mcp\Types\NotificationParams;
use Mcp\Types\Result;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServerInterface;
use RuntimeException;

/**
 * WebSocket server transport for MCP.
 *
 * Uses Ratchet for WebSocket handling. OnMessage parses incoming JSON-RPC messages,
 * converts them to typed objects, and passes them to the session.
 * writeMessage() broadcasts messages to all connected clients.
 */
class WebSocketServerTransport implements Transport, MessageComponentInterface, WsServerInterface {
    private array $connections = [];
    private ?BaseSession $session = null;
    private bool $isStarted = false;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    public function start(): void {
        if ($this->isStarted) {
            throw new RuntimeException('Transport already started');
        }

        if (!$this->session) {
            throw new RuntimeException('No session attached to transport');
        }

        $this->isStarted = true;
        $this->logger->debug('WebSocket transport started');
    }

    public function stop(): void {
        if (!$this->isStarted) {
            return;
        }

        foreach ($this->connections as $conn) {
            $conn->close();
        }
        $this->connections = [];
        $this->isStarted = false;
        $this->logger->debug('WebSocket transport stopped');
    }

    public function attachSession(BaseSession $session): void {
        $this->session = $session;
    }

    public function onOpen(ConnectionInterface $conn): void {
        $connId = spl_object_hash($conn);
        $this->connections[$connId] = $conn;

        $this->logger->debug("New WebSocket connection: $connId");

        if ($conn instanceof \Ratchet\WebSocket\WsConnection) {
            $conn->setSubProtocol('mcp');
        }
    }

    public function onMessage(ConnectionInterface $from, $msg): void {
        try {
            $data = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->sendError($from, -32700, 'Parse error: ' . $e->getMessage());
            return;
        }

        // Validate jsonrpc
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            $this->sendError($from, -32600, 'Invalid Request: jsonrpc version must be "2.0"');
            return;
        }

        // Determine message type
        $hasMethod = array_key_exists('method', $data);
        $hasId = array_key_exists('id', $data);
        $hasResult = array_key_exists('result', $data);
        $hasError = array_key_exists('error', $data);

        $id = null;
        if ($hasId) {
            $id = new RequestId($data['id']);
        }

        try {
            $message = $this->buildMessage($data, $hasMethod, $hasId, $hasResult, $hasError, $id);

            if ($this->session) {
                $this->session->handleIncomingMessage($message);
            }
        } catch (McpError $e) {
            // Send error response
            $error = $e->error;
            $errMsg = new JsonRpcMessage(
                error: $error,
                jsonrpc: '2.0',
                id: $id // If original had an id, use it; else null is allowed
            );
            $from->send(json_encode($errMsg, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (\Exception $e) {
            $this->sendError($from, -32700, 'Parse error: ' . $e->getMessage(), $id);
        }
    }

    public function onClose(ConnectionInterface $conn): void {
        $connId = spl_object_hash($conn);
        unset($this->connections[$connId]);
        $this->logger->debug("Connection closed: $connId");
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void {
        $connId = spl_object_hash($conn);
        $this->logger->error("Error on connection $connId: " . $e->getMessage());
        $conn->close();
    }

    public function writeMessage(JsonRpcMessage $message): void {
        if (!$this->isStarted) {
            throw new RuntimeException('Transport not started');
        }

        $json = json_encode($message,
            JSON_UNESCAPED_SLASHES |
            JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            throw new RuntimeException('Failed to encode message as JSON: ' . json_last_error_msg());
        }

        foreach ($this->connections as $conn) {
            $conn->send($json);
        }
    }

    public function getSubProtocols(): array {
        return ['mcp'];
    }

    public function readMessage(): ?JsonRpcMessage {
        // Like SSE, we don't directly read messages here; they're handled by onMessage.
        return null;
    }

    private function sendError(ConnectionInterface $conn, int $code, string $message, ?RequestId $id = null): void {
        $err = new JsonRpcMessage(
            jsonrpc: '2.0',
            id: $id,
            error: new ErrorData($code, $message)
        );
        $conn->send(json_encode($err, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * Builds a JsonRpcMessage from the decoded data.
     */
    private function buildMessage(array $data, bool $hasMethod, bool $hasId, bool $hasResult, bool $hasError, ?RequestId $id): JsonRpcMessage {
        if ($hasError) {
            $errorObj = new JsonRpcErrorObject(
                code: $data['error']['code'] ?? 0,
                message: $data['error']['message'] ?? '',
                data: $data['error']['data'] ?? null
            );
            $errorMsg = new JSONRPCError(
                jsonrpc: '2.0',
                id: $id ?? new RequestId(''),
                error: $errorObj
            );
            $errorMsg->validate();
            return new JsonRpcMessage($errorMsg);
        } elseif ($hasMethod && $hasId && !$hasResult) {
            // Request
            $params = null;
            if (isset($data['params']) && is_array($data['params'])) {
                $params = $this->buildRequestParams($data['params']);
            }
            $req = new JSONRPCRequest(
                jsonrpc: '2.0',
                id: $id,
                params: $params,
                method: $data['method']
            );
            $req->validate();
            return new JsonRpcMessage($req);
        } elseif ($hasMethod && !$hasId && !$hasResult && !$hasError) {
            // Notification
            $params = null;
            if (isset($data['params']) && is_array($data['params'])) {
                $params = $this->buildNotificationParams($data['params']);
            }
            $not = new JSONRPCNotification(
                jsonrpc: '2.0',
                params: $params,
                method: $data['method']
            );
            $not->validate();
            return new JsonRpcMessage($not);
        } elseif ($hasId && $hasResult && !$hasMethod && !$hasError) {
            // Response
            $result = $this->buildResult($data['result']);
            $resp = new JSONRPCResponse(
                jsonrpc: '2.0',
                id: $id,
                result: $result
            );
            $resp->validate();
            return new JsonRpcMessage($resp);
        } else {
            throw new McpError(
                new ErrorData(-32600, 'Invalid Request: Could not determine message type')
            );
        }
    }

    private function buildRequestParams(array $paramsArr): RequestParams {
        $meta = null;
        if (isset($paramsArr['_meta']) && is_array($paramsArr['_meta'])) {
            $meta = $this->metaFromArray($paramsArr['_meta']);
        }

        $params = new RequestParams($_meta = $meta);
        foreach ($paramsArr as $k => $v) {
            if ($k !== '_meta') {
                $params->$k = $v;
            }
        }
        return $params;
    }

    private function buildNotificationParams(array $paramsArr): NotificationParams {
        $meta = null;
        if (isset($paramsArr['_meta']) && is_array($paramsArr['_meta'])) {
            $meta = $this->metaFromArray($paramsArr['_meta']);
        }

        $params = new NotificationParams($_meta = $meta);
        foreach ($paramsArr as $k => $v) {
            if ($k !== '_meta') {
                $params->$k = $v;
            }
        }
        return $params;
    }

    private function buildResult(array $resultData): Result {
        $meta = null;
        if (isset($resultData['_meta']) && is_array($resultData['_meta'])) {
            $meta = $this->metaFromArray($resultData['_meta']);
        }

        $res = new Result($_meta = $meta);
        foreach ($resultData as $k => $v) {
            if ($k !== '_meta') {
                $res->$k = $v;
            }
        }
        return $res;
    }

    private function metaFromArray(array $metaArr): \Mcp\Types\Meta {
        $meta = new \Mcp\Types\Meta();
        foreach ($metaArr as $mk => $mv) {
            $meta->$mk = $mv;
        }
        return $meta;
    }
}

/**
 * Factory function to create a WebSocket server
 */
function create_websocket_server(?LoggerInterface $logger = null): WebSocketServerTransport {
    return new WebSocketServerTransport($logger ?? new NullLogger());
}