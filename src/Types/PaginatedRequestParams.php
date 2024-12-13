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
 * Filename: Types/PaginatedRequestParams.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Params for a paginated request:
 * {
 *   cursor?: string;
 *   [key: string]: unknown
 * }
 */
class PaginatedRequestParams implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public ?string $cursor = null,
    ) {}

    public function validate(): void {
        // No required fields
    }

    public function jsonSerialize(): mixed {
        $data = [];
        if ($this->cursor !== null) {
            $data['cursor'] = $this->cursor;
        }
        return array_merge($data, $this->extraFields);
    }
}