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
 * Filename: Types/JsonRpcMessage.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * JSON-RPC message envelope
 */
class JsonRpcMessage implements McpModel {
    public function __construct(
        public readonly string $jsonrpc = '2.0',
        public ?RequestId $id = null,
        public ?string $method = null,
        public ?array $params = null,
        public ?Result $result = null,
        public ?array $error = null,
    ) {}

    public function validate(): void {
        if ($this->jsonrpc !== '2.0') {
            throw new \InvalidArgumentException('JSON-RPC version must be "2.0"');
        }

        if ($this->error !== null) {
            if (!isset($this->error['code']) || !isset($this->error['message'])) {
                throw new \InvalidArgumentException('JSON-RPC error must have code and message');
            }
        }

        if ($this->id !== null) {
            $this->id->validate();
        }

        if ($this->result !== null) {
            $this->result->validate();
        }
    }

    public function jsonSerialize(): mixed {
        $data = [
            'jsonrpc' => $this->jsonrpc,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        if ($this->method !== null) {
            $data['method'] = $this->method;
        }
        if ($this->params !== null) {
            $data['params'] = $this->params;
        }
        if ($this->result !== null) {
            $data['result'] = $this->result;
        }
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }
}