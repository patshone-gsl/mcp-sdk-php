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
 * Filename: Server/Transport/WebSocketServerTransport.php
 */

declare(strict_types=1);

namespace Mcp\Server\Transport;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Shared\McpError;
use Mcp\Shared\ErrorData;
use Mcp\Shared\BaseSession;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServerInterface;
use RuntimeException;

/**
 * WebSocket server transport for MCP
 * 
 * This implementation uses Ratchet (ReactPHP) for WebSocket handling,
 * which is a common PHP WebSocket server implementation.
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
            throw new \RuntimeException('Transport already started');
        }

        if (!$this->session) {
            throw new \RuntimeException('No session attached to transport');
        }

        $this->isStarted = true;
        $this->logger->debug('WebSocket transport started');
    }

    public function stop(): void {
        if (!$this->isStarted) {
            return;
        }

        foreach ($this->connections as $connection) {
            $connection->close();
        }
        $this->connections = [];
        $this->isStarted = false;
        $this->logger->debug('WebSocket transport stopped');
    }

    public function attachSession(BaseSession $session): void {
        $this->session = $session;
    }

    /**
     * Handle new WebSocket connection
     */
    public function onOpen(ConnectionInterface $conn): void {
        $connId = spl_object_hash($conn);
        $this->connections[$connId] = $conn;
        
        $this->logger->debug("New WebSocket connection: $connId");

        // Set the subprotocol as required by MCP
        if ($conn instanceof \Ratchet\WebSocket\WsConnection) {
            $conn->setSubProtocol('mcp');
        }
    }

    /**
     * Handle incoming WebSocket message
     */
    public function onMessage(ConnectionInterface $from, $msg): void {
        try {
            $data = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);
            
            // Validate JSON-RPC message according to schema
            if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
                throw new McpError(new ErrorData(
                    -32600,
                    'Invalid Request: jsonrpc version must be "2.0"'
                ));
            }

            $message = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: isset($data['id']) ? new RequestId($data['id']) : null,
                method: $data['method'] ?? null,
                params: $data['params'] ?? null,
                result: isset($data['result']) ? (object)$data['result'] : null,
                error: $data['error'] ?? null
            );

            // Process message through session
            if ($this->session) {
                $this->session->handleMessage($message);
            }

        } catch (McpError $e) {
            $errorMessage = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: null,
                error: $e->error
            );
            $from->send(json_encode($errorMessage));
        } catch (\Exception $e) {
            $errorMessage = new JsonRpcMessage(
                jsonrpc: '2.0',
                id: null,
                error: new ErrorData(-32700, 'Parse error: ' . $e->getMessage())
            );
            $from->send(json_encode($errorMessage));
        }
    }

    /**
     * Handle WebSocket connection close
     */
    public function onClose(ConnectionInterface $conn): void {
        $connId = spl_object_hash($conn);
        unset($this->connections[$connId]);
        $this->logger->debug("Connection closed: $connId");
    }

    /**
     * Handle WebSocket errors
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void {
        $connId = spl_object_hash($conn);
        $this->logger->error("Error on connection $connId: " . $e->getMessage());
        $conn->close();
    }

    /**
     * Write message to all connected clients
     */
    public function writeMessage(JsonRpcMessage $message): void {
        if (!$this->isStarted) {
            throw new \RuntimeException('Transport not started');
        }

        $json = json_encode($message, 
            JSON_UNESCAPED_SLASHES | 
            JSON_INVALID_UTF8_SUBSTITUTE
        );

        foreach ($this->connections as $connection) {
            $connection->send($json);
        }
    }

    /**
     * Required by WsServerInterface
     */
    public function getSubProtocols(): array {
        return ['mcp'];
    }
}

/**
 * Factory function to create a WebSocket server
 */
function create_websocket_server(?LoggerInterface $logger = null): WebSocketServerTransport {
    return new WebSocketServerTransport($logger);
}