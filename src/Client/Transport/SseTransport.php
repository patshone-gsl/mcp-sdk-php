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
 * Filename: Client/Transport/SseTransport.php
 */

declare(strict_types=1);

namespace Mcp\Client\Transport;

use Mcp\Types\JsonRpcMessage;
use Mcp\Shared\MemoryStream;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use InvalidArgumentException;

/**
 * Handles SSE (Server-Sent Events) based communication with an MCP server
 */
class SseTransport {
    private $eventStream = null;
    private $curlHandle = null;
    private $logger;
    private $endpoint = null;

    public function __construct(
        private readonly string $url,
        private readonly array $headers = [],
        private readonly float $timeout = 5.0,
        private readonly float $sseReadTimeout = 300.0,
        ?LoggerInterface $logger = null,
    ) {
        if (empty($url)) {
            throw new InvalidArgumentException('URL cannot be empty');
        }
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Opens the connection to the SSE server
     * 
     * @return array{MemoryStream, MemoryStream} Tuple of read and write streams
     */
    public function connect(): array {
        $this->logger->info('Connecting to SSE endpoint: ' . $this->removeRequestParams($this->url));

        // Initialize cURL for SSE
        $this->curlHandle = curl_init();
        curl_setopt_array($this->curlHandle, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: text/event-stream',
                'Cache-Control: no-cache',
            ], $this->formatHeaders($this->headers)),
            CURLOPT_TIMEOUT => $this->sseReadTimeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);

        $this->eventStream = fopen('php://temp', 'r+');
        if ($this->eventStream === false) {
            throw new RuntimeException('Failed to create event stream buffer');
        }

        $readStream = new class($this) extends MemoryStream {
            private $transport;
            private $buffer = '';
            private $eventData = '';
            private $eventName = '';
            
            public function __construct(SseTransport $transport) {
                $this->transport = $transport;
                parent::__construct();
            }
            
            public function receive() {
                while (true) {
                    // Read from cURL
                    $chunk = $this->transport->readEventChunk();
                    if ($chunk === null) {
                        return null;
                    }
                    
                    $this->buffer .= $chunk;
                    
                    // Process line by line
                    while (($pos = strpos($this->buffer, "\n")) !== false) {
                        $line = substr($this->buffer, 0, $pos);
                        $this->buffer = substr($this->buffer, $pos + 1);
                        
                        $line = rtrim($line);
                        if (empty($line)) {
                            if (!empty($this->eventData)) {
                                $event = $this->processEvent();
                                if ($event !== null) {
                                    return $event;
                                }
                            }
                            continue;
                        }
                        
                        if (str_starts_with($line, 'event:')) {
                            $this->eventName = trim(substr($line, 6));
                        } elseif (str_starts_with($line, 'data:')) {
                            $this->eventData .= trim(substr($line, 5));
                        }
                    }
                }
            }

            private function processEvent() {
                $result = null;
                
                if ($this->eventName === 'endpoint') {
                    $this->transport->setEndpoint($this->eventData);
                } elseif ($this->eventName === 'message') {
                    try {
                        $data = json_decode($this->eventData, true);
                        $result = new JsonRpcMessage(
                            jsonrpc: $data['jsonrpc'],
                            id: $data['id'] ?? null,
                            method: $data['method'] ?? null,
                            params: $data['params'] ?? null,
                            result: $data['result'] ?? null,
                            error: $data['error'] ?? null
                        );
                    } catch (\Exception $e) {
                        $result = $e;
                    }
                }
                
                $this->eventData = '';
                $this->eventName = '';
                return $result;
            }
        };

        $writeStream = new class($this) extends MemoryStream {
            private $transport;
            
            public function __construct(SseTransport $transport) {
                $this->transport = $transport;
                parent::__construct();
            }
            
            public function send($message): void {
                if (!($message instanceof JsonRpcMessage)) {
                    return;
                }

                $endpoint = $this->transport->getEndpoint();
                if ($endpoint === null) {
                    throw new RuntimeException('No endpoint URL available');
                }

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $endpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($message, JSON_UNESCAPED_SLASHES),
                    CURLOPT_HTTPHEADER => array_merge([
                        'Content-Type: application/json',
                    ], $this->transport->getFormattedHeaders()),
                    CURLOPT_TIMEOUT => $this->transport->getTimeout(),
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode < 200 || $httpCode >= 300) {
                    throw new RuntimeException("HTTP error: $httpCode");
                }
            }
        };

        return [$readStream, $writeStream];
    }

    public function close(): void {
        if ($this->eventStream !== null && is_resource($this->eventStream)) {
            fclose($this->eventStream);
        }
        if ($this->curlHandle !== null) {
            curl_close($this->curlHandle);
        }
    }

    private function removeRequestParams(string $url): string {
        $parts = parse_url($url);
        return $parts['scheme'] . '://' . $parts['host'] . 
               (isset($parts['port']) ? ':' . $parts['port'] : '') . 
               ($parts['path'] ?? '');
    }

    private function formatHeaders(array $headers): array {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = "$name: $value";
        }
        return $formatted;
    }

    public function getFormattedHeaders(): array {
        return $this->formatHeaders($this->headers);
    }

    public function getTimeout(): float {
        return $this->timeout;
    }

    public function readEventChunk(): ?string {
        $chunk = curl_exec($this->curlHandle);
        if ($chunk === false) {
            return null;
        }
        return $chunk;
    }

    public function setEndpoint(string $endpointUrl): void {
        $baseUrl = parse_url($this->url);
        $endpoint = parse_url($endpointUrl);
        
        if (!$endpoint || !$baseUrl) {
            throw new RuntimeException('Invalid URL format');
        }

        if ($baseUrl['scheme'] !== $endpoint['scheme'] || 
            $baseUrl['host'] !== $endpoint['host']) {
            throw new InvalidArgumentException(
                'Endpoint origin does not match connection origin: ' . $endpointUrl
            );
        }

        $this->endpoint = $endpointUrl;
        $this->logger->info('Endpoint URL set: ' . $endpointUrl);
    }

    public function getEndpoint(): ?string {
        return $this->endpoint;
    }
}