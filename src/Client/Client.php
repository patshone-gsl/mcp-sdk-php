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
 * Main client class for MCP communication.
 *
 * The client can connect to a server via stdio or SSE, initialize a session,
 * and start a receive loop to process incoming messages.
 */
class Client {
    private ?ClientSession $session = null;
    private LoggerInterface $logger;
    private bool $isRunning = false;

    public function __construct(?LoggerInterface $logger = null) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Connect to an MCP server using either STDIO or SSE.
     *
     * If commandOrUrl is an HTTP(S) URL, we use SSE transport.
     * Otherwise, we assume it's a command and use STDIO transport.
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
                // Use SSE transport for HTTP(S) URLs
                $this->logger->info("Connecting to SSE endpoint: $commandOrUrl");
                $transport = new SseTransport(
                    url: $commandOrUrl,
                    headers: [],          // Add custom headers if needed
                    timeout: 5.0,         // Connection timeout
                    sseReadTimeout: 300.0,// SSE read timeout
                    logger: $this->logger
                );
            } else {
                // Use stdio transport for commands
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
     * Process incoming messages in a loop.
     *
     * Similar to the Python `receive_loop()` which iterates over `session.incoming_messages`.
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
                // No message yet, just continue loop
                usleep(10000); // small sleep to avoid busy waiting
                continue;
            }

            if ($message instanceof \Exception) {
                $this->logger->error('Error: ' . $message->getMessage());
                continue;
            }

            if ($message instanceof JsonRpcMessage) {
                $this->logger->info('Received message from server: ' . json_encode($message));
                // If you need additional logic for incoming server requests/notifications,
                // you could add it here. The BaseSession handles responses already.
            }
        }
    }

    /**
     * Start the message processing loop in a non-blocking way if possible, else inline.
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
            // Run in the same process if pcntl_fork is not available
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