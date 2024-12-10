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
 * Filename: Types/RequestId.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Base type for all request IDs
 */
class RequestId implements McpModel {
    public function __construct(
        private string|int $id,
    ) {}

    public function getValue(): string|int {
        return $this->id;
    }

    public function validate(): void {
        if (is_string($this->id) && empty($this->id)) {
            throw new \InvalidArgumentException('Request ID string cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        return $this->id;
    }
}