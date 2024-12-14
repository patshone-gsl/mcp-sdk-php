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
 * Filename: Server/Transport/SseServerTransport.php
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
use RuntimeException;

/**
 * Server-side SSE transport for MCP servers.
 *
 * This transport simulates a similar setup to the Python code:
 * - `handleSseRequest()` sets up SSE headers and returns a session_id for the client.
 * - `handlePostRequest()` receives messages from the client (POST) and passes them to the session.
 * - `writeMessage()` sends events to all connected SSE clients.
 */
class SseServerTransport implements Transport, SessionAwareTransport {
    private array $sessions = [];
    private ?BaseSession $session = null;
    private bool $isStarted = false;

    public function __construct(
        private readonly string $endpoint,
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
     * Handles initial SSE connection from a client.
     *
     * @param resource $output An output stream resource to write SSE events to.
     * @return string The generated session_id for this connection.
     */
    public function handleSseRequest($output): string {
        if (!$this->isStarted) {
            throw new RuntimeException('Transport not started');
        }

        $sessionId = bin2hex(random_bytes(16));
        $this->sessions[$sessionId] = [
            'created' => time(),
            'lastSeen' => time(),
            'output' => $output,
        ];

        // Set SSE headers (assuming this method is called before headers are sent)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Send endpoint information event
        $this->sendSseEvent($sessionId, 'endpoint', $this->endpoint . '?session_id=' . $sessionId);

        $this->logger->debug("New SSE connection established: $sessionId");
        return $sessionId;
    }

    /**
     * Handles incoming message from client via POST.
     * 
     * @param string $sessionId The session ID provided by the client as a query param.
     * @param string $content The JSON content from the POST body.
     * @throws McpError If parsing or validation fails.
     */
    public function handlePostRequest(string $sessionId, string $content): void {
        if (!$this->isStarted) {
            throw new RuntimeException('Transport not started');
        }

        if (!isset($this->sessions[$sessionId])) {
            throw new McpError(new ErrorData(
                -32001,
                'Session not found'
            ));
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new McpError(new ErrorData(
                -32700,
                'Parse error: ' . $e->getMessage()
            ));
        }

        // Validate jsonrpc version
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new McpError(new ErrorData(
                -32600,
                'Invalid Request: jsonrpc version must be "2.0"'
            ));
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
                $message = new JsonRpcMessage($errorMsg);
            } elseif ($hasMethod && $hasId && !$hasResult) {
                // JSONRPCRequest
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
                $message = new JsonRpcMessage($req);
            } elseif ($hasMethod && !$hasId && !$hasResult && !$hasError) {
                // JSONRPCNotification
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
                $message = new JsonRpcMessage($not);
            } elseif ($hasId && $hasResult && !$hasMethod && !$hasError) {
                // JSONRPCResponse
                $resultData = $data['result'];
                $result = $this->buildResult($resultData);

                $resp = new JSONRPCResponse(
                    jsonrpc: '2.0',
                    id: $id,
                    result: $result
                );
                $resp->validate();
                $message = new JsonRpcMessage($resp);
            } else {
                throw new McpError(new ErrorData(
                    -32600,
                    'Invalid Request: Could not determine message type'
                ));
            }

            $this->sessions[$sessionId]['lastSeen'] = time();
            $this->logger->debug("Received message from session $sessionId");
            
            // Pass message to the session
            if ($this->session) {
                $this->session->handleIncomingMessage($message);
            }

        } catch (McpError $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new McpError(new ErrorData(
                -32700,
                'Parse error: ' . $e->getMessage()
            ));
        }
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

        foreach (array_keys($this->sessions) as $sessionId) {
            $this->sendSseEvent($sessionId, 'message', $json);
        }
    }

    public function readMessage(): ?JsonRpcMessage {
        // SSE does not provide a direct way to read messages. The POST request handler deals with incoming messages.
        // So this can return null, or we may never call this from the server logic.
        return null;
    }

    /**
     * Builds a RequestParams object from an array.
     */
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

    /**
     * Builds a NotificationParams object from an array.
     */
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

    /**
     * Builds a Result object from an array.
     */
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

    /**
     * Sends an SSE event to a specific session.
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
     * Converts a meta array into a Meta object.
     */
    private function metaFromArray(array $metaArr): \Mcp\Types\Meta {
        $meta = new \Mcp\Types\Meta();
        foreach ($metaArr as $mk => $mv) {
            $meta->$mk = $mv;
        }
        return $meta;
    }

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