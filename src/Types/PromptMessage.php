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
 * Filename: Types/PromptMessage.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * PromptMessage
 * {
 *   role: Role;
 *   content: TextContent | ImageContent | EmbeddedResource;
 * }
 */
class PromptMessage implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly Role $role,
        public readonly TextContent|ImageContent|EmbeddedResource $content,
    ) {}

    public function validate(): void {
        $this->content->validate();
    }

    public function jsonSerialize(): mixed {
        return array_merge([
            'role' => $this->role->value,
            'content' => $this->content,
        ], $this->extraFields);
    }
}