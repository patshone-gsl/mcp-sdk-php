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
 * Filename: Server/Transport/SseServerTransport.php
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
use RuntimeException;

/**
 * Server-side SSE transport for MCP servers
 * 
 * This transport allows MCP servers to communicate with clients using Server-Sent Events (SSE)
 * for server-to-client messages and POST requests for client-to-server messages.
 */
class SseServerTransport implements Transport, SessionAwareTransport {
    private array $sessions = [];
    private ?BaseSession $session = null;
    private $outputBuffer = '';
    private bool $isStarted = false;

    public function __construct(
        private readonly string $endpoint,
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
        $this->logger->debug('SSE transport started');
    }

    public function stop(): void {
        if (!$this->isStarted) {
            return;
        }

        $this->sessions = [];
        $this->isStarted = false;
        $this->logger->debug('SSE transport stopped');
    }

    public function attachSession(BaseSession $session): void {
        $this->session = $session;
    }

    /**
     * Handles initial SSE connection from a client
     */
    public function handleSseRequest($output): string {
        if (!$this->isStarted) {
            throw new \RuntimeException('Transport not started');
        }

        $sessionId = bin2hex(random_bytes(16));
        $this->sessions[$sessionId] = [
            'created' => time(),
            'lastSeen' => time(),
            'output' => $output,
            'messageQueue' => []
        ];

        // Configure SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Send endpoint information
        $this->sendSseEvent(
            $sessionId, 
            'endpoint', 
            $this->endpoint . '?session_id=' . $sessionId
        );

        $this->logger->debug("New SSE connection established: $sessionId");
        return $sessionId;
    }

    /**
     * Handles incoming message from client via POST
     */
    public function handlePostRequest(string $sessionId, string $content): void {
        if (!isset($this->sessions[$sessionId])) {
            throw new McpError(new ErrorData(
                -32001,
                'Session not found'
            ));
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
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

            $this->sessions[$sessionId]['lastSeen'] = time();
            $this->logger->debug("Received message from session $sessionId");

        } catch (McpError $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new McpError(new ErrorData(
                -32700,
                'Parse error: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Writes a message to connected clients
     */
    public function writeMessage(JsonRpcMessage $message): void {
        if (!$this->isStarted) {
            throw new \RuntimeException('Transport not started');
        }

        $json = json_encode($message, 
            JSON_UNESCAPED_SLASHES | 
            JSON_INVALID_UTF8_SUBSTITUTE
        );

        foreach ($this->sessions as $sessionId => $session) {
            $this->sendSseEvent($sessionId, 'message', $json);
        }
    }

    /**
     * Sends an SSE event to a specific session
     */
    private function sendSseEvent(string $sessionId, string $event, string $data): void {
        if (!isset($this->sessions[$sessionId])) {
            return;
        }

        $output = "event: {$event}\n";
        $output .= "data: {$data}\n\n";

        $stream = $this->sessions[$sessionId]['output'];
        fwrite($stream, $output);
        fflush($stream);
    }

    /**
     * Cleans up expired sessions
     */
    public function cleanupSessions(int $maxAge = 3600): void {
        $now = time();
        foreach ($this->sessions as $sessionId => $session) {
            if ($now - $session['lastSeen'] > $maxAge) {
                unset($this->sessions[$sessionId]);
                $this->logger->debug("Cleaned up expired session: $sessionId");
            }
        }
    }
}