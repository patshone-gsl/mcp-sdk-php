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
 * Filename: Client/Client.php
 */

declare(strict_types=1);

namespace Mcp\Client;

use Mcp\Client\Transport\StdioServerParameters;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Client\Transport\SseTransport;
use Mcp\Client\ClientSession;
use Mcp\Shared\MemoryStream;
use Mcp\Types\JsonRpcMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Main client class for MCP communication
 */
class Client {
    private ?ClientSession $session = null;
    private $logger;
    private $isRunning = false;

    public function __construct(?LoggerInterface $logger = null) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Connect to an MCP server using either stdio or SSE
     */
    public function connect(
        string $commandOrUrl,
        array $args = [],
        ?array $env = null,
        ?float $readTimeout = null
    ): ClientSession {
        $urlParts = parse_url($commandOrUrl);
        
        try {
            if (isset($urlParts['scheme']) && in_array($urlParts['scheme'], ['http', 'https'])) {
                $this->logger->info("Connecting to SSE endpoint: $commandOrUrl");
                $transport = new SseTransport($commandOrUrl);
            } else {
                $this->logger->info("Starting process: $commandOrUrl");
                $params = new StdioServerParameters($commandOrUrl, $args, $env);
                $transport = new StdioTransport($params);
            }

            [$readStream, $writeStream] = $transport->connect();
            
            $this->session = new ClientSession(
                readStream: $readStream,
                writeStream: $writeStream,
                readTimeout: $readTimeout
            );

            $this->session->initialize();
            $this->startReceiveLoop();
            
            return $this->session;
        } catch (\Exception $e) {
            $this->logger->error('Connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process incoming messages
     */
    private function receiveLoop(): void {
        if (!$this->session) {
            throw new RuntimeException('No active session');
        }

        $this->logger->info('Starting receive loop');
        $this->isRunning = true;

        while ($this->isRunning && $this->session->isInitialized()) {
            $message = $this->session->receiveMessage();
            if ($message === null) {
                continue;
            }

            if ($message instanceof \Exception) {
                $this->logger->error('Error: ' . $message->getMessage());
                continue;
            }

            if ($message instanceof JsonRpcMessage) {
                $this->logger->info('Received message from server: ' . json_encode($message));
            }
        }
    }

    /**
     * Start the message processing loop
     */
    private function startReceiveLoop(): void {
        if (!$this->session) {
            throw new RuntimeException('No active session');
        }

        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Failed to start receive loop: fork failed');
            }
            if ($pid === 0) {
                // Child process
                try {
                    $this->receiveLoop();
                } finally {
                    exit(0);
                }
            }
        } else {
            // Non-blocking approach
            set_time_limit(0);
            $this->receiveLoop();
        }
    }

    /**
     * Close the client connection
     */
    public function close(): void {
        $this->isRunning = false;
        if ($this->session) {
            $this->session->close();
            $this->session = null;
        }
    }
}