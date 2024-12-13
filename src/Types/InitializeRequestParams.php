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
 * Filename: Types/InitializeRequestParams.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Params for InitializeRequest
 * {
 *   protocolVersion: string;
 *   capabilities: ClientCapabilities;
 *   clientInfo: Implementation;
 * }
 */
class InitializeRequestParams implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $protocolVersion,
        public readonly ClientCapabilities $capabilities,
        public readonly Implementation $clientInfo,
    ) {}

    public function validate(): void {
        if (empty($this->protocolVersion)) {
            throw new \InvalidArgumentException('Protocol version cannot be empty');
        }
        $this->capabilities->validate();
        $this->clientInfo->validate();
    }

    public function jsonSerialize(): mixed {
        $data = [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities,
            'clientInfo' => $this->clientInfo,
        ];
        return array_merge($data, $this->extraFields);
    }
}