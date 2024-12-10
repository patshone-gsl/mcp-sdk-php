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
 * Filename: Client/Transport/StdioTransport.php
 */

declare(strict_types=1);

namespace Mcp\Client\Transport;

use Mcp\Types\JsonRpcMessage;
use Mcp\Shared\MemoryStream;
use RuntimeException;

/**
 * Manages stdio-based communication with an MCP server process
 */
class StdioTransport {
    private $process;
    private $pipes;

    public function __construct(
        private readonly StdioServerParameters $parameters
    ) {}

    /**
     * Opens the connection to the server process
     *
     * @return array{MemoryStream, MemoryStream} Tuple of read and write streams
     */
    public function connect(): array {
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => STDERR          // stderr
        ];

        // Ensure environment initialization is done before using EnvironmentHelper
        EnvironmentHelper::initialize();
        $env = $this->parameters->getEnv() ?? EnvironmentHelper::getDefaultEnvironment();

        $command = escapeshellcmd($this->parameters->getCommand());
        $args = array_map('escapeshellarg', $this->parameters->getArgs());
        $fullCommand = $command . ' ' . implode(' ', $args);

        $this->process = proc_open($fullCommand, $descriptorSpec, $this->pipes, null, $env);

        if ($this->process === false || !is_resource($this->process)) {
            throw new RuntimeException("Failed to start process: $fullCommand");
        }

        // Set up non-blocking reads on stdout
        stream_set_blocking($this->pipes[1], false);

        $readStream = new class($this->pipes[1]) extends MemoryStream {
            private $pipe;

            public function __construct($pipe) {
                $this->pipe = $pipe;
                parent::__construct();
            }

            public function receive() {
                $buffer = '';
                while (!feof($this->pipe)) {
                    $chunk = fgets($this->pipe);
                    if ($chunk === false) {
                        if (feof($this->pipe)) {
                            return null;
                        }
                        break;
                    }
                    $buffer .= $chunk;

                    if (str_ends_with(trim($buffer), "\n")) {
                        try {
                            $data = json_decode(trim($buffer), true);
                            return new JsonRpcMessage(
                                jsonrpc: $data['jsonrpc'],
                                id: $data['id'] ?? null,
                                method: $data['method'] ?? null,
                                params: $data['params'] ?? null,
                                result: $data['result'] ?? null,
                                error: $data['error'] ?? null
                            );
                        } catch (\Exception $e) {
                            return $e;
                        }
                    }
                }
                return null;
            }
        };

        $writeStream = new class($this->pipes[0]) extends MemoryStream {
            private $pipe;

            public function __construct($pipe) {
                $this->pipe = $pipe;
                parent::__construct();
            }

            public function send($message): void {
                if ($message instanceof JsonRpcMessage) {
                    $json = json_encode($message, JSON_UNESCAPED_SLASHES);
                    fwrite($this->pipe, $json . "\n");
                    fflush($this->pipe);
                }
            }
        };

        return [$readStream, $writeStream];
    }

    public function close(): void {
        if (isset($this->pipes)) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        if (isset($this->process) && is_resource($this->process)) {
            proc_close($this->process);
        }
    }
}