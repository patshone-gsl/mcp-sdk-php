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
 * Filename: Server/Transport/StdioServerTransport.php
 */

declare(strict_types=1);

namespace Mcp\Server\Transport;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Shared\McpError;
use Mcp\Shared\ErrorData as TypesErrorData;
use Mcp\Types\JsonRpcErrorObject;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\RequestParams;
use Mcp\Types\NotificationParams;
use Mcp\Types\Result;
use RuntimeException;

/**
 * STDIO-based transport implementation for MCP servers
 */
class StdioServerTransport implements BufferedTransport, NonBlockingTransport {
    private $stdin;
    private $stdout;
    private array $writeBuffer = [];
    private bool $isStarted = false;

    /**
     * @param resource|null $stdin Input stream (defaults to STDIN)
     * @param resource|null $stdout Output stream (defaults to STDOUT)
     */
    public function __construct(
        $stdin = null,
        $stdout = null
    ) {
        if ($stdin !== null && !is_resource($stdin)) {
            throw new \InvalidArgumentException('stdin must be a valid resource.');
        }
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('stdout must be a valid resource.');
        }

        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
    }

    public function start(): void {
        if ($this->isStarted) {
            throw new RuntimeException('Transport already started');
        }

        // Set streams to non-blocking mode if not on Windows
        $os = PHP_OS_FAMILY;
        if ($os !== 'Windows') {
            if (!stream_set_blocking($this->stdin, false)) {
                throw new RuntimeException('Failed to set stdin to non-blocking mode');
            }
            if (!stream_set_blocking($this->stdout, false)) {
                throw new RuntimeException('Failed to set stdout to non-blocking mode');
            }
        }

        $this->isStarted = true;
    }

    public function stop(): void {
        if (!$this->isStarted) {
            return;
        }

        $this->flush();
        $this->isStarted = false;
    }

    public function hasDataAvailable(): bool {
        $read = [$this->stdin];
        $write = $except = [];
        return stream_select($read, $write, $except, 0) > 0;
    }

    public function readMessage(): ?JsonRpcMessage {
        if (!$this->isStarted) {
            throw new RuntimeException('Transport not started');
        }

        $line = fgets($this->stdin);
        if ($line === false) {
            return null; // No data available
        }

        try {
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Parse error
            throw new McpError(
                new TypesErrorData(
                    code: -32700,
                    message: 'Parse error: ' . $e->getMessage()
                )
            );
        }

        // Validate jsonrpc version
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new McpError(
                new TypesErrorData(
                    code: -32600,
                    message: 'Invalid Request: jsonrpc version must be "2.0"'
                )
            );
        }

        // Determine message type
        $hasMethod = array_key_exists('method', $data);
        $hasId = array_key_exists('id', $data);
        $hasResult = array_key_exists('result', $data);
        $hasError = array_key_exists('error', $data);

        // Convert 'id' if present
        $id = null;
        if ($hasId) {
            $id = new RequestId($data['id']);
        }

        try {
            if ($hasError) {
                // It's a JSONRPCError
                // error should be { code: number, message: string, data?: any }
                $errorObj = new JsonRpcErrorObject(
                    code: $data['error']['code'] ?? 0,
                    message: $data['error']['message'] ?? '',
                    data: $data['error']['data'] ?? null
                );
                $errorMsg = new JSONRPCError(
                    jsonrpc: '2.0',
                    id: $id ?? new RequestId(''), // must have id
                    error: $errorObj
                );
                $errorMsg->validate();
                return new JsonRpcMessage($errorMsg);
            } elseif ($hasMethod && $hasId && !$hasResult) {
                // It's a JSONRPCRequest
                // params should be processed into RequestParams
                $params = null;
                if (isset($data['params']) && is_array($data['params'])) {
                    $params = new RequestParams(
                        $_meta = isset($data['params']['_meta']) ? $this->metaFromArray($data['params']['_meta']) : null
                    );
                    // Set extra fields from params
                    foreach ($data['params'] as $k => $v) {
                        if ($k !== '_meta') {
                            $params->$k = $v;
                        }
                    }
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
                // It's a JSONRPCNotification
                // params -> NotificationParams
                $params = null;
                if (isset($data['params']) && is_array($data['params'])) {
                    $params = new NotificationParams(
                        $_meta = isset($data['params']['_meta']) ? $this->metaFromArray($data['params']['_meta']) : null
                    );
                    foreach ($data['params'] as $k => $v) {
                        if ($k !== '_meta') {
                            $params->$k = $v;
                        }
                    }
                }

                $not = new JSONRPCNotification(
                    jsonrpc: '2.0',
                    params: $params,
                    method: $data['method']
                );
                $not->validate();
                return new JsonRpcMessage($not);
            } elseif ($hasId && $hasResult && !$hasMethod && !$hasError) {
                // It's a JSONRPCResponse
                // result should be processed into a Result object
                $resultData = $data['result'];
                // Create a generic Result (we have a Result class that allows arbitrary fields)
                $result = new Result(
                    $_meta = isset($resultData['_meta']) ? $this->metaFromArray($resultData['_meta']) : null
                );
                foreach ($resultData as $k => $v) {
                    if ($k !== '_meta') {
                        $result->$k = $v;
                    }
                }

                $resp = new JSONRPCResponse(
                    jsonrpc: '2.0',
                    id: $id,
                    result: $result
                );
                $resp->validate();
                return new JsonRpcMessage($resp);
            } else {
                // Invalid structure
                throw new McpError(
                    new TypesErrorData(
                        code: -32600,
                        message: 'Invalid Request: Could not determine message type'
                    )
                );
            }
        } catch (McpError $e) {
            // Rethrow McpError as is
            throw $e;
        } catch (\Exception $e) {
            // Other exceptions become parse errors
            throw new McpError(
                new TypesErrorData(
                    code: -32700,
                    message: 'Parse error: ' . $e->getMessage()
                )
            );
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

        $this->writeBuffer[] = $json . "\n";
    }

    public function flush(): void {
        if (!$this->isStarted) {
            return;
        }

        while (!empty($this->writeBuffer)) {
            $data = array_shift($this->writeBuffer);
            $written = fwrite($this->stdout, $data);

            if ($written === false) {
                throw new RuntimeException('Failed to write to stdout');
            }

            // Handle partial writes
            if ($written < strlen($data)) {
                $this->writeBuffer = [substr($data, $written), ...$this->writeBuffer];
                break;
            }
        }

        fflush($this->stdout);
    }

    public static function create($stdin = null, $stdout = null): self {
        return new self($stdin, $stdout);
    }

    /**
     * Helper to build a Meta object from an array
     */
    private function metaFromArray(array $metaArr): \Mcp\Types\Meta {
        $meta = new \Mcp\Types\Meta();
        foreach ($metaArr as $mk => $mv) {
            $meta->$mk = $mv;
        }
        return $meta;
    }
}