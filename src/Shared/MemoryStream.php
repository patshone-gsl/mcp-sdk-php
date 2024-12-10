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
 * Filename: Shared/MemoryStream.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\JsonRpcMessage;
use Mcp\Client\ClientSession;
use Mcp\Server\Server;

/**
 * In-memory transports for MCP communication
 */
class MemoryStream {
    private array $messages = [];
    private array $exceptions = [];

    public function send($message): void {
        if ($message instanceof \Exception) {
            $this->exceptions[] = $message;
        } else {
            $this->messages[] = $message;
        }
    }

    public function receive() {
        if (!empty($this->exceptions)) {
            return array_shift($this->exceptions);
        }
        return array_shift($this->messages);
    }

    public function isEmpty(): bool {
        return empty($this->messages) && empty($this->exceptions);
    }
}