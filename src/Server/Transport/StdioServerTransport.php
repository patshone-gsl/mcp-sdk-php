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
 * Filename: Server/Transport/StdioServerTransport.php
 */

declare(strict_types=1);

namespace Mcp\Server\Transport;

use Mcp\Types\JsonRpcMessage;
use Mcp\Types\RequestId;
use Mcp\Shared\McpError;
use Mcp\Shared\ErrorData;
use Mcp\Server\Transport\BufferedTransport;
use Mcp\Server\Transport\NonBlockingTransport;
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
     * Create a new STDIO transport
     * 
     * @param resource|null $stdin Input stream (defaults to STDIN)
     * @param resource|null $stdout Output stream (defaults to STDOUT)
     */
    public function __construct(
        ?resource $stdin = null,
        ?resource $stdout = null
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
    }

    public function start(): void {
        if ($this->isStarted) {
            throw new \RuntimeException('Transport already started');
        }

        // Set streams to non-blocking mode
        if (!stream_set_blocking($this->stdin, false)) {
            throw new \RuntimeException('Failed to set stdin to non-blocking mode');
        }
        if (!stream_set_blocking($this->stdout, false)) {
            throw new \RuntimeException('Failed to set stdout to non-blocking mode');
        }

        $this->isStarted = true;
    }

    public function stop(): void {
        if (!$this->isStarted) {
            return;
        }

        // Flush any remaining output
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
            throw new \RuntimeException('Transport not started');
        }

        $line = fgets($this->stdin);
        if ($line === false) {
            return null;
        }

        try {
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            
            // Validate required fields according to schema
            if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
                throw new McpError(new ErrorData(
                    -32600,
                    'Invalid Request: jsonrpc version must be "2.0"'
                ));
            }

            return new JsonRpcMessage(
                jsonrpc: '2.0',
                id: isset($data['id']) ? new RequestId($data['id']) : null,
                method: $data['method'] ?? null,
                params: $data['params'] ?? null,
                result: isset($data['result']) ? (object)$data['result'] : null,
                error: $data['error'] ?? null
            );
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
            throw new \RuntimeException('Transport not started');
        }

        $json = json_encode($message, 
            JSON_UNESCAPED_SLASHES | 
            JSON_INVALID_UTF8_SUBSTITUTE
        );
        
        if ($json === false) {
            throw new \RuntimeException('Failed to encode message as JSON: ' . json_last_error_msg());
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
            
            // Handle partial writes
            if ($written < strlen($data)) {
                $this->writeBuffer = [substr($data, $written), ...$this->writeBuffer];
                break;
            }
        }

        fflush($this->stdout);
    }
    
    public static function create(?resource $stdin = null, ?resource $stdout = null): self {
        return new self($stdin, $stdout);
    }
    
}